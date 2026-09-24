<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Models\Promotion;
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
        ]);

        return view('admin.promotions.index', compact('promotions'));
    }

    public function create()
    {
        return view('admin.promotions.create');
    }

    public function store(PromotionRequest $request)
    {
        try {
            $this->promotionService->create($request->validated());

            return redirect()->route('promotions.index')->with('success', 'Khuyến mãi đã được tạo.');
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

        $coupons = $promotion->coupons()->latest()->paginate(10);

        return view('admin.promotions.show', compact('promotion', 'coupons'));
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

            return redirect()->route('promotions.index')->with('success', 'Khuyến mãi đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('promotions.edit', $promotion)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(int $id)
    {
        $promotion = $this->promotionService->find($id);

        if ($promotion) {
            try {
                $this->promotionService->delete($promotion);
            } catch (\Exception $e) {
                return redirect()->route('promotions.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
            }
        }

        return redirect()->route('promotions.index')->with('success', 'Khuyến mãi đã được xóa.');
    }
}
