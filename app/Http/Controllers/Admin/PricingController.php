<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PricingRequest;
use App\Models\Pricing;
use App\Models\Service;
use App\Models\Garment;
use App\Services\PricingService;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(
        private PricingService $pricingService,
    ) {}

    public function index(Request $request)
    {
        $pricings = $this->pricingService->getAll([
            'search' => $request->input('search'),
            'service_id' => $request->input('service_id'),
            'garment_id' => $request->input('garment_id'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $services = Service::where('status', 'active')->orderBy('name')->get();
        $garments = Garment::where('status', 'active')->orderBy('name')->get();
        $statuses = RecordStatus::options();
        $units = Pricing::unitOptions();

        return view('admin.pricings.index', compact('pricings', 'services', 'garments', 'statuses', 'units'));
    }

    public function create()
    {
        $services = Service::where('status', 'active')->orderBy('name')->get();
        $garments = Garment::where('status', 'active')->orderBy('name')->get();

        return view('admin.pricings.create', compact('services', 'garments'));
    }

    public function store(PricingRequest $request)
    {
        try {
            $this->pricingService->create($request->validated());

            return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('pricings.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $pricing = $this->pricingService->find($id);

        if (!$pricing) {
            abort(404);
        }

        return view('admin.pricings.show', compact('pricing'));
    }

    public function edit(int $id)
    {
        $pricing = $this->pricingService->find($id);

        if (!$pricing) {
            abort(404);
        }

        $services = Service::where('status', 'active')->orderBy('name')->get();
        $garments = Garment::where('status', 'active')->orderBy('name')->get();

        return view('admin.pricings.edit', compact('pricing', 'services', 'garments'));
    }

    public function update(PricingRequest $request, int $id)
    {
        $pricing = $this->pricingService->find($id);

        if (!$pricing) {
            abort(404);
        }

        try {
            $this->pricingService->update($pricing, $request->validated());

            return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('pricings.edit', $id)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $pricing = $this->pricingService->find($id);

        if (!$pricing) {
            abort(404);
        }

        try {
            $this->pricingService->delete($pricing);

            return redirect()->route('pricings.index')->with('success', 'Bảng giá đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('pricings.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}