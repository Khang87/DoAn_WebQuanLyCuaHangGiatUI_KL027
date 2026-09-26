<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordStatus;
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
            'sort' => $request->input('sort'),
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
            $this->promotionService->create($this->payload($request));

            return redirect()->route('promotions.index')->with('success', 'Chương trình khuyến mãi đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('promotions.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $promotion = $this->promotionService->find($id);

        if (!$promotion) {
            abort(404);
        }

        return view('admin.promotions.show', [
            'promotion' => $promotion,
            // View chi tiết hiển thị bảng các mã giảm giá thuộc chương trình.
            'coupons' => $promotion->coupons()->latest()->paginate(10),
        ]);
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
            $this->promotionService->update($promotion, $this->payload($request));

            return redirect()->route('promotions.index')->with('success', 'Chương trình khuyến mãi đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('promotions.edit', $id)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    /**
     * Dữ liệu gửi lên sau khi đã validate.
     *
     * Checkbox không được tích không gửi field nào, nên phải tự chuẩn hoá
     * `conditions` để người dùng bỏ được điều kiện "chỉ đơn đầu tiên"
     * thay vì điều kiện cũ bị giữ lại mãi.
     */
    private function payload(PromotionRequest $request): array
    {
        $data = $request->validated();

        $data['conditions'] = $request->boolean('conditions.' . Promotion::CONDITION_FIRST_ORDER_ONLY)
            ? [Promotion::CONDITION_FIRST_ORDER_ONLY => true]
            : [];

        return $data;
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
            return redirect()->route('promotions.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}