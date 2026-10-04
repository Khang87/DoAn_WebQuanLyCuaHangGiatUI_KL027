<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuLoaiDoGiatRequest;
use App\Models\DanhMucLoaiDoGiat;
use App\Services\LoaiDoGiatService;
use App\Support\FriendlyError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoaiDoGiatController extends Controller
{
    public function __construct(
        private LoaiDoGiatService $loaiDoGiatService,
    ) {}

    public function index(Request $request): View
    {
        $categories = $this->loaiDoGiatService->getAll([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'category_id' => $request->integer('DanhMucID') ?: null,
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $statuses = RecordStatus::databaseOptions();

        return view('admin.loaidogiat.index', compact('categories', 'statuses'));
    }

    public function create(): View
    {
        $categories = DanhMucLoaiDoGiat::query()
            ->where('TrangThai', 'Hoạt động')
            ->orderBy('TenDanhMuc')
            ->get(['DanhMucID', 'TenDanhMuc']);

        return view('admin.loaidogiat.create', compact('categories'));
    }

    public function store(LuuLoaiDoGiatRequest $request): RedirectResponse
    {
        try {
            $this->loaiDoGiatService->create($request->validated());

            return redirect()->route('loaidogiat.index')->with('success', 'Loại đồ giặt đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('loaidogiat.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id): View
    {
        $category = $this->loaiDoGiatService->find($id);

        if (! $category) {
            abort(404);
        }

        $pricings = $category->bangGias()
            ->with(['dichVu', 'donViTinh'])
            ->orderByDesc('NgayApDung')
            ->orderByDesc('BangGiaID')
            ->paginate(10)
            ->withQueryString();

        return view('admin.loaidogiat.show', compact('category', 'pricings'));
    }

    public function edit(int $id): View
    {
        $category = $this->loaiDoGiatService->find($id);

        if (! $category) {
            abort(404);
        }

        $categories = DanhMucLoaiDoGiat::query()
            ->where(function ($query) use ($category): void {
                $query->where('TrangThai', 'Hoạt động')
                    ->orWhere('DanhMucID', $category->DanhMucID);
            })
            ->orderBy('TenDanhMuc')
            ->get(['DanhMucID', 'TenDanhMuc']);

        return view('admin.loaidogiat.edit', compact('category', 'categories'));
    }

    public function update(LuuLoaiDoGiatRequest $request, int $id): RedirectResponse
    {
        $category = $this->loaiDoGiatService->find($id);

        if (! $category) {
            abort(404);
        }

        try {
            $this->loaiDoGiatService->update($category, $request->validated());

            return redirect()->route('loaidogiat.index')->with('success', 'Loại đồ giặt đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('loaidogiat.edit', $category->LoaiDoGiatID)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $category = $this->loaiDoGiatService->find($id);

        if (! $category) {
            abort(404);
        }

        try {
            $error = $this->loaiDoGiatService->delete($category);

            if ($error !== null) {
                return redirect()->route('loaidogiat.index')->with('error', $error);
            }

            return redirect()->route('loaidogiat.index')->with('success', 'Loại đồ giặt đã được xóa.');
        } catch (\Exception $e) {
            report($e);

            return redirect()->route('loaidogiat.index')->with('error', FriendlyError::message($e, 'loại đồ giặt'));
        }
    }
}
