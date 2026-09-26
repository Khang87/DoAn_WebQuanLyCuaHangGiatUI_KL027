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
     * Quy Ä‘á»•i 1 Ä‘iá»ƒm tÃ­ch lÅ©y thÃ nh tiá»n.
     */
    public const POINT_VALUE = 1000;

    /**
     * LÃ½ do voucher cuá»‘i cÃ¹ng bá»‹ loáº¡i trong láº§n gá»i create()/update() gáº§n nháº¥t.
     */
    private ?string $promotionRejection = null;

    /**
     * LÃ½ do voucher bá»‹ loáº¡i á»Ÿ láº§n xá»­ lÃ½ gáº§n nháº¥t (null nghÄ©a lÃ  khÃ´ng bá»‹ loáº¡i).
     */
    public function promotionRejection(): ?string
    {
        return $this->promotionRejection;
    }

    /**
     * Chuáº©n hÃ³a bá»™ sá»‘ tiá»n cá»§a Ä‘Æ¡n hÃ ng.
     *
     *   Táº¡m tÃ­nh            = Î£ (sá»‘ lÆ°á»£ng hoáº·c kg Ã— Ä‘Æ¡n giÃ¡ lá»‹ch sá»­ theo price_lists)
     *   Tiá»n giáº£m voucher   = Promotion::calculateDiscount(Táº¡m tÃ­nh)
     *   Tiá»n giáº£m do Ä‘iá»ƒm   = sá»‘ Ä‘iá»ƒm dÃ¹ng Ã— POINT_VALUE
     *   Tá»•ng thanh toÃ¡n     = Táº¡m tÃ­nh - giáº£m voucher - giáº£m Ä‘iá»ƒm (khÃ´ng nhá» hÆ¡n 0)
     *
     * Má»—i khoáº£n giáº£m Ä‘Æ°á»£c cháº·n tá»‘i Ä‘a báº±ng sá»‘ tiá»n cÃ²n láº¡i Ä‘á»ƒ tá»•ng khÃ´ng Ã¢m.
     *
     * @return array{subtotal: float, discount_by_promotion: float, points_used: int, discount_by_points: float, total_amount: float}
     */
    public function calculateAmounts(float $subtotal, ?Promotion $promotion, int $pointsUsed, int $customerPoints): array
    {
        $subtotal = max(0, $subtotal);

        $discountByPromotion = $promotion
            ? min($promotion->calculateDiscount($subtotal), $subtotal)
            : 0.0;

        // KhÃ´ng cho dÃ¹ng vÆ°á»£t sá»‘ Ä‘iá»ƒm khÃ¡ch Ä‘ang cÃ³.
        $pointsUsed = max(0, min($pointsUsed, $customerPoints));

        $remaining = max(0, $subtotal - $discountByPromotion);
        $discountByPoints = min($pointsUsed * self::POINT_VALUE, $remaining);

        // Sá»‘ Ä‘iá»ƒm thá»±c sá»± quy Ä‘á»•i Ä‘Æ°á»£c thÃ nh tiá»n (trÃ¡nh ghi Ä‘iá»ƒm "lÃ£ng phÃ­").
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
     * Táº¡o cÃ¡c dÃ²ng máº·t hÃ ng tá»« dá»¯ liá»‡u form, chá»‘t Ä‘Æ¡n giÃ¡ lá»‹ch sá»­ vÃ  tÃ­nh táº¡m tÃ­nh.
     *
     * ÄÆ¡n vá»‹ tÃ­nh tiá»n láº¥y tá»« price_lists theo cáº·p service_id + garment_id:
     *   - Ä‘Æ¡n vá»‹ "kg"  â†’ Táº¡m tÃ­nh = Khá»‘i lÆ°á»£ng Ã— ÄÆ¡n giÃ¡
     *   - Ä‘Æ¡n vá»‹ khÃ¡c  â†’ Táº¡m tÃ­nh = Sá»‘ lÆ°á»£ng   Ã— ÄÆ¡n giÃ¡
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

            // GiÃ¡ lá»‹ch sá»­ má»›i nháº¥t theo cáº·p service_id + garment_id.
            $pricing = ($serviceId && $garmentId)
                ? Pricing::getLatestPricing((int) $serviceId, (int) $garmentId)
                : null;

            // Æ¯u tiÃªn giÃ¡ gá»­i lÃªn; náº¿u thiáº¿u thÃ¬ láº¥y giÃ¡ lá»‹ch sá»­ tá»« báº£ng price_lists.
            $price = $item['price'] ?? null;
            if (($price === null || $price === '') && $pricing) {
                $price = $pricing->price;
            }

            $price = (float) ($price ?? 0);
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $weight = max(0, (float) ($item['weight'] ?? 0));

            // Dá»‹ch vá»¥ tÃ­nh theo kg dÃ¹ng khá»‘i lÆ°á»£ng lÃ m Ä‘Æ¡n vá»‹ nhÃ¢n vá»›i Ä‘Æ¡n giÃ¡.
            $isWeightUnit = Pricing::isWeightUnit($pricing?->unit);
            $multiplier = $isWeightUnit ? $weight : $quantity;
            $lineSubtotal = round($price * $multiplier, 2);

            $rows[] = [
                'service_id' => $serviceId ?: null,
                'garment_id' => $garmentId ?: null,
                // order_items.item_name lÃ  NOT NULL nhÆ°ng form chá»‰ gá»­i service/garment
                // nÃªn tÃªn máº·t hÃ ng Ä‘Æ°á»£c suy ra tá»« dá»‹ch vá»¥/loáº¡i Ä‘á»“ tÆ°Æ¡ng á»©ng.
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
     * Suy ra tÃªn máº·t hÃ ng khi form khÃ´ng gá»­i item_name.
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

        return 'Máº·t hÃ ng';
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

        $sortMap = [
            'latest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'code_asc' => ['code', 'asc'],
            'code_desc' => ['code', 'desc'],
            'total_desc' => ['total_amount', 'desc'],
            'total_asc' => ['total_amount', 'asc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['created_at', 'desc'];

        return $query->with(['customer', 'employee', 'items.service', 'items.garment', 'promotion', 'invoice', 'payments', 'booking'])
            ->orderBy($sortBy, $sortOrder)
            ->paginate(10);
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
     * XÃ¡c Ä‘á»‹nh voucher tá»« form: Æ°u tiÃªn promotion_id, náº¿u rá»—ng thÃ¬ tra theo mÃ£ code.
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
     * Loáº¡i voucher náº¿u Ä‘iá»u kiá»‡n nghiá»‡p vá»¥ khÃ´ng cho phÃ©p khÃ¡ch dÃ¹ng.
     *
     * ChÃ­nh sÃ¡ch: Ä‘Æ¡n váº«n Ä‘Æ°á»£c lÆ°u nhÆ°ng KHÃ”NG Ã¡p dá»¥ng giáº£m giÃ¡, Ä‘á»“ng thá»i ghi
     * nháº­n lÃ½ do Ä‘á»ƒ controller hiá»ƒn thá»‹ cáº£nh bÃ¡o cho ngÆ°á»i dÃ¹ng.
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

            // Trá»« Ä‘iá»ƒm tÃ­ch lÅ©y cá»§a khÃ¡ch hÃ ng ngay trong cÃ¹ng transaction.
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

            // Ghi nháº­n lÆ°á»£t sá»­ dá»¥ng voucher (chá»‰ khi voucher cÃ²n hiá»‡u lá»±c).
            if ($promotion && $promotion->isValid()) {
                $promotion->markUsed();
            }

            return $order->fresh(['items', 'customer', 'promotion']);
        });
    }

    public function update(Order $order, array $data, bool $overrideSettled = false): Order
    {
        if ($order->isLocked() && ! $overrideSettled) {
            throw SettledOrderException::forOrder($order->code);
        }

        $this->promotionRejection = null;

        return DB::transaction(function () use ($order, $data) {
            $customer = $data['customer_id']
                ? Customer::find($data['customer_id'])
                : $order->customer;

            [$items, $subtotal] = $this->buildItems($data['items'] ?? []);

            // Form gá»­i promotion_id (cÃ³ thá»ƒ rá»—ng) vÃ /hoáº·c promotion_code.
            $touchesPromotion = array_key_exists('promotion_id', $data) || array_key_exists('promotion_code', $data);
            $previousPromotionId = $order->promotion_id;
            $promotion = $touchesPromotion
                ? $this->resolvePromotion($data)
                : $order->promotion;

            // ChÃ­nh sÃ¡ch Ä‘iá»u kiá»‡n voucher (vd first_order_only): bá» voucher náº¿u
            // khÃ¡ch khÃ´ng thoáº£ Ä‘iá»u kiá»‡n, Ä‘Æ¡n váº«n lÆ°u bÃ¬nh thÆ°á»ng.
            $promotion = $this->applyPromotionConditions($promotion, $customer, $subtotal, $order->id);

            // HoÃ n láº¡i sá»‘ Ä‘iá»ƒm Ä‘Ã£ dÃ¹ng á»Ÿ láº§n lÆ°u trÆ°á»›c Ä‘á»ƒ tÃ­nh láº¡i tá»« Ä‘áº§u.
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

            // Äá»“ng bá»™ sá»‘ láº§n sá»­ dá»¥ng voucher khi voucher cá»§a Ä‘Æ¡n thay Ä‘á»•i
            // (bao gá»“m cáº£ trÆ°á»ng há»£p bá»‹ loáº¡i vÃ¬ khÃ´ng thoáº£ Ä‘iá»u kiá»‡n).
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

    public function delete(Order $order, bool $overrideSettled = false): bool
    {
        if ($order->isLocked() && ! $overrideSettled) {
            throw SettledOrderException::forOrder($order->code);
        }

        return DB::transaction(function () use ($order) {
            // XÃ³a Ä‘Æ¡n thÃ¬ hoÃ n láº¡i lÆ°á»£t sá»­ dá»¥ng voucher Ä‘Ã£ gáº¯n vá»›i Ä‘Æ¡n.
            if ($order->promotion_id) {
                Promotion::find($order->promotion_id)?->markUnused();
            }

            return $order->delete();
        });
    }

    /**
     * Äá»•i tráº¡ng thÃ¡i Ä‘Æ¡n. ÄÆ¡n Ä‘Ã£ quyáº¿t toÃ¡n thÃ¬ khÃ´ng Ä‘Æ°á»£c Ä‘i lÃ¹i tráº¡ng thÃ¡i
     * vÃ¬ sáº½ lÃ m sai lá»‡ch sá»‘ tiá»n Ä‘Ã£ thu.
     */
    public function updateStatus(Order $order, string $status, bool $overrideSettled = false): Order
    {
        if ($order->isLocked() && ! $overrideSettled) {
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
