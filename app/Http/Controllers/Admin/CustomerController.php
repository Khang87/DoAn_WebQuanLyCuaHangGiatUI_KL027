<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
    ) {}

    public function index(Request $request)
    {
        $customers = $this->customerService->getAll([
            'search' => $request->input('search'),
            'type' => $request->input('type'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        return view('admin.customers.index', compact('customers'));
    }

    public function create()
    {
        return view('admin.customers.create');
    }

    public function store(CustomerRequest $request)
    {
        try {
            $customer = $this->customerService->create($request->validated());

            return redirect()->route('customers.index')->with('success', 'Khách hàng đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('customers.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $customer = $this->customerService->find($id);

        if (!$customer) {
            abort(404);
        }

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $customer->orders()->with('service')->latest()->paginate(10),
            'totalSpent' => $this->customerService->getTotalSpent($customer),
            'orderCount' => $this->customerService->getOrderCount($customer),
        ]);
    }

    public function edit(int $id)
    {
        $customer = $this->customerService->find($id);

        if (!$customer) {
            abort(404);
        }

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(CustomerRequest $request, int $id)
    {
        $customer = $this->customerService->find($id);

        if (!$customer) {
            abort(404);
        }

        try {
            $this->customerService->update($customer, $request->validated());

            return redirect()->route('customers.index')->with('success', 'Khách hàng đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('customers.edit', $customer)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $customer = $this->customerService->find($id);

        if (!$customer) {
            abort(404);
        }

        try {
            $this->customerService->delete($customer);

            return redirect()->route('customers.index')->with('success', 'Khách hàng đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('customers.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
