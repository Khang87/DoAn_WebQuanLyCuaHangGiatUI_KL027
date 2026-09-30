<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CustomerResource;
use App\Models\KhachHang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
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

        return $this->paginatedResponse($request, CustomerResource::collection($paginator), $paginator);
    }

    public function show(KhachHang $customer): JsonResponse
    {
        $customer->loadCount('donHangs');

        return $this->itemResponse(request(), new CustomerResource($customer));
    }
}
