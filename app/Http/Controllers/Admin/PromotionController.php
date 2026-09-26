<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        private PromotionService $promotionService,
    ) {}

    public function index(Request $request)
    {
        $promotions = $this->promotionService->getAll([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $statuses = RecordStatus::options();

        return view('admin.promotions.index', compact('promotions', 'statuses'));
    }

    public function create()
    {
        return view('admin.promotions.create');
    }

    public function store(PromotionRequest $request)
    {
        try {
            $this->promotionService->create($request->validated());

            return redirect()->route('promotions.index')->with('success', 'Chương trình khuyến mãi đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('promotions.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function show(int $id)
    {
        $promotion = $this->promotionService->find($id);

        if (!$promotion) {
            abort(404);
        }

        return view('admin.promotions.show', compact('promotion'));
    }

    public function edit(int $id)
    {
        $promotion = $this->promotionService->find($id);

        if (!$promotion) {
            abort(404);
        }

        return view('admin.promotions.edit', compact('promotion'));
    }

    public function update(PromotionRequest $request, int $id)
    {
        $promotion = $this->promotionService->find($id);

        if (!$promotion) {
            abort(404);
        }

        try {
            $this->promotionService->update($promotion, $request->validated());

            return redirect()->route('promotions.index')->with('success', 'Chương trình khuyến mãi đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('promotions.edit', $id)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $promotion = $this->promotionService->find($id);

        if (!$promotion) {
            abort(404);
        }

        try {
            $this->promotionService->delete($promotion);

            return redirect()->route('promotions.index')->with('success', 'Chương trình khuyến mãi đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('promotions.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}