<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceCategoryRequest;
use App\Models\ServiceCategory;
use App\Services\ServiceCategoryService;
use Illuminate\Http\Request;

class ServiceCategoryController extends Controller
{
    public function __construct(
        private ServiceCategoryService $categoryService,
    ) {}

    public function index(Request $request)
    {
        $categories = $this->categoryService->getAll([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort'),
        ]);

        return view('admin.service-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.service-categories.create');
    }

    public function store(ServiceCategoryRequest $request)
    {
        try {
            $this->categoryService->create($request->validated());

            return redirect()->route('service-categories.index')->with('success', 'Danh mục đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('service-categories.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $category = $this->categoryService->find($id);

        if (!$category) {
            abort(404);
        }

        return view('admin.service-categories.show', [
            'category' => $category,
            'services' => $category->services()->latest()->paginate(10),
        ]);
    }

    public function edit(int $id)
    {
        $category = $this->categoryService->find($id);

        if (!$category) {
            abort(404);
        }

        return view('admin.service-categories.edit', compact('category'));
    }

    public function update(ServiceCategoryRequest $request, int $id)
    {
        $category = $this->categoryService->find($id);

        if (!$category) {
            abort(404);
        }

        try {
            $this->categoryService->update($category, $request->validated());

            return redirect()->route('service-categories.index')->with('success', 'Danh mục đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('service-categories.edit', $category)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $category = $this->categoryService->find($id);

        if (!$category) {
            abort(404);
        }

        try {
            $this->categoryService->delete($category);

            return redirect()->route('service-categories.index')->with('success', 'Danh mục đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('service-categories.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }

    public function toggleStatus(int $id)
    {
        $category = $this->categoryService->find($id);

        if (!$category) {
            abort(404);
        }

        try {
            $category->update(['status' => $category->status === 'active' ? 'inactive' : 'active']);

            return back()->with('success', 'Trạng thái danh mục đã được cập nhật.');
        } catch (\Exception $e) {
            return back()->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}
