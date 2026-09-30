<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuCouponRequest;
use App\Models\KhuyenMai;
use App\Services\PromotionService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        private PromotionService $promotionService,
    ) {}

    public function index(Request $request)
    {
        $coupons = $this->promotionService->getAll([
            'status' => $request->input('status'),
        ]);

        $promotions = KhuyenMai::where('TrangThai', 'Hoạt động')->orderBy('TenKhuyenMai')->get();

        return view('admin.coupons.index', compact('coupons', 'promotions'));
    }

    public function create()
    {
        $promotions = KhuyenMai::where('TrangThai', 'Hoạt động')->orderBy('TenKhuyenMai')->get();

        return view('admin.coupons.create', compact('promotions'));
    }

    public function store(LuuCouponRequest $request)
    {
        try {
            $this->promotionService->create($request->validated());

            return redirect()->route('coupons.index')->with('success', 'Mã giảm giá đã được tạo.');
        } catch (\Exception $e) {
            return redirect()->route('coupons.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $coupon = $this->promotionService->find($id);

        if (! $coupon) {
            abort(404);
        }

        return view('admin.coupons.show', compact('coupon'));
    }

    public function edit(int $id)
    {
        $coupon = $this->promotionService->find($id);
        $promotions = KhuyenMai::where('TrangThai', 'Hoạt động')->orderBy('TenKhuyenMai')->get();

        if (! $coupon) {
            abort(404);
        }

        return view('admin.coupons.edit', compact('coupon', 'promotions'));
    }

    public function update(LuuCouponRequest $request, int $id)
    {
        $coupon = $this->promotionService->find($id);

        if (! $coupon) {
            abort(404);
        }

        try {
            $this->promotionService->update($coupon, $request->validated());

            return redirect()->route('coupons.index')->with('success', 'Mã giảm giá đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('coupons.edit', $coupon)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $coupon = $this->promotionService->find($id);

        if ($coupon) {
            try {
                $this->promotionService->delete($coupon);
            } catch (\Exception $e) {
                return redirect()->route('coupons.index')->with('error', FriendlyError::message($e));
            }
        }

        return redirect()->route('coupons.index')->with('success', 'Đã xóa mã giảm giá.');
    }
}
