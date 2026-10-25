<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NhatKyHeThong;
use App\Models\TaiKhoan;
use Illuminate\Http\Request;
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

        if (! empty($filters['table'])) {
            $query->where('BangDuLieu', $filters['table']);
        }

        if (! empty($filters['action'])) {
            $query->where('HanhDong', 'like', '%'.$filters['action'].'%');
        }

        if (! empty($filters['TaiKhoanID'])) {
            $query->where('TaiKhoanID', $filters['TaiKhoanID']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('ThoiGian', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('ThoiGian', '<=', $filters['to']);
        }

        $logs = $query->orderByDesc('ThoiGian')->orderByDesc('NhatKyID')->paginate(30)->withQueryString();

        return view('admin.system_logs.index', [
            'logs' => $logs,
            'targetCodes' => NhatKyHeThong::targetCodes($logs->getCollection()),
            'filters' => $filters,
            'accounts' => TaiKhoan::query()
                ->with(['nhanVien', 'khachHang'])
                ->select(['TaiKhoanID', 'TenDangNhap', 'NhanVienID', 'KhachHangID', 'AvatarURL'])
                ->orderBy('TenDangNhap')
                ->get(),
            'tables' => NhatKyHeThong::query()->select('BangDuLieu')->distinct()->orderBy('BangDuLieu')->pluck('BangDuLieu'),
        ]);
    }
}
