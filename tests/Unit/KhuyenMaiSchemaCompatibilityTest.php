<?php

namespace Tests\Unit;

use App\Models\KhuyenMai;
use Tests\TestCase;

class KhuyenMaiSchemaCompatibilityTest extends TestCase
{
    public function test_model_matches_the_live_promotion_table_and_key(): void
    {
        $promotion = new KhuyenMai;

        $this->assertSame('KhuyenMai', $promotion->getTable());
        $this->assertSame('KhuyenMaiID', $promotion->getKeyName());
        $this->assertFalse($promotion->usesTimestamps());
        $this->assertContains('SoLuongSuDung', $promotion->getFillable());
    }

    public function test_used_count_does_not_act_as_a_redemption_limit(): void
    {
        $promotion = new KhuyenMai([
            'LoaiKhuyenMai' => KhuyenMai::DISCOUNT_FIXED,
            'GiaTriGiam' => 10000,
            'NgayBatDau' => today()->toDateString(),
            'NgayKetThuc' => today()->toDateString(),
            'SoLuongSuDung' => 0,
            'TrangThai' => 'Hoạt động',
        ]);

        $this->assertTrue($promotion->isValid());
    }
}
