<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Services\CouponService;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        private CouponService $couponService,
        private PromotionService $promotionService,
    ) {}

    public function index(Request $request)
    {
        $coupons = $this->couponService->getAll([
            'promotion_id' => $request->input('promotion_id'),
            'status' => $request->input('status'),
        ]);

        $promotions = Promotion::where('status', 'active')->orderBy('name')->get();

        return view('admin.coupons.index', compact('coupons', 'promotions'));
    }

    public function create()
    {
        $promotions = Promotion::where('status', 'active')->orderBy('name')->get();

        return view('admin.coupons.create', compact('promotions'));
    }

    public function store(CouponRequest $request)
    {
        try {
            $this->couponService->create($request->validated());

            return redirect()->route('coupons.index')->with('success', 'Mã giảm giá đã được tạo.');
        } catch (\Exception $e) {
            return redirect()->route('coupons.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $coupon = $this->couponService->find($id);

        if (!$coupon) {
            abort(404);
        }

        return view('admin.coupons.show', compact('coupon'));
    }

    public function edit(int $id)
    {
        $coupon = $this->couponService->find($id);
        $promotions = Promotion::where('status', 'active')->orderBy('name')->get();

        if (!$coupon) {
            abort(404);
        }

        return view('admin.coupons.edit', compact('coupon', 'promotions'));
    }

    public function update(CouponRequest $request, int $id)
    {
        $coupon = $this->couponService->find($id);

        if (!$coupon) {
            abort(404);
        }

        try {
            $this->couponService->update($coupon, $request->validated());

            return redirect()->route('coupons.index')->with('success', 'Mã giảm giá đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('coupons.edit', $coupon)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $coupon = $this->couponService->find($id);

        if ($coupon) {
            try {
                $this->couponService->delete($coupon);
            } catch (\Exception $e) {
                return redirect()->route('coupons.index')->with('error', \App\Support\FriendlyError::message($e));
            }
        }

        return redirect()->route('coupons.index')->with('success', 'Đã xóa mã giảm giá.');
    }
}
