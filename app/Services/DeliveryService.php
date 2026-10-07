<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\GiaoNhan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
            $query->where('TrangThai', DeliveryStatus::parse($filters['status'])->dbValue());
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
        if (empty($data['order_id'])) {
            throw new \InvalidArgumentException('Giao nhận phải gắn với một đơn hàng.');
        }

        return GiaoNhan::create($this->mapAttributes($data));
    }

    public function update(GiaoNhan $delivery, array $data): GiaoNhan
    {
        $delivery->update($this->mapAttributes($data));

        return $delivery->fresh();
    }

    public function delete(GiaoNhan $delivery): bool
    {
        return $delivery->delete();
    }

    private function mapAttributes(array $data): array
    {
        $attributes = [];

        if (array_key_exists('order_id', $data)) {
            $attributes['DonHangID'] = $data['order_id'];
        }

        if (array_key_exists('employee_id', $data)) {
            $attributes['NhanVienID'] = $data['employee_id'];
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
            $attributes['HinhThuc'] = $data['fulfillment'] ?? 'Tại nhà';
        }

        if (array_key_exists('fulfillment', $data)) {
            $attributes['HinhThuc'] = $data['fulfillment'];
            if ($data['fulfillment'] === 'Tại cửa hàng') {
                $attributes['DiaChi'] = null;
            }
        }

        if (array_key_exists('pickup_date', $data) && array_key_exists('pickup_time', $data)) {
            $attributes['ThoiGianDuKien'] = $data['pickup_date'].' '.$data['pickup_time'];
        }

        if (array_key_exists('status', $data)) {
            $attributes['TrangThai'] = DeliveryStatus::parse($data['status'])->dbValue();
        } elseif (! empty($data['order_id'])) {
            $attributes['TrangThai'] = DeliveryStatus::Pending->dbValue();
        }

        if (array_key_exists('notes', $data)) {
            $attributes['GhiChu'] = $data['notes'];
        }

        return $attributes;
    }
}
