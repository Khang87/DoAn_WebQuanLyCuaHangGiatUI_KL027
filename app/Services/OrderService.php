<?php

namespace App\Services;

use App\Exceptions\SettledOrderException;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Garment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pricing;
use App\Models\Promotion;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Quy đổi 1 điểm tích lũy thành tiền.
     */
    public const POINT_VALUE = 1000;

    /**
     * Lý do voucher cuối cùng bị loại trong lần gọi create()/update() gần nhất.
     */
    private ?string $promotionRejection = null;

    /**
     * Lý do voucher bị loại ở lần xử lý gần nhất (null nghĩa là không bị loại).
     */
    public function promotionRejection(): ?string
    {
        return $this->promotionRejection;
    }

    /**
     * Chuẩn hóa bộ số tiền của đơn hàng.
     *
     *   Tạm tính            = Σ (số lượng hoặc kg × đơn giá lịch sử theo price_lists)
     *   Tiền giảm voucher   = Promotion::calculateDiscount(Tạm tính)
     *   Tiền giảm do điểm   = số điểm dùng × POINT_VALUE
     *   Tổng thanh toán     = Tạm tính - giảm voucher - giảm điểm (không nhỏ hơn 0)
     *
     * Mỗi khoản giảm được chặn tối đa bằng số tiền còn lại để tổng không âm.
     *
     * @return array{subtotal: float, discount_by_promotion: float, points_used: int, discount_by_points: float, total_amount: float}
     */
    public function calculateAmounts(float $subtotal, ?Promotion $promotion, int $pointsUsed, int $customerPoints): array
    {
        $subtotal = max(0, $subtotal);

        $discountByPromotion = $promotion
            ? min($promotion->calculateDiscount($subtotal), $subtotal)
            : 0.0;

        // Không cho dùng vượt số điểm khách đang có.
        $pointsUsed = max(0, min($pointsUsed, $customerPoints));

        $remaining = max(0, $subtotal - $discountByPromotion);
        $discountByPoints = min($pointsUsed * self::POINT_VALUE, $remaining);

        // Số điểm thực sự quy đổi được thành tiền (tránh ghi điểm "lãng phí").
        $effectivePoints = (int) floor($discountByPoints / self::POINT_VALUE);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_by_promotion' => round($discountByPromotion, 2),
            'points_used' => $effectivePoints,
            'discount_by_points' => round($discountByPoints, 2),
            'total_amount' => round(max(0, $subtotal - $discountByPromotion - $discountByPoints), 2),
        ];
    }

    /**
     * Tạo các dòng mặt hàng từ dữ liệu form, chốt đơn giá lịch sử và tính tạm tính.
     *
     * Đơn vị tính tiền lấy từ price_lists theo cặp service_id + garment_id:
     *   - đơn vị "kg"  → Tạm tính = Khối lượng × Đơn giá
     *   - đơn vị khác  → Tạm tính = Số lượng   × Đơn giá
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function buildItems(?array $rawItems): array
    {
        $rows = [];
        $subtotal = 0.0;

        foreach ($rawItems ?? [] as $item) {
            $serviceId = $item['service_id'] ?? null;
            $garmentId = $item['garment_id'] ?? null;

            // Giá lịch sử mới nhất theo cặp service_id + garment_id.
            $pricing = ($serviceId && $garmentId)
                ? Pricing::getLatestPricing((int) $serviceId, (int) $garmentId)
                : null;

            // Ưu tiên giá gửi lên; nếu thiếu thì lấy giá lịch sử từ bảng price_lists.
            $price = $item['price'] ?? null;
            if (($price === null || $price === '') && $pricing) {
                $price = $pricing->price;
            }

            $price = (float) ($price ?? 0);
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $weight = max(0, (float) ($item['weight'] ?? 0));

            // Dịch vụ tính theo kg dùng khối lượng làm đơn vị nhân với đơn giá.
            $isWeightUnit = Pricing::isWeightUnit($pricing?->unit);
            $multiplier = $isWeightUnit ? $weight : $quantity;
            $lineSubtotal = round($price * $multiplier, 2);

            $rows[] = [
                'service_id' => $serviceId ?: null,
                'garment_id' => $garmentId ?: null,
                // order_items.item_name là NOT NULL nhưng form chỉ gửi service/garment
                // nên tên mặt hàng được suy ra từ dịch vụ/loại đồ tương ứng.
                'item_name' => ($item['item_name'] ?? null) ?: $this->resolveItemName($serviceId, $garmentId),
                'item_type' => $item['item_type'] ?? 'service',
                'price' => $price,
                'quantity' => $quantity,
                'weight' => $weight,
                'subtotal' => $lineSubtotal,
                'notes' => $item['notes'] ?? null,
            ];

            $subtotal += $lineSubtotal;
        }

        return [$rows, round($subtotal, 2)];
    }

    /**
     * Suy ra tên mặt hàng khi form không gửi item_name.
     */
    private function resolveItemName($serviceId, $garmentId): string
    {
        if ($serviceId) {
            $name = Service::find($serviceId)?->name;
            if ($name) {
                return $name;
            }
        }

        if ($garmentId) {
            $name = Garment::find($garmentId)?->name;
            if ($name) {
                return $name;
            }
        }

        return 'Mặt hàng';
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Order::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('code', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $allowedSorts = ['id', 'code', 'total_amount', 'status', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['customer', 'employee', 'items.service', 'items.garment', 'promotion', 'invoice'])
            ->withTrashed()
            ->orderBy($sortBy, $sortOrder)
            ->paginate(20);
    }

    public function find(int $id): ?Order
    {
        return Order::with([
            'customer',
            'employee',
            'items.service',
            'items.garment',
            'promotion',
            'payments',
            'invoice',
            'delivery',
            'booking',
        ])->withTrashed()->find($id);
    }

    /**
     * Xác định voucher từ form: ưu tiên promotion_id, nếu rỗng thì tra theo mã code.
     */
    private function resolvePromotion(array $data): ?Promotion
    {
        if (! empty($data['promotion_id'])) {
            return Promotion::find($data['promotion_id']);
        }

        if (! empty($data['promotion_code'])) {
            return Promotion::findByCode($data['promotion_code']);
        }

        return null;
    }

    /**
     * Loại voucher nếu điều kiện nghiệp vụ không cho phép khách dùng.
     *
     * Chính sách: đơn vẫn được lưu nhưng KHÔNG áp dụng giảm giá, đồng thời ghi
     * nhận lý do để controller hiển thị cảnh báo cho người dùng.
     */
    private function applyPromotionConditions(?Promotion $promotion, ?Customer $customer, float $subtotal, ?int $excludeOrderId = null): ?Promotion
    {
        $this->promotionRejection = null;

        if (! $promotion) {
            return null;
        }

        $reason = $promotion->rejectionReasonForCustomer($customer, $subtotal, $excludeOrderId);

        if ($reason !== null) {
            $this->promotionRejection = $reason;

            return null;
        }

        return $promotion;
    }

    public function create(array $data): Order
    {
        $this->promotionRejection = null;

        return DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = 'DH'.str_pad((string) ((Order::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
            }

            $customer = Customer::find($data['customer_id']);

            [$items, $subtotal] = $this->buildItems($data['items'] ?? []);

            $promotion = $this->applyPromotionConditions(
                $this->resolvePromotion($data),
                $customer,
                $subtotal
            );

            $amounts = $this->calculateAmounts(
                $subtotal,
                $promotion,
                (int) ($data['points_used'] ?? 0),
                (int) ($customer?->points ?? 0)
            );

            // Trừ điểm tích lũy của khách hàng ngay trong cùng transaction.
            if ($customer && $amounts['points_used'] > 0) {
                $customer->deductPoints($amounts['points_used']);
            }

            $order = Order::create(array_merge($data, [
                'promotion_id' => $promotion?->id,
                'items' => null,
            ], $amounts));

            foreach ($items as $item) {
                OrderItem::create(array_merge(['order_id' => $order->id], $item));
            }

            // Ghi nhận lượt sử dụng voucher (chỉ khi voucher còn hiệu lực).
            if ($promotion && $promotion->isValid()) {
                $promotion->markUsed();
            }

            return $order->fresh(['items', 'customer', 'promotion']);
        });
    }

    public function update(Order $order, array $data): Order
    {
        if ($order->isLocked()) {
            throw SettledOrderException::forOrder($order->code);
        }

        $this->promotionRejection = null;

        return DB::transaction(function () use ($order, $data) {
            $customer = $data['customer_id']
                ? Customer::find($data['customer_id'])
                : $order->customer;

            [$items, $subtotal] = $this->buildItems($data['items'] ?? []);

            // Form gửi promotion_id (có thể rỗng) và/hoặc promotion_code.
            $touchesPromotion = array_key_exists('promotion_id', $data) || array_key_exists('promotion_code', $data);
            $previousPromotionId = $order->promotion_id;
            $promotion = $touchesPromotion
                ? $this->resolvePromotion($data)
                : $order->promotion;

            // Chính sách điều kiện voucher (vd first_order_only): bỏ voucher nếu
            // khách không thoả điều kiện, đơn vẫn lưu bình thường.
            $promotion = $this->applyPromotionConditions($promotion, $customer, $subtotal, $order->id);

            // Hoàn lại số điểm đã dùng ở lần lưu trước để tính lại từ đầu.
            $previousPoints = (int) $order->points_used;
            if ($customer && $previousPoints > 0) {
                $customer->addPoints($previousPoints);
            }

            $amounts = $this->calculateAmounts(
                $subtotal,
                $promotion,
                (int) ($data['points_used'] ?? 0),
                (int) ($customer?->points ?? 0)
            );

            if ($customer && $amounts['points_used'] > 0) {
                $customer->deductPoints($amounts['points_used']);
            }

            $order->fill(array_merge($data, [
                'promotion_id' => $promotion?->id,
                'items' => null,
            ], $amounts));
            $order->save();

            $order->items()->delete();
            foreach ($items as $item) {
                OrderItem::create(array_merge(['order_id' => $order->id], $item));
            }

            // Đồng bộ số lần sử dụng voucher khi voucher của đơn thay đổi
            // (bao gồm cả trường hợp bị loại vì không thoả điều kiện).
            if ((int) $previousPromotionId !== (int) ($promotion?->id)) {
                if ($previousPromotionId) {
                    Promotion::find($previousPromotionId)?->markUnused();
                }
                if ($promotion && $promotion->isValid()) {
                    $promotion->markUsed();
                }
            }

            return $order->fresh(['items', 'customer', 'promotion']);
        });
    }

    public function delete(Order $order): bool
    {
        if ($order->isLocked()) {
            throw SettledOrderException::forOrder($order->code);
        }

        return DB::transaction(function () use ($order) {
            // Xóa đơn thì hoàn lại lượt sử dụng voucher đã gắn với đơn.
            if ($order->promotion_id) {
                Promotion::find($order->promotion_id)?->markUnused();
            }

            return $order->delete();
        });
    }

    /**
     * Đổi trạng thái đơn. Đơn đã quyết toán thì không được đi lùi trạng thái
     * vì sẽ làm sai lệch số tiền đã thu.
     */
    public function updateStatus(Order $order, string $status): Order
    {
        if ($order->isLocked()) {
            throw SettledOrderException::forOrder($order->code);
        }

        $order->update(['status' => $status]);

        return $order->fresh();
    }

    public function restore(int $id): ?Order
    {
        $order = Order::onlyTrashed()->find($id);
        if ($order) {
            $order->restore();
        }

        return $order;
    }

    public function getStatusFlow(): array
    {
        return OrderStatus::options();
    }
}
