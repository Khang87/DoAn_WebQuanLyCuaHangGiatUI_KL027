<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GarmentCategoryRequest;
use App\Models\GarmentCategory;
use App\Services\GarmentCategoryService;
use Illuminate\Http\Request;

class GarmentCategoryController extends Controller
{
    public function __construct(
        private GarmentCategoryService $garmentCategoryService,
    ) {}

    public function index(Request $request)
    {
        $categories = $this->garmentCategoryService->getAll([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $statuses = RecordStatus::options();

        return view('admin.garment-categories.index', compact('categories', 'statuses'));
    }

    public function create()
    {
        return view('admin.garment-categories.create');
    }

    public function store(GarmentCategoryRequest $request)
    {
        try {
            $this->garmentCategoryService->create($request->validated());

            return redirect()->route('garment-categories.index')->with('success', 'Danh mục loại đồ giặt đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('garment-categories.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $category = $this->garmentCategoryService->find($id);

        if (!$category) {
            abort(404);
        }

        $garments = $category->garments()->latest()->paginate(10);

        return view('admin.garment-categories.show', compact('category', 'garments'));
    }

    public function edit(int $id)
    {
        $category = $this->garmentCategoryService->find($id);

        if (!$category) {
            abort(404);
        }

        return view('admin.garment-categories.edit', compact('category'));
    }

    public function update(GarmentCategoryRequest $request, int $id)
    {
        $category = $this->garmentCategoryService->find($id);

        if (!$category) {
            abort(404);
        }

        try {
            $this->garmentCategoryService->update($category, $request->validated());

            return redirect()->route('garment-categories.index')->with('success', 'Danh mục loại đồ giặt đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('garment-categories.edit', $category)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $category = $this->garmentCategoryService->find($id);

        if (!$category) {
            abort(404);
        }

        try {
            $this->garmentCategoryService->delete($category);

            return redirect()->route('garment-categories.index')->with('success', 'Danh mục loại đồ giặt đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('garment-categories.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}