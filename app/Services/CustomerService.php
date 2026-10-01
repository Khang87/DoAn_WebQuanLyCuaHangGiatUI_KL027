<?php

namespace App\Services;

use App\Models\DiemTichLuy;
use App\Models\KhachHang;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = KhachHang::query()->with('diemTichLuy');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($customerQuery) use ($search) {
                $customerQuery->where('HoTen', 'like', "%{$search}%")
                    ->orWhere('SoDienThoai', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $customerQuery->orWhere('KhachHangID', (int) $search);
                }
            });
        }

        $sortMap = [
            'latest' => ['NgayTao', 'desc'],
            'oldest' => ['NgayTao', 'asc'],
            'name_asc' => ['HoTen', 'asc'],
            'name_desc' => ['HoTen', 'desc'],
        ];
        $sort = $filters['sort'] ?? 'latest';

        if (in_array($sort, ['points_desc', 'points_asc'])) {
            $direction = $sort === 'points_desc' ? 'desc' : 'asc';
            $query->leftJoin('DiemTichLuy', 'DiemTichLuy.KhachHangID', '=', 'KhachHang.KhachHangID')
                ->select('KhachHang.*')
                ->orderByRaw('COALESCE("DiemTichLuy"."DiemHienTai", 0) '.$direction)
                ->orderBy('KhachHang.KhachHangID');
        } else {
            [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['NgayTao', 'desc'];
            $query->orderBy($sortBy, $sortOrder)
                ->orderBy('KhachHang.KhachHangID');
        }

        return $query->paginate(10)->withQueryString();
    }

    public function find(int $id): ?KhachHang
    {
        return KhachHang::find($id);
    }

    public function findByCode(string $code): ?KhachHang
    {
        return KhachHang::where('MaKhachHang', $code)->first();
    }

    public function create(array $data): KhachHang
    {
        if (empty($data['MaKhachHang'])) {
            $data['MaKhachHang'] = 'KH'.str_pad((string) ((KhachHang::max('KhachHangID') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
        }
        $points = ! empty($data['DiemHienTai']) ? $data['DiemHienTai'] : 0;
        unset($data['DiemHienTai']);
        $customer = KhachHang::create($data);
        DiemTichLuy::create([
            'KhachHangID' => $customer->KhachHangID,
            'DiemHienTai' => $points,
        ]);

        return $customer->fresh();
    }

    public function update(KhachHang $customer, array $data): KhachHang
    {
        if (isset($data['DiemHienTai'])) {
            $points = $data['DiemHienTai'];
            unset($data['DiemHienTai']);
        }
        $customer->update($data);
        if (isset($points)) {
            DiemTichLuy::updateOrCreate(
                ['KhachHangID' => $customer->KhachHangID],
                ['DiemHienTai' => $points, 'NgayCapNhat' => now()]
            );
        }

        return $customer->fresh();
    }

    public function delete(KhachHang $customer): bool
    {
        if (
            $customer->donHangs()->exists()
            || $customer->bookings()->exists()
            || $customer->danhGia()->exists()
            || $customer->diemTichLuy()->exists()
            || $customer->taiKhoan()->exists()
        ) {
            $customer->update(['TrangThai' => 'Ngừng hoạt động']);

            return false;
        }

        return $customer->delete();
    }

    public function restore(int $id): ?KhachHang
    {
        return KhachHang::find($id);
    }

    public function getTotalSpent(KhachHang $customer): float
    {
        return (float) $customer->donHangs()->sum('ThanhTien');
    }

    public function getOrderCount(KhachHang $customer): int
    {
        return (int) $customer->donHangs()->count();
    }

    public function deductPoints(KhachHang $customer, int $points): bool
    {
        if ($customer->points >= $points) {
            $customer->deductPoints($points);

            return true;
        }

        return false;
    }

    public function addPoints(KhachHang $customer, int $points): void
    {
        $customer->addPoints($points);
    }
}
