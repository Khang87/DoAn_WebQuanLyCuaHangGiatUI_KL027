<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GarmentRequest;
use App\Models\DichVu;
use App\Models\LoaiDichVu;
use App\Models\LoaiDoGiat;
use App\Services\ServiceService;
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
            'category' => $request->input('category'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort'),
        ]);

        $categories = LoaiDichVu::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDichVu')->get();
        $statuses = RecordStatus::options();

        return view('admin.garments.index', compact('services', 'categories', 'statuses'));
    }

    public function create()
    {
        $categories = LoaiDichVu::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDichVu')->get();
        $garmentTypes = LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();

        return view('admin.garments.create', compact('categories', 'garmentTypes'));
    }

    public function store(GarmentRequest $request)
    {
        try {
            $this->serviceService->create($request->validated());

            return redirect()->route('garments.index')->with('success', 'Dịch vụ đã được thêm.');
        } catch (\Exception $e) {
            return redirect()->route('garments.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $service = $this->serviceService->find($id);

        if (!$service) {
            abort(404);
        }

        return view('admin.garments.show', compact('service'));
    }

    public function edit(int $id)
    {
        $service = $this->serviceService->find($id);

        if (!$service) {
            abort(404);
        }

        $categories = LoaiDichVu::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDichVu')->get();
        $garmentTypes = LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();

        return view('admin.garments.edit', compact('service', 'categories', 'garmentTypes'));
    }

    public function update(GarmentRequest $request, int $id)
    {
        $service = $this->serviceService->find($id);

        if (!$service) {
            abort(404);
        }

        try {
            $this->serviceService->update($service, $request->validated());

            return redirect()->route('garments.index')->with('success', 'Dịch vụ đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('garments.edit', $service)->with('error', \App\Support\FriendlyError::message($e))->withInput();
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

            return redirect()->route('garments.index')->with('success', 'Dịch vụ đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('garments.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}
