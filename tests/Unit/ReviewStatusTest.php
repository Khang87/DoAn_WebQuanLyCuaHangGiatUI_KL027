<?php

namespace Tests\Unit;

use App\Enums\ReviewStatus;
use App\Models\DanhGia;
use PHPUnit\Framework\TestCase;

class ReviewStatusTest extends TestCase
{
    public function test_database_status_labels_are_parsed_as_ui_status_values(): void
    {
        $this->assertSame(ReviewStatus::Hidden, ReviewStatus::parse('Ẩn'));
        $this->assertSame(ReviewStatus::Visible, ReviewStatus::parse('Hiển thị'));
    }

    public function test_review_model_exposes_database_status_in_the_format_used_by_the_ui(): void
    {
        $hiddenReview = new DanhGia(['TrangThai' => 'Ẩn']);
        $visibleReview = new DanhGia(['TrangThai' => 'Hiển thị']);

        $this->assertSame(ReviewStatus::Hidden->value, $hiddenReview->status);
        $this->assertSame(ReviewStatus::Visible->value, $visibleReview->status);
    }
}
