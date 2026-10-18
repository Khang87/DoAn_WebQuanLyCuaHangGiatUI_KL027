<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\DiemTichLuy;
use App\Models\KhachHang;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
            'id_asc' => ['KhachHangID', 'asc'],
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

    /**
     * @return array{
     *     message: string,
     *     orders: list<array{id: int, code: string, status: string, payment_status: string}>
     * }|null
     */
    public function delete(KhachHang $customer): ?array
    {
        return DB::transaction(function () use ($customer): ?array {
            $orders = DB::table('DonHang')
                ->leftJoin('HoaDon', 'HoaDon.DonHangID', '=', 'DonHang.DonHangID')
                ->where('DonHang.KhachHangID', $customer->getKey())
                ->orderBy('DonHang.MaDonHang')
                ->get([
                    'DonHang.DonHangID',
                    'DonHang.MaDonHang',
                    'DonHang.TrangThai',
                    'HoaDon.TrangThai as invoice_status',
                ])
                ->map(fn (object $order): array => [
                    'id' => (int) $order->DonHangID,
                    'code' => (string) $order->MaDonHang,
                    'status' => (string) $order->TrangThai,
                    'payment_status' => (string) ($order->invoice_status ?? InvoiceStatus::Unpaid->value),
                ])
                ->all();

            $relatedRecords = array_filter([
                'đơn hàng' => count($orders),
                'lịch hẹn' => $customer->bookings()->count(),
                'đánh giá' => $customer->danhGia()->count(),
                'tài khoản' => $customer->taiKhoan()->count(),
            ]);

            if ($relatedRecords !== []) {
                $details = [];

                foreach ($relatedRecords as $recordType => $count) {
                    $details[] = $count.' '.$recordType;
                }

                return [
                    'message' => 'Không thể xóa khách hàng vì còn dữ liệu liên quan: '.implode(', ', $details).'.',
                    'orders' => $orders,
                ];
            }

            DiemTichLuy::query()
                ->where('KhachHangID', $customer->getKey())
                ->delete();

            if (! $customer->delete()) {
                return [
                    'message' => 'Không thể xóa khách hàng. Vui lòng thử lại.',
                    'orders' => [],
                ];
            }

            return null;
        });
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
