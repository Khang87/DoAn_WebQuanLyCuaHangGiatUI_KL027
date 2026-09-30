<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\KhachHangResource;
use App\Models\KhachHang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KhachHangController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('customers.view');

        $paginator = KhachHang::query()
            ->withCount('donHangs')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('HoTen', 'like', "%{$search}%")
                    ->orWhere('SoDienThoai', 'like', "%{$search}%")
                    ->orWhere('Email', 'like', "%{$search}%");
            })
            ->orderBy('HoTen')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse($request, KhachHangResource::collection($paginator), $paginator);
    }

    public function show(KhachHang $customer): JsonResponse
    {
        Gate::authorize('customers.view');

        $customer->loadCount('donHangs');

        return $this->itemResponse(request(), new KhachHangResource($customer));
    }
}
