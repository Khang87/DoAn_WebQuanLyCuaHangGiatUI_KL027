<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuLoaiDichVuRequest;
use App\Services\ServiceCategoryService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;

class LoaiDichVuController extends Controller
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
        $statuses = RecordStatus::databaseOptions();

        return view('admin.service-categories.index', compact('categories', 'statuses'));
    }

    public function create()
    {
        return view('admin.service-categories.create');
    }

    public function store(LuuLoaiDichVuRequest $request)
    {
        try {
            $this->categoryService->create($request->validated());

            return redirect()->route('service-categories.index')->with('success', 'Danh mục đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('service-categories.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            abort(404);
        }

        return view('admin.service-categories.show', [
            'category' => $category,
            'services' => $category->dichVus()->with('loaiDichVu')->orderBy('TenDichVu')->paginate(10),
        ]);
    }

    public function edit(int $id)
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            abort(404);
        }

        return view('admin.service-categories.edit', compact('category'));
    }

    public function update(LuuLoaiDichVuRequest $request, int $id)
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            abort(404);
        }

        try {
            $this->categoryService->update($category, $request->validated());

            return redirect()->route('service-categories.index')->with('success', 'Danh mục đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('service-categories.edit', $category)->with('error', FriendlyError::message($e))->withInput();
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

            return redirect()->route('service-categories.index')->with('success', 'Danh mục đã được tạm ngưng.');
        } catch (\Exception $e) {
            return redirect()->route('service-categories.index')->with('error', FriendlyError::message($e));
        }
    }

    public function toggleStatus(int $id)
    {
        $category = $this->categoryService->find($id);

        if (! $category) {
            abort(404);
        }

        try {
            $category->update(['TrangThai' => $category->TrangThai === 'Hoạt động' ? 'Tạm ngưng' : 'Hoạt động']);

            return back()->with('success', 'Trạng thái danh mục đã được cập nhật.');
        } catch (\Exception $e) {
            return back()->with('error', FriendlyError::message($e));
        }
    }
}
