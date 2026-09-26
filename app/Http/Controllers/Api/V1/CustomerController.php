<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $paginator = Customer::query()
            ->withCount('orders')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->paginatedResponse($request, CustomerResource::collection($paginator), $paginator);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->loadCount('orders');

        return $this->itemResponse(request(), new CustomerResource($customer));
    }
}
