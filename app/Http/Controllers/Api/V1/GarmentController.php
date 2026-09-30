<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RecordStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\DichVuResource;
use App\Models\DichVu;
use App\Models\LoaiDichVu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GarmentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $paginator = DichVu::query()
            ->with('loaiDichVu')
            ->when($request->filled('category_id'), fn ($query) => $query->where('LoaiDichVuID', $request->integer('category_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('TenDichVu', 'like', "%{$search}%");
            })
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('TrangThai', RecordStatus::parse($request->string('status'))->label()),
                fn ($query) => $query->where('TrangThai', RecordStatus::Active->label())
            )
            ->orderBy('TenDichVu')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse($request, DichVuResource::collection($paginator), $paginator);
    }

    /**
     * Danh sách nhóm dịch vụ lấy từ database, không hardcode.
     */
    public function categories(): JsonResponse
    {
        $categories = LoaiDichVu::where('TrangThai', RecordStatus::Active->label())
            ->orderBy('TenLoaiDichVu')
            ->get(['LoaiDichVuID as id', 'TenLoaiDichVu as name']);

        return response()->json(['data' => $categories]);
    }
}
