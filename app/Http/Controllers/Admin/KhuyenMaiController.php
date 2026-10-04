<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuKhuyenMaiRequest;
use App\Services\PromotionService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;

class KhuyenMaiController extends Controller
{
    public function __construct(
        private PromotionService $promotionService,
    ) {}

    public function index(Request $request)
    {
        $promotions = $this->promotionService->getAll([
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort' => $request->input('sort'),
        ]);

        $statuses = RecordStatus::databaseOptions();

        return view('admin.promotions.index', compact('promotions', 'statuses'));
    }

    public function create()
    {
        return view('admin.promotions.create');
    }

    public function store(LuuKhuyenMaiRequest $request)
    {
        try {
            $this->promotionService->create($request->validated());

            return redirect()->route('promotions.index')->with('success', 'Chương trình khuyến mãi đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('promotions.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $promotion = $this->promotionService->find($id);

        if (! $promotion) {
            abort(404);
        }

        $orders = $promotion->donHangs()
            ->with('khachHang')
            ->orderByDesc('NgayTao')
            ->paginate(10);

        return view('admin.promotions.show', [
            'promotion' => $promotion,
            'orders' => $orders,
            'orderCount' => $promotion->donHangs()->count(),
            'totalDiscount' => (float) $promotion->donHangs()->sum('TienGiamKhuyenMai'),
        ]);
    }

    public function edit(int $id)
    {
        $promotion = $this->promotionService->find($id);

        if (! $promotion) {
            abort(404);
        }

        return view('admin.promotions.edit', compact('promotion'));
    }

    public function update(LuuKhuyenMaiRequest $request, int $id)
    {
        $promotion = $this->promotionService->find($id);

        if (! $promotion) {
            abort(404);
        }

        try {
            $this->promotionService->update($promotion, $request->validated());

            return redirect()->route('promotions.index')->with('success', 'Chương trình khuyến mãi đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('promotions.edit', $id)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $promotion = $this->promotionService->find($id);

        if (! $promotion) {
            abort(404);
        }

        try {
            $this->promotionService->delete($promotion);

            $message = $promotion->exists
                ? 'Chương trình đã được dùng trong đơn hàng nên đã chuyển sang trạng thái tạm ngưng.'
                : 'Chương trình khuyến mãi đã được xóa.';

            return redirect()->route('promotions.index')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('promotions.index')->with('error', FriendlyError::message($e));
        }
    }
}
