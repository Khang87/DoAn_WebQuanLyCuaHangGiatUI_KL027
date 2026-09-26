<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeliveryRequest;
use App\Models\Customer;
use App\Models\User;
use App\Models\Order;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService,
    ) {}

    public function index(Request $request)
    {
        $deliveries = $this->deliveryService->getAll([
            'search' => $request->input('search'),
            'customer_id' => $request->input('customer_id'),
            'method' => $request->input('method'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $customers = Customer::orderBy('name')->get();
        $employees = User::where('role', '!=', 'customer')->orderBy('name')->get();

        return view('admin.deliveries.index', compact('deliveries', 'customers', 'employees'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $employees = User::where('role', '!=', 'customer')->orderBy('name')->get();
        $orders = Order::whereDoesntHave('delivery')->where('status', '!=', 'cancelled')->orderBy('created_at', 'desc')->get();

        return view('admin.deliveries.create', compact('customers', 'employees', 'orders'));
    }

    public function store(DeliveryRequest $request)
    {
        try {
            $delivery = $this->deliveryService->create($request->validated());

            return redirect()->route('deliveries.show', $delivery)->with('success', 'Giao nhận đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('deliveries.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (!$delivery) {
            abort(404);
        }

        return view('admin.deliveries.show', compact('delivery'));
    }

    public function edit(int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (!$delivery) {
            abort(404);
        }

        $customers = Customer::orderBy('name')->get();
        $employees = User::where('role', '!=', 'customer')->orderBy('name')->get();
        $orders = Order::whereDoesntHave('delivery')->where('status', '!=', 'cancelled')->orderBy('created_at', 'desc')->get();

        return view('admin.deliveries.edit', compact('delivery', 'customers', 'employees', 'orders'));
    }

    public function update(DeliveryRequest $request, int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (!$delivery) {
            abort(404);
        }

        try {
            $this->deliveryService->update($delivery, $request->validated());

            return redirect()->route('deliveries.index')->with('success', 'Giao nhận đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('deliveries.edit', $delivery)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (!$delivery) {
            abort(404);
        }

        try {
            $this->deliveryService->delete($delivery);

            return redirect()->route('deliveries.index')->with('success', 'Đã xóa giao nhận.');
        } catch (\Exception $e) {
            return redirect()->route('deliveries.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}