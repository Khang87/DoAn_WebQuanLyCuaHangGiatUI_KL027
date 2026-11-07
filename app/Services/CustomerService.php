<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\DiemTichLuy;
use App\Models\DonHang;
use App\Models\KhachHang;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = KhachHang::query()->with(['diemTichLuy', 'taiKhoan:TaiKhoanID,KhachHangID,AvatarURL']);

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
     *     orders: list<array{id: int, code: string, status: string, payment_status: string}>,
     *     accounts: list<array{id: int, email: string, username: string, provider: string}>,
     *     archived?: bool
     * }|null
     */
    public function delete(KhachHang $customer): ?array
    {
        return DB::transaction(function () use ($customer): ?array {
            $lockedCustomer = KhachHang::query()->lockForUpdate()->findOrFail($customer->getKey());
            $orders = DonHang::query()
                ->where('KhachHangID', $lockedCustomer->getKey())
                ->with(['hoaDons', 'statusChanges'])
                ->orderBy('MaDonHang')
                ->lockForUpdate()
                ->get()
                ->map(function (DonHang $order): array {
                    $status = $order->operationalStatusEnum()->value;
                    $paidInvoice = $order->hoaDons->first(
                        fn ($invoice): bool => $invoice->TrangThai === InvoiceStatus::Paid->value,
                    );
                    $invoiceStatus = $paidInvoice?->TrangThai ?? $order->hoaDons->first()?->TrangThai;
                    $paymentStatus = $invoiceStatus ?? InvoiceStatus::Unpaid->value;

                    return [
                        'id' => (int) $order->getKey(),
                        'code' => (string) $order->getRawOriginal('MaDonHang'),
                        'status' => $status,
                        'payment_status' => (string) $paymentStatus,
                    ];
                })
                ->all();
            $blockingOrders = array_values(array_filter(
                $orders,
                fn (array $order): bool => ! in_array($order['status'], [
                    OrderStatus::Delivered->value,
                ], true) || $order['payment_status'] !== InvoiceStatus::Paid->value,
            ));

            $accounts = DB::table('TaiKhoan')
                ->where('KhachHangID', $lockedCustomer->getKey())
                ->orderBy('TaiKhoanID')
                ->get(['TaiKhoanID', 'Email', 'TenDangNhap', 'UserAuthId'])
                ->map(fn (object $account): array => [
                    'id' => (int) $account->TaiKhoanID,
                    'email' => (string) ($account->Email ?? ''),
                    'username' => (string) ($account->TenDangNhap ?? ''),
                    'provider' => filled($account->UserAuthId) ? 'Google' : 'Email/Mật khẩu',
                ])
                ->all();

            $hasRelatedRecords = $lockedCustomer->donHangs()->exists()
                || $lockedCustomer->bookings()->exists()
                || $lockedCustomer->danhGia()->exists()
                || $accounts !== [];

            if ($blockingOrders !== []) {
                return [
                    'message' => 'Không thể lưu trữ khách hàng vì còn đơn hàng chưa giao hoặc chưa thanh toán.',
                    'orders' => $blockingOrders,
                    'accounts' => $accounts,
                ];
            }

            if ($hasRelatedRecords) {
                $updated = KhachHang::query()
                    ->whereKey($lockedCustomer->getKey())
                    ->update(['TrangThai' => 'Ngừng hoạt động']);

                if ($updated !== 1) {
                    throw new \RuntimeException('Không thể lưu trữ hồ sơ khách hàng. Trạng thái không được cập nhật.');
                }

                return [
                    'message' => 'Hồ sơ khách hàng đã được lưu trữ ở trạng thái Tạm ngưng. Lịch sử và dữ liệu liên quan được giữ nguyên.',
                    'orders' => [],
                    'accounts' => [],
                    'archived' => true,
                ];
            }

            DiemTichLuy::query()
                ->where('KhachHangID', $lockedCustomer->getKey())
                ->delete();

            if (! $lockedCustomer->delete()) {
                return [
                    'message' => 'Không thể xóa khách hàng. Vui lòng thử lại.',
                    'orders' => [],
                    'accounts' => [],
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
