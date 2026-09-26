<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends ApiController
{
    /**
     * Chỉ trả về voucher còn hiệu lực (dùng được cho client).
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = Promotion::query()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', today());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->where(function ($query) {
                $query->whereNull('quantity')->orWhereColumn('used_count', '<', 'quantity');
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            })
            ->orderBy('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse($request, PromotionResource::collection($paginator), $paginator);
    }
}
