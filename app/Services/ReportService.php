<?php

namespace App\Services;

use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReportService
{
    public function getRevenueData(string $period = 'month'): array
    {
        $format = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%W',
            'year' => '%Y',
            default => '%Y-%m',
        };

        // `strftime` là hàm của SQLite; PostgreSQL cần `to_char`. Cột `NgayTao`
        // cũng phải có nháy kép vì đây là raw SQL.
        $format = match ($period) {
            'day' => 'DD',
            'week' => 'WW',
            'year' => 'YYYY',
            default => 'YYYY-MM',
        };

        $query = DonHang::selectRaw("SUM(\"ThanhTien\") as total, to_char(\"NgayTao\", '{$format}') as period")
            ->groupBy('period');

        return $query->orderBy('period')->get()->map(fn ($item) => [
            'period' => $item->period,
            'total' => (float) $item->total,
        ])->toArray();
    }

    public function getOrderStatusCounts(): array
    {
        // `TrangThai` phải được đặt trong nháy kép: raw SQL không đi qua wrapper
        // của Eloquent nên PostgreSQL sẽ hạ `TrangThai` thành `trangthai`.
        return DonHang::selectRaw('"TrangThai", COUNT(*) as count')
            ->groupBy('TrangThai')
            ->pluck('count', 'TrangThai')
            ->toArray();
    }

    public function getTopCustomers(int $limit = 10): Collection
    {
        return DonHang::selectRaw('"KhachHangID", SUM("ThanhTien") as total_spent, COUNT(*) as order_count')
            ->where('TrangThai', '!=', 'cancelled')
            ->groupBy('KhachHangID')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->with('customer')
            ->get()
            ->filter(fn ($item) => $item->customer !== null);
    }

    public function getOrderCountsByService(): array
    {
        // Dịch vụ nằm ở bảng chi tiết đơn (`ChiTietDonHang.DichVuID`), không có
        // trong `DonHang`.
        return ChiTietDonHang::selectRaw('"DichVuID", COUNT(*) as count')
            ->groupBy('DichVuID')
            ->pluck('count', 'DichVuID')
            ->toArray();
    }

    public function getRevenueSummary(): array
    {
        return [
            'total_revenue' => (float) DonHang::where('TrangThai', '!=', 'cancelled')->sum('ThanhTien'),
            'total_orders' => DonHang::count(),
            'completed_orders' => DonHang::where('TrangThai', 'completed')->count(),
            'cancelled_orders' => DonHang::where('TrangThai', 'cancelled')->count(),
            'pending_orders' => DonHang::where('TrangThai', 'pending')->count(),
            'processing_orders' => DonHang::where('TrangThai', 'processing')->count(),
            'today_revenue' => (float) DonHang::whereDate('NgayTao', today())->sum('ThanhTien'),
            'month_revenue' => (float) DonHang::whereMonth('NgayTao', now()->month)->sum('ThanhTien'),
        ];
    }

    public function getMonthlyRevenue(): array
    {
        return collect(range(1, 12))->map(function ($month) {
            $revenue = DonHang::whereMonth('NgayTao', $month)
                ->where('TrangThai', '!=', 'cancelled')
                ->sum('ThanhTien');

            return round($revenue / 1000000, 2);
        })->values()->toArray();
    }
}
