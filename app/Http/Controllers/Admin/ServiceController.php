<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private ServiceService $serviceService,
    ) {}

    public function index(Request $request)
    {
        $services = $this->serviceService->getAll([
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort'),
        ]);

        $categories = \App\Models\ServiceCategory::where('status', 'active')->orderBy('name')->get();

        return view('admin.services.index', compact('services', 'categories'));
    }

    public function create()
    {
        $categories = \App\Models\ServiceCategory::where('status', 'active')->orderBy('name')->get();

        return view('admin.services.create', compact('categories'));
    }

    public function store(ServiceRequest $request)
    {
        try {
            $this->serviceService->create($request->validated());

            return redirect()->route('services.index')->with('success', 'Dịch vụ đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('services.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $service = $this->serviceService->find($id);

        if (!$service) {
            abort(404);
        }

        return view('admin.services.show', compact('service'));
    }

    public function edit(int $id)
    {
        $service = $this->serviceService->find($id);

        if (!$service) {
            abort(404);
        }

        $categories = \App\Models\ServiceCategory::where('status', 'active')->orderBy('name')->get();

        return view('admin.services.edit', compact('service', 'categories'));
    }

    public function update(ServiceRequest $request, int $id)
    {
        $service = $this->serviceService->find($id);

        if (!$service) {
            abort(404);
        }

        try {
            $this->serviceService->update($service, $request->validated());

            return redirect()->route('services.index')->with('success', 'Dịch vụ đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('services.edit', $service)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $service = $this->serviceService->find($id);

        if (!$service) {
            abort(404);
        }

        try {
            $this->serviceService->delete($service);

            return redirect()->route('services.index')->with('success', 'Dịch vụ đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('services.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    public function toggleStatus(int $id)
    {
        $service = $this->serviceService->find($id);

        if (!$service) {
            abort(404);
        }

        try {
            $service->update(['status' => $service->status === 'active' ? 'inactive' : 'active']);

            return back()->with('success', 'Trạng thái dịch vụ đã được cập nhật.');
        } catch (\Exception $e) {
            return back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
