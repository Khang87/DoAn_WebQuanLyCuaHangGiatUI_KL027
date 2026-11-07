<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NhatKyHeThong;
use App\Models\TaiKhoan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SystemLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isOwner(), 403);

        $filters = $request->validate([
            'table' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:255'],
            'TaiKhoanID' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $query = NhatKyHeThong::query()->with(['taiKhoan.nhanVien', 'taiKhoan.khachHang']);
        $query->whereNot(function ($emptyAccountDeletion): void {
            $emptyAccountDeletion
                ->where('BangDuLieu', 'TaiKhoan')
                ->where('HanhDong', 'Xóa tài khoản')
                ->whereRaw('CAST("DuLieuCu" AS TEXT) = ?', ['[]'])
                ->whereRaw('CAST("DuLieuMoi" AS TEXT) = ?', ['[]']);
        });

        if (! empty($filters['table'])) {
            $query->where('BangDuLieu', $filters['table']);
        }

        if (! empty($filters['action'])) {
            $query->where('HanhDong', 'like', '%'.$filters['action'].'%');
        }

        if (! empty($filters['TaiKhoanID'])) {
            $query->where('TaiKhoanID', $filters['TaiKhoanID']);
        }

        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $timezone = config('app.timezone');
            $localFrom = ! empty($filters['from'])
                ? Carbon::parse($filters['from'], $timezone)->startOfDay()
                : null;
            $localUntil = ! empty($filters['to'])
                ? Carbon::parse($filters['to'], $timezone)->addDay()->startOfDay()
                : null;
            $utcFrom = $localFrom?->copy()->utc();
            $utcUntil = $localUntil?->copy()->utc();

            $query->where(function ($range) use ($localFrom, $localUntil, $utcFrom, $utcUntil): void {
                $range->where(function ($utc) use ($utcFrom, $utcUntil): void {
                    $utc->whereIn('HanhDong', NhatKyHeThong::UTC_TRIGGER_ACTIONS)
                        ->whereNull('TaiKhoanID');
                    if ($utcFrom !== null) {
                        $utc->where('ThoiGian', '>=', $utcFrom);
                    }
                    if ($utcUntil !== null) {
                        $utc->where('ThoiGian', '<', $utcUntil);
                    }
                })->orWhere(function ($local) use ($localFrom, $localUntil): void {
                    $local->where(function ($source): void {
                        $source->whereNotIn('HanhDong', NhatKyHeThong::UTC_TRIGGER_ACTIONS)
                            ->orWhereNotNull('TaiKhoanID');
                    });
                    if ($localFrom !== null) {
                        $local->where('ThoiGian', '>=', $localFrom);
                    }
                    if ($localUntil !== null) {
                        $local->where('ThoiGian', '<', $localUntil);
                    }
                });
            });
        }

        $logs = $query->orderByDesc('ThoiGian')->orderByDesc('NhatKyID')->paginate(30)->withQueryString();

        return view('admin.system_logs.index', [
            'logs' => $logs,
            'targetCodes' => NhatKyHeThong::targetCodes($logs->getCollection()),
            'filters' => $filters,
            'accounts' => TaiKhoan::query()
                ->with(['nhanVien', 'khachHang'])
                ->select(['TaiKhoanID', 'Email', 'SoDienThoai', 'NhanVienID', 'KhachHangID'])
                ->orderBy('TaiKhoanID')
                ->get(),
            'tables' => NhatKyHeThong::query()->select('BangDuLieu')->distinct()->orderBy('BangDuLieu')->pluck('BangDuLieu'),
        ]);
    }
}
