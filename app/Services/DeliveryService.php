<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\BookingGiaoNhan;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(private OrderService $orderService) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = GiaoNhan::query();

        if (isset($filters['employee_id'])) {
            $query->where('NhanVienID', $filters['employee_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->whereHas('donHang', fn ($orderQuery) => $orderQuery->where('KhachHangID', $filters['customer_id']));
        }

        if (! empty($filters['method'])) {
            $method = match ($filters['method']) {
                'nhan_do' => 'NHAN_DO',
                'giao_do' => 'GIAO_DO',
                default => $filters['method'],
            };

            $query->where('LoaiGiaoNhan', $method);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'received') {
                $query->whereRaw('1 = 0');
            } else {
                $status = DeliveryStatus::parse($filters['status']);
                $query->where('TrangThai', $status->dbValue());
                if ($status === DeliveryStatus::Picking) {
                    $query->where('LoaiGiaoNhan', 'NHAN_DO');
                } elseif ($status === DeliveryStatus::Delivering) {
                    $query->where('LoaiGiaoNhan', 'GIAO_DO');
                }
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (! empty($numericPart)) {
                    $q->where('GiaoNhanID', $numericPart);
                }
                $q->orWhere('DiaChi', 'LIKE', "%{$search}%")
                    ->orWhere('GhiChu', 'LIKE', "%{$search}%")
                    ->orWhereHas('donHang.khachHang', function ($sub) use ($search) {
                        $sub->where('HoTen', 'LIKE', "%{$search}%")
                            ->orWhere('SoDienThoai', 'LIKE', "%{$search}%");
                    });
            });
        }

        $allowedSorts = ['GiaoNhanID', 'HinhThuc', 'TrangThai', 'ThoiGianDuKien'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'ThoiGianDuKien';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->with([
                'donHang:DonHangID,MaDonHang,KhachHangID',
                'donHang.khachHang:KhachHangID,HoTen,SoDienThoai',
                'nhanVien:NhanVienID,HoTen',
            ])
            ->orderBy($sortBy, $sortOrder)
            ->paginate(10)
            ->withQueryString();
    }

    public function getBookingDocuments(array $filters = []): LengthAwarePaginator
    {
        $query = BookingGiaoNhan::query()->with([
            'booking:BookingID,MaBooking,KhachHangID,TrangThai,HinhThucNhanDo,HinhThucTraDo',
            'booking.khachHang:KhachHangID,HoTen,SoDienThoai',
            'booking.donHangs:DonHangID,MaDonHang,BookingID,KhachHangID',
            'donHang:DonHangID,MaDonHang,KhachHangID',
            'donHang.khachHang:KhachHangID,HoTen,SoDienThoai',
            'nhanVien:NhanVienID,HoTen',
        ]);

        if (isset($filters['employee_id'])) {
            $query->where('NhanVienID', $filters['employee_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->whereHas('booking', fn ($bookingQuery) => $bookingQuery
                ->where('KhachHangID', $filters['customer_id']));
        }

        if (! empty($filters['method'])) {
            $method = match ($filters['method']) {
                'nhan_do' => 'NHAN_DO',
                'giao_do' => 'GIAO_DO',
                default => $filters['method'],
            };
            $query->where('LoaiGiaoNhan', $method);
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'received') {
                $query->where('LoaiGiaoNhan', 'NHAN_DO')->where('TrangThai', 'Đã nhận');
            } else {
                $status = DeliveryStatus::parse($filters['status']);
                $query->where(function ($statusQuery) use ($status): void {
                    $statusQuery->where('TrangThai', $status->dbValue());
                    if ($status === DeliveryStatus::Completed) {
                        $statusQuery->orWhere(function ($receivedQuery): void {
                            $receivedQuery->where('LoaiGiaoNhan', 'NHAN_DO')
                                ->where('TrangThai', 'Đã nhận');
                        });
                    }
                });
                if ($status === DeliveryStatus::Picking) {
                    $query->where('LoaiGiaoNhan', 'NHAN_DO');
                } elseif ($status === DeliveryStatus::Delivering) {
                    $query->where('LoaiGiaoNhan', 'GIAO_DO');
                }
            }
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $numericPart = preg_replace('/[^0-9]/', '', $search);
            $query->where(function ($searchQuery) use ($search, $numericPart): void {
                $searchQuery->where('DiaChi', 'LIKE', "%{$search}%");
                if ($numericPart !== '') {
                    $searchQuery->orWhere('BookingGiaoNhanID', $numericPart)
                        ->orWhere('BookingID', $numericPart)
                        ->orWhere('DonHangID', $numericPart);
                }
                $searchQuery->orWhere('LoaiGiaoNhan', 'LIKE', "%{$search}%")
                    ->orWhereHas('booking', function ($bookingQuery) use ($search): void {
                        $bookingQuery->where('MaBooking', 'LIKE', "%{$search}%")
                            ->orWhereHas('khachHang', fn ($customerQuery) => $customerQuery
                                ->where('HoTen', 'LIKE', "%{$search}%")
                                ->orWhere('SoDienThoai', 'LIKE', "%{$search}%"));
                    })
                    ->orWhereHas('donHang', fn ($orderQuery) => $orderQuery
                        ->where('MaDonHang', 'LIKE', "%{$search}%"));
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? null, ['BookingGiaoNhanID', 'HinhThuc', 'TrangThai', 'ThoiGianDuKien'], true)
            ? $filters['sort_by']
            : 'ThoiGianDuKien';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortBy, $sortOrder)
            ->orderBy('BookingGiaoNhanID', $sortOrder)
            ->paginate(10, ['*'], 'booking_page')
            ->withQueryString();
    }

    public function findBookingDocument(int $id): ?BookingGiaoNhan
    {
        return BookingGiaoNhan::query()
            ->with([
                'booking.khachHang',
                'booking.donHangs',
                'donHang.khachHang',
                'nhanVien',
            ])
            ->find($id);
    }

    public function find(int $id): ?GiaoNhan
    {
        $relations = ['donHang.khachHang', 'nhanVien'];
        if (config('giatui.booking_two_step')) {
            $relations = array_merge($relations, [
                'donHang.booking.kiemKe',
                'donHang.booking.giaoNhanDuKiens',
                'donHang.chiTietDonHangs.dichVu',
                'donHang.chiTietDonHangs.loaiDoGiat',
                'donHang.chiTietDonHangs.donViTinh',
            ]);
        }

        return GiaoNhan::with($relations)->find($id);
    }

    public function create(array $data): GiaoNhan
    {
        return DB::transaction(function () use ($data): GiaoNhan {
            $order = DonHang::query()->lockForUpdate()->findOrFail($data['order_id'] ?? null);
            DeliveryRules::assertMutableOrder($order);

            if ($order->BookingID !== null && strtolower((string) ($data['method'] ?? '')) === 'giao_do') {
                $existingReturns = GiaoNhan::query()
                    ->where('DonHangID', $order->DonHangID)
                    ->where('LoaiGiaoNhan', 'GIAO_DO')
                    ->where('TrangThai', '!=', DeliveryStatus::Cancelled->dbValue())
                    ->lockForUpdate()
                    ->get();

                if ($existingReturns->count() > 1) {
                    throw ValidationException::withMessages([
                        'method' => 'Đơn hàng đã có nhiều phiếu giao còn hiệu lực; cần đối soát trước khi tạo thêm.',
                    ]);
                }

                if ($existingReturns->isNotEmpty()) {
                    $existingReturn = $existingReturns->first();
                    $expectedFulfillment = $order->booking()->value('HinhThucTraDo');
                    if ($existingReturn->HinhThuc !== $expectedFulfillment) {
                        throw ValidationException::withMessages([
                            'method' => 'Phiếu giao trả hiện có không khớp với hình thức đã chốt; cần đối soát trước khi tiếp tục.',
                        ]);
                    }

                    return $existingReturn;
                }
            }

            $data = DeliveryRules::validate($data, $order);
            $attributes = $this->mapAttributes($data);
            if ($order->BookingID !== null && $attributes['LoaiGiaoNhan'] === 'GIAO_DO') {
                $returnPlan = BookingGiaoNhan::query()
                    ->where('BookingID', $order->BookingID)
                    ->where('DonHangID', $order->DonHangID)
                    ->where('LoaiGiaoNhan', 'GIAO_DO')
                    ->lockForUpdate()
                    ->first();

                if ($returnPlan === null || $returnPlan->HinhThuc !== $attributes['HinhThuc']) {
                    throw ValidationException::withMessages([
                        'booking' => 'Không tìm thấy kế hoạch trả đồ hợp lệ đã liên kết với đơn hàng.',
                    ]);
                }

                $attributes['PhiGiaoNhan'] = $returnPlan->PhiGiaoNhan;
            }
            $this->assertNoConflictingLeg($attributes);

            return GiaoNhan::create($attributes);
        });
    }

    public function update(GiaoNhan $delivery, array $data): GiaoNhan
    {
        return DB::transaction(function () use ($delivery, $data): GiaoNhan {
            $order = DonHang::query()->lockForUpdate()->findOrFail($delivery->DonHangID);
            $current = GiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());

            return $this->updateLocked($order, $current, $data);
        });
    }

    public function advanceProgress(GiaoNhan $delivery): GiaoNhan
    {
        return DB::transaction(function () use ($delivery): GiaoNhan {
            $order = DonHang::query()->lockForUpdate()->findOrFail($delivery->DonHangID);
            $current = GiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());
            DeliveryRules::assertMutableOrder($order);
            if ($current->DonHangID !== $order->DonHangID) {
                throw ValidationException::withMessages(['order_id' => 'Phiếu đã thay đổi, vui lòng tải lại.']);
            }

            $currentStatus = DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan);
            $nextStatus = match (true) {
                $currentStatus === DeliveryStatus::Pending && $current->LoaiGiaoNhan === 'NHAN_DO' => DeliveryStatus::Picking,
                $currentStatus === DeliveryStatus::Pending && $current->LoaiGiaoNhan === 'GIAO_DO' && $current->HinhThuc === 'Tại cửa hàng' => DeliveryStatus::Completed,
                $currentStatus === DeliveryStatus::Pending && $current->LoaiGiaoNhan === 'GIAO_DO' => DeliveryStatus::Delivering,
                $currentStatus === DeliveryStatus::Picking && $current->LoaiGiaoNhan === 'NHAN_DO' => DeliveryStatus::Completed,
                $currentStatus === DeliveryStatus::Delivering && $current->LoaiGiaoNhan === 'GIAO_DO' => DeliveryStatus::Completed,
                default => throw ValidationException::withMessages(['status' => 'Phiếu giao nhận không còn tiến độ nào cần xác nhận.']),
            };

            return $this->updateLocked($order, $current, ['status' => $nextStatus->value]);
        });
    }

    public function delete(GiaoNhan $delivery): bool
    {
        return DB::transaction(function () use ($delivery): bool {
            $order = DonHang::query()->lockForUpdate()->findOrFail($delivery->DonHangID);
            $current = GiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());
            DeliveryRules::assertMutableOrder($order);
            if (
                $current->DonHangID !== $order->DonHangID
                || DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan) === DeliveryStatus::Completed
                || ($current->LoaiGiaoNhan === 'GIAO_DO' && DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan) === DeliveryStatus::Cancelled)
            ) {
                throw ValidationException::withMessages([
                    'delivery' => 'Không thể xóa phiếu đã hoàn thành hoặc lần giao đã hủy cần được giữ lại lịch sử.',
                ]);
            }

            return $current->delete();
        });
    }

    private function assertNoConflictingLeg(array $attributes, ?int $exceptId = null): void
    {
        if ($attributes['TrangThai'] === DeliveryStatus::Cancelled->dbValue()) {
            return;
        }
        if (GiaoNhan::query()->where('DonHangID', $attributes['DonHangID'])
            ->where('LoaiGiaoNhan', $attributes['LoaiGiaoNhan'])
            ->where('TrangThai', '!=', DeliveryStatus::Cancelled->dbValue())
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))->exists()) {
            throw ValidationException::withMessages(['method' => 'Đơn hàng đã có phiếu còn hiệu lực cho chặng này.']);
        }
    }

    private function updateLocked(DonHang $order, GiaoNhan $current, array $data): GiaoNhan
    {
        DeliveryRules::assertMutableOrder($order);
        if ($current->DonHangID !== $order->DonHangID) {
            throw ValidationException::withMessages(['order_id' => 'Phiếu đã thay đổi, vui lòng tải lại.']);
        }
        $validated = DeliveryRules::validate($data, $order, $current);
        $this->assertNoConflictingLeg($this->mapAttributes($validated), $current->getKey());
        $attributes = $this->mapAttributes(array_intersect_key($validated, $data));
        if ($current->ThoiGianDuKien?->format('Y-m-d H:i') === ($validated['pickup_date'].' '.$validated['pickup_time'])) {
            unset($attributes['ThoiGianDuKien']);
        }
        $previousStatus = DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan);
        if (
            $previousStatus !== DeliveryStatus::Completed
            && DeliveryStatus::parseForLeg($validated['status'], $current->LoaiGiaoNhan) === DeliveryStatus::Completed
        ) {
            $attributes['ThoiGianThucTe'] = now();
        }
        $current->update($attributes);
        $targetStatus = DeliveryStatus::parseForLeg($current->TrangThai, $current->LoaiGiaoNhan);

        if ($current->LoaiGiaoNhan === 'GIAO_DO' && $previousStatus !== $targetStatus) {
            if ($targetStatus === DeliveryStatus::Delivering) {
                $this->orderService->syncStatusFromDelivery($order, $current, OrderStatus::Delivering);
            } elseif ($targetStatus === DeliveryStatus::Completed) {
                $this->orderService->syncStatusFromDelivery($order, $current, OrderStatus::Delivered);
            }
        }

        return $current->fresh();
    }

    private function mapAttributes(array $data): array
    {
        $attributes = [];

        if (array_key_exists('order_id', $data)) {
            $attributes['DonHangID'] = $data['order_id'];
        }

        if (array_key_exists('employee_id', $data)) {
            $attributes['NhanVienID'] = empty($data['employee_id']) ? null : (int) $data['employee_id'];
        }

        if (array_key_exists('method', $data)) {
            $attributes['LoaiGiaoNhan'] = match ($data['method']) {
                'nhan_do' => 'NHAN_DO',
                'giao_do' => 'GIAO_DO',
                default => throw new \InvalidArgumentException('Phương thức giao nhận không hợp lệ.'),
            };
        }

        if (array_key_exists('address', $data)) {
            $attributes['DiaChi'] = $data['address'];
        }

        if (array_key_exists('fulfillment', $data)) {
            $attributes['HinhThuc'] = $data['fulfillment'];
            if ($data['fulfillment'] === 'Tại cửa hàng') {
                $attributes['DiaChi'] = null;
            }
        }

        if (array_key_exists('pickup_date', $data) && array_key_exists('pickup_time', $data)) {
            $attributes['ThoiGianDuKien'] = empty($data['pickup_date']) ? null : $data['pickup_date'].' '.$data['pickup_time'];
        }

        if (array_key_exists('status', $data)) {
            $attributes['TrangThai'] = DeliveryStatus::parse($data['status'])->dbValue();

        }

        if (array_key_exists('notes', $data)) {
            $attributes['GhiChu'] = $data['notes'];
        }

        return $attributes;
    }
}
