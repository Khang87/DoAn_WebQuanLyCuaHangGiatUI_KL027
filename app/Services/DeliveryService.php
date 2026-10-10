<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = GiaoNhan::query();

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
            $status = DeliveryStatus::parse($filters['status']);
            $query->where('TrangThai', $status->dbValue());
            if ($status === DeliveryStatus::Picking) {
                $query->where('LoaiGiaoNhan', 'NHAN_DO');
            } elseif ($status === DeliveryStatus::Delivering) {
                $query->where('LoaiGiaoNhan', 'GIAO_DO');
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

    public function find(int $id): ?GiaoNhan
    {
        return GiaoNhan::with(['donHang.khachHang', 'nhanVien'])->find($id);
    }

    public function create(array $data): GiaoNhan
    {
        return DB::transaction(function () use ($data): GiaoNhan {
            $order = DonHang::query()->lockForUpdate()->findOrFail($data['order_id'] ?? null);
            DeliveryRules::assertMutableOrder($order);
            $data = DeliveryRules::validate($data, $order);
            $attributes = $this->mapAttributes($data);
            $this->assertNoConflictingLeg($attributes);

            return GiaoNhan::create($attributes);
        });
    }

    public function update(GiaoNhan $delivery, array $data): GiaoNhan
    {
        return DB::transaction(function () use ($delivery, $data): GiaoNhan {
            $order = DonHang::query()->lockForUpdate()->findOrFail($delivery->DonHangID);
            $current = GiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());
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
            $current->update($attributes);

            return $current->fresh();
        });
    }

    public function delete(GiaoNhan $delivery): bool
    {
        return DB::transaction(function () use ($delivery): bool {
            $order = DonHang::query()->lockForUpdate()->findOrFail($delivery->DonHangID);
            $current = GiaoNhan::query()->lockForUpdate()->findOrFail($delivery->getKey());
            DeliveryRules::assertMutableOrder($order);
            if ($current->DonHangID !== $order->DonHangID || DeliveryStatus::parse($current->TrangThai) === DeliveryStatus::Completed) {
                throw ValidationException::withMessages(['delivery' => 'Không thể xóa phiếu đã hoàn thành hoặc đã thay đổi.']);
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
