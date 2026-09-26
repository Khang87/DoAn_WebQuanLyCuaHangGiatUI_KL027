<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RecordStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $paginator = Service::query()
            ->with('category')
            ->when($request->filled('category_id'), fn ($query) => $query->where('service_category_id', $request->integer('category_id')))
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

        return $this->paginatedResponse($request, ServiceResource::collection($paginator), $paginator);
    }

    /**
     * Nhóm dịch vụ lấy từ database, không hardcode.
     */
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::where('status', RecordStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['data' => $categories]);
    }
}
