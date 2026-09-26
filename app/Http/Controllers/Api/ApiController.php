<?php

namespace App\Http\Controllers\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

abstract class ApiController
{
    /**
     * Số trang mặc định và tối đa cho các endpoint dạng index.
     */
    protected const DEFAULT_PER_PAGE = 15;
    protected const MAX_PER_PAGE = 100;

    /**
     * Cùng một cấu trúc cho mọi endpoint index: data + meta phân trang.
     */
    protected function paginatedResponse(Request $request, AnonymousResourceCollection $collection, LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $collection->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Cùng một cấu trúc cho mọi endpoint show / action.
     */
    protected function itemResponse(Request $request, mixed $resource): JsonResponse
    {
        return response()->json([
            'data' => $resource->resolve($request),
        ]);
    }

    /**
     * Cùng một cấu trúc cho mọi lỗi nghiệp vụ.
     */
    protected function errorResponse(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    protected function perPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }
}
