<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuGarmentRequest;
use App\Services\ServiceService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;

class GarmentController extends Controller
{
    public function __construct(
        private ServiceService $serviceService,
    ) {}

    public function index(Request $request)
    {
        $services = $this->serviceService->getAll([
            'search' => $request->input('search'),
            'category_id' => $request->input('category'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort'),
        ]);

        $categories = $this->serviceService->getCategories();
        $statuses = RecordStatus::databaseOptions();

        return view('admin.garments.index', compact('services', 'categories', 'statuses'));
    }

    public function create()
    {
        $categories = $this->serviceService->getCategories();

        return view('admin.garments.create', compact('categories'));
    }

    public function store(LuuGarmentRequest $request)
    {
        try {
            $this->serviceService->create($request->validated());

            return redirect()->route('garments.index')->with('success', 'Dịch vụ đã được thêm.');
        } catch (\Exception $e) {
            return redirect()->route('garments.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $service = $this->serviceService->find($id);

        if (! $service) {
            abort(404);
        }

        $pricings = $service->bangGias()
            ->with(['loaiDoGiat', 'donViTinh'])
            ->orderByDesc('NgayApDung')
            ->orderByDesc('BangGiaID')
            ->paginate(10);

        return view('admin.garments.show', compact('service', 'pricings'));
    }

    public function edit(int $id)
    {
        $service = $this->serviceService->find($id);

        if (! $service) {
            abort(404);
        }

        $categories = $this->serviceService->getCategories();

        return view('admin.garments.edit', compact('service', 'categories'));
    }

    public function update(LuuGarmentRequest $request, int $id)
    {
        $service = $this->serviceService->find($id);

        if (! $service) {
            abort(404);
        }

        try {
            $this->serviceService->update($service, $request->validated());

            return redirect()->route('garments.index')->with('success', 'Dịch vụ đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('garments.edit', $service)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $service = $this->serviceService->find($id);

        if (! $service) {
            abort(404);
        }

        try {
            $this->serviceService->delete($service);

            return redirect()->route('garments.index')->with('success', 'Dịch vụ đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('garments.index')->with('error', FriendlyError::message($e));
        }
    }
}
