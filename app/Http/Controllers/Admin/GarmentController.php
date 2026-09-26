<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GarmentRequest;
use App\Models\Garment;
use App\Services\GarmentService;
use Illuminate\Http\Request;

class GarmentController extends Controller
{
    public function __construct(
        private GarmentService $garmentService,
    ) {}

    public function index(Request $request)
    {
        $garments = $this->garmentService->getAll([
            'search' => $request->input('search'),
            'category' => $request->input('category'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $categories = $this->garmentService->getCategories();
        $statuses = RecordStatus::options();

        return view('admin.garments.index', compact('garments', 'categories', 'statuses'));
    }

    public function create()
    {
        $categories = $this->garmentService->getCategories();

        return view('admin.garments.create', compact('categories'));
    }

    public function store(GarmentRequest $request)
    {
        try {
            $this->garmentService->create($request->validated());

            return redirect()->route('garments.index')->with('success', 'Loại đồ giặt đã được thêm.');
        } catch (\Exception $e) {
            return redirect()->route('garments.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $garment = $this->garmentService->find($id);

        if (!$garment) {
            abort(404);
        }

        $conditions = $garment->conditions()->latest()->paginate(10);

        return view('admin.garments.show', compact('garment', 'conditions'));
    }

    public function edit(int $id)
    {
        $garment = $this->garmentService->find($id);

        if (!$garment) {
            abort(404);
        }

        $categories = $this->garmentService->getCategories();

        return view('admin.garments.edit', compact('garment', 'categories'));
    }

    public function update(GarmentRequest $request, int $id)
    {
        $garment = $this->garmentService->find($id);

        if (!$garment) {
            abort(404);
        }

        try {
            $this->garmentService->update($garment, $request->validated());

            return redirect()->route('garments.index')->with('success', 'Loại đồ giặt đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('garments.edit', $garment)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $garment = $this->garmentService->find($id);

        if (!$garment) {
            abort(404);
        }

        try {
            $this->garmentService->delete($garment);

            return redirect()->route('garments.index')->with('success', 'Loại đồ giặt đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('garments.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
