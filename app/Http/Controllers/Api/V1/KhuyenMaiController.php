<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\KhuyenMaiResource;
use App\Models\KhuyenMai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KhuyenMaiController extends ApiController
{
    /**
     * Chỉ trả về voucher còn hiệu lực (dùng được cho client).
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = KhuyenMai::query()
            ->withCount(['donHangs as used_count'])
            ->where('TrangThai', 'Hoạt động')
            ->where(function ($query) {
                $query->whereNull('NgayBatDau')->orWhereDate('NgayBatDau', '<=', today());
            })
            ->where(function ($query) {
                $query->whereNull('NgayKetThuc')->orWhereDate('NgayKetThuc', '>=', today());
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('MaKhuyenMai', 'like', "%{$search}%")
                    ->orWhere('TenKhuyenMai', 'like', "%{$search}%");
            })
            ->orderBy('KhuyenMaiID')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse($request, KhuyenMaiResource::collection($paginator), $paginator);
    }
}
