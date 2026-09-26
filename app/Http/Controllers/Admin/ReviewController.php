<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewRequest;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private ReviewService $reviewService,
    ) {}

    public function index(Request $request)
    {
        $reviews = $this->reviewService->getAll([
            'search' => $request->input('search'),
            'rating' => $request->input('rating'),
            'status' => $request->input('status'),
            'customer_id' => $request->input('customer_id'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $averageRating = $this->reviewService->getAverageRating();
        $totalReviews = $this->reviewService->getTotalReviews();

        return view('admin.reviews.index', compact('reviews', 'averageRating', 'totalReviews'));
    }

    public function show(int $id)
    {
        $review = $this->reviewService->find($id);

        if (!$review) {
            abort(404);
        }

        return view('admin.reviews.show', compact('review'));
    }

    public function store(ReviewRequest $request)
    {
        try {
            $review = $this->reviewService->create($request->validated());

            return redirect()->route('reviews.show', $review)->with('success', 'Đánh giá đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('reviews.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function update(ReviewRequest $request, int $id)
    {
        $review = $this->reviewService->find($id);

        if (!$review) {
            abort(404);
        }

        try {
            // Admin can only update shop_response and status
            $data = $request->only(['shop_response', 'status']);
            $this->reviewService->update($review, $data);

            return redirect()->route('reviews.index')->with('success', 'Đánh giá đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('reviews.edit', $review)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function respond(Request $request, int $id)
    {
        $review = $this->reviewService->find($id);

        if (!$review) {
            abort(404);
        }

        try {
            $this->reviewService->update($review, ['shop_response' => $request->input('shop_response')]);

            return redirect()->route('reviews.show', $review)->with('success', 'Phản hồi đã được gửi.');
        } catch (\Exception $e) {
            return redirect()->route('reviews.show', $review)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    public function toggleStatus(int $id)
    {
        $review = $this->reviewService->find($id);

        if (!$review) {
            abort(404);
        }

        try {
            $this->reviewService->toggleStatus($review);

            return redirect()->route('reviews.index')->with('success', 'Trạng thái hiển thị đã được thay đổi.');
        } catch (\Exception $e) {
            return redirect()->route('reviews.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    public function destroy(int $id)
    {
        $review = $this->reviewService->find($id);

        if (!$review) {
            abort(404);
        }

        try {
            $this->reviewService->delete($review);

            return redirect()->route('reviews.index')->with('success', 'Đánh giá đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('reviews.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}