<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuKhachHangRequest;
use App\Services\CustomerService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;

class KhachHangController extends Controller
{
    public function __construct(
        private CustomerService $customerService,
    ) {}

    public function index(Request $request)
    {
        $customers = $this->customerService->getAll([
            'search' => $request->input('search'),
            'sort' => $request->input('sort'),
        ]);

        $currentSort = $request->input('sort', 'latest');

        return view('admin.customers.index', compact('customers', 'currentSort'));
    }

    public function create()
    {
        return view('admin.customers.create');
    }

    public function store(LuuKhachHangRequest $request)
    {
        try {
            $customer = $this->customerService->create($request->validated());

            return redirect()->route('customers.index')->with('success', 'Khách hàng đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('customers.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $customer = $this->customerService->find($id);

        if (! $customer) {
            abort(404);
        }

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $customer->donHangs()
                ->with(['chiTietDonHangs.dichVu'])
                ->orderByDesc('NgayTao')
                ->paginate(10)
                ->withQueryString(),
            'totalSpent' => $this->customerService->getTotalSpent($customer),
            'orderCount' => $this->customerService->getOrderCount($customer),
        ]);
    }

    public function edit(int $id)
    {
        $customer = $this->customerService->find($id);

        if (! $customer) {
            abort(404);
        }

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(LuuKhachHangRequest $request, int $id)
    {
        $customer = $this->customerService->find($id);

        if (! $customer) {
            abort(404);
        }

        try {
            $this->customerService->update($customer, $request->validated());

            return redirect()->route('customers.index')->with('success', 'Khách hàng đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('customers.edit', $customer)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $customer = $this->customerService->find($id);

        if (! $customer) {
            abort(404);
        }

        try {
            $deleted = $this->customerService->delete($customer);

            $message = $deleted
                ? 'Khách hàng đã được xóa.'
                : 'Khách hàng có dữ liệu liên quan nên không thể xóa.';

            return redirect()->route('customers.index')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('customers.index')->with('error', FriendlyError::message($e));
        }
    }
}
