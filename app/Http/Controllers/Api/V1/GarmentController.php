<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RecordStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\GarmentResource;
use App\Models\Garment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GarmentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $paginator = Garment::query()
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%");
            })
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', RecordStatus::parse($request->string('status'))->value),
                fn ($query) => $query->where('status', RecordStatus::Active->value)
            )
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse($request, GarmentResource::collection($paginator), $paginator);
    }

    /**
     * Danh sách nhóm loại đồ lấy từ dữ liệu garments đang hoạt động.
     */
    public function categories(): JsonResponse
    {
        $categories = Garment::whereNotNull('category')
            ->where('status', RecordStatus::Active->value)
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json(['data' => $categories]);
    }
}
