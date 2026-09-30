<?php

namespace App\Services;

use App\Models\GiaoNhan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = GiaoNhan::query();

        if (!empty($filters['customer_id'])) {
            $query->where('KhachHangID', $filters['customer_id']);
        }

        if (!empty($filters['method'])) {
            $query->where('HinhThuc', $filters['method']);
        }

        if (!empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (!empty($numericPart)) {
                    $q->where('GiaoNhanID', $numericPart);
                }
                $q->orWhere('MaGiaoNhan', 'LIKE', "%{$search}%")
                    ->orWhere('DiaChi', 'LIKE', "%{$search}%")
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

        return $query->with(['donHang', 'nhanVien'])->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?GiaoNhan
    {
        return GiaoNhan::withTrashed()->with(['donHang', 'nhanVien'])->find($id);
    }

    public function create(array $data): GiaoNhan
    {
        if (empty($data['order_id'])) {
            throw new \InvalidArgumentException('Giao nhận phải gắn với một đơn hàng.');
        }

        if (empty($data['code'])) {
            $data['code'] = 'GH' . str_pad((string) ((GiaoNhan::max('GiaoNhanID') ?? 0) + 1), 4, '0', STR_PAD_LEFT);
        }

        // Validate method
        if (!in_array($data['method'] ?? '', ['nhan_do', 'giao_do'])) {
            throw new \InvalidArgumentException('Phương thức giao nhận không hợp lệ. Chỉ chấp nhận: nhan_do, giao_do');
        }

        return GiaoNhan::create($data);
    }

    public function update(GiaoNhan $delivery, array $data): GiaoNhan
    {
        if (isset($data['method']) && !in_array($data['method'], ['nhan_do', 'giao_do'])) {
            throw new \InvalidArgumentException('Phương thức giao nhận không hợp lệ. Chỉ chấp nhận: nhan_do, giao_do');
        }

        $delivery->update($data);
        return $delivery->fresh();
    }

    public function delete(GiaoNhan $delivery): bool
    {
        return $delivery->delete();
    }

    public function restore(int $id): ?GiaoNhan
    {
        $delivery = GiaoNhan::onlyTrashed()->find($id);
        if ($delivery) {
            $delivery->restore();
        }
        return $delivery;
    }
}
