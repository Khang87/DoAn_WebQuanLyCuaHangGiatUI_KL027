<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeliveryRequest;
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

        $customers = \App\Models\Customer::orderBy('name')->get();

        return view('admin.deliveries.index', compact('deliveries', 'customers'));
    }

    // create() and store() methods removed - "Tạo lịch giao nhận" feature disabled

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

        $customers = \App\Models\Customer::orderBy('name')->get();

        return view('admin.deliveries.edit', compact('delivery', 'customers'));
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
            return redirect()->route('deliveries.edit', $delivery)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if ($delivery) {
            try {
                $this->deliveryService->delete($delivery);
            } catch (\Exception $e) {
                return redirect()->route('deliveries.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
            }
        }

        return redirect()->route('deliveries.index')->with('success', 'Đã xóa giao nhận.');
    }
}
