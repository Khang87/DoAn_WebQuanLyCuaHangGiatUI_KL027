<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuLaundryCategoryRequest;
use App\Services\LaundryCategoryService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;

class LaundryCategoryController extends Controller
{
    public function __construct(
        private LaundryCategoryService $categoryService,
    ) {}

    public function index(Request $request)
    {
        $categories = $this->categoryService->getAll([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort'),
        ]);

        $statuses = RecordStatus::databaseOptions();

        return view('admin.laundry-categories.index', compact('categories', 'statuses'));
    }

    public function create()
    {
        return view('admin.laundry-categories.create', [
            'statuses' => RecordStatus::databaseOptions(),
        ]);
    }

    public function store(LuuLaundryCategoryRequest $request)
    {
        try {
            $this->categoryService->create($request->validated());

            return redirect()->route('laundry-categories.index')->with('success', 'Danh mục loại đồ giặt đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('laundry-categories.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            abort(404);
        }

        $services = $category->dichVus()->orderBy('DichVuID')->paginate(10);

        return view('admin.laundry-categories.show', [
            'category' => $category,
            'services' => $services,
        ]);
    }

    public function edit(int $id)
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            abort(404);
        }

        return view('admin.laundry-categories.edit', [
            'category' => $category,
            'statuses' => RecordStatus::databaseOptions(),
        ]);
    }

    public function update(LuuLaundryCategoryRequest $request, int $id)
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            abort(404);
        }

        try {
            $this->categoryService->update($category, $request->validated());

            return redirect()->route('laundry-categories.index')->with('success', 'Danh mục loại đồ giặt đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('laundry-categories.edit', $category)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            abort(404);
        }

        try {
            $this->categoryService->delete($category);

            return redirect()->route('laundry-categories.index')->with('success', 'Danh mục loại đồ giặt đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('laundry-categories.index')->with('error', FriendlyError::message($e));
        }
    }

    public function toggleStatus(int $id)
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            abort(404);
        }

        try {
            $category->update([
                'TrangThai' => $category->TrangThai === 'Hoạt động' ? 'Tạm ngưng' : 'Hoạt động',
            ]);

            return back()->with('success', 'Trạng thái danh mục đã được cập nhật.');
        } catch (\Exception $e) {
            return back()->with('error', FriendlyError::message($e));
        }
    }
}
