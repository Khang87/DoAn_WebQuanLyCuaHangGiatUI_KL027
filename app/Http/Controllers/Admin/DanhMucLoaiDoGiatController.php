<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuDanhMucLoaiDoGiatRequest;
use App\Services\GarmentCategoryService;
use App\Support\FriendlyError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DanhMucLoaiDoGiatController extends Controller
{
    public function __construct(
        private GarmentCategoryService $categoryService,
    ) {}

    public function index(Request $request): View
    {
        $categories = $this->categoryService->getAll([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort'),
        ]);
        $statuses = RecordStatus::databaseOptions();

        return view('admin.garment-categories.index', compact('categories', 'statuses'));
    }

    public function create(): View
    {
        return view('admin.garment-categories.create');
    }

    public function store(LuuDanhMucLoaiDoGiatRequest $request): RedirectResponse
    {
        try {
            $this->categoryService->create($request->validated());

            return redirect()->route('garment-categories.index')
                ->with('success', 'Danh mục loại đồ giặt đã được tạo thành công.');
        } catch (\Throwable $exception) {
            return redirect()->route('garment-categories.create')
                ->with('error', FriendlyError::message($exception))
                ->withInput();
        }
    }

    public function show(int $id): View
    {
        $category = $this->categoryService->find($id);
        if (! $category) {
            abort(404);
        }

        return view('admin.garment-categories.show', [
            'category' => $category,
            'garments' => $category->loaiDoGiats()->orderBy('TenLoaiDoGiat')->paginate(10),
        ]);
    }

    public function edit(int $id): View
    {
        $category = $this->categoryService->find($id);
        if (! $category) {
            abort(404);
        }

        return view('admin.garment-categories.edit', compact('category'));
    }

    public function update(LuuDanhMucLoaiDoGiatRequest $request, int $id): RedirectResponse
    {
        $category = $this->categoryService->find($id);
        if (! $category) {
            abort(404);
        }

        try {
            $this->categoryService->update($category, $request->validated());

            return redirect()->route('garment-categories.index')
                ->with('success', 'Danh mục loại đồ giặt đã được cập nhật.');
        } catch (\Throwable $exception) {
            return redirect()->route('garment-categories.edit', $category)
                ->with('error', FriendlyError::message($exception))
                ->withInput();
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $category = $this->categoryService->find($id);
        if (! $category) {
            abort(404);
        }

        try {
            $deleted = $this->categoryService->delete($category);
            $message = $deleted
                ? 'Danh mục loại đồ giặt đã được xóa.'
                : 'Danh mục còn loại đồ giặt sử dụng nên đã được chuyển sang trạng thái tạm ngưng.';

            return redirect()->route('garment-categories.index')->with('success', $message);
        } catch (\Throwable $exception) {
            return redirect()->route('garment-categories.index')
                ->with('error', FriendlyError::message($exception));
        }
    }
}
