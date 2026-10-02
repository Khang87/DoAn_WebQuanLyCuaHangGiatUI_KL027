<?php

namespace Tests\Unit;

use App\Models\KhachHangDiaChi;
use PHPUnit\Framework\TestCase;

class KhachHangDiaChiModelTest extends TestCase
{
    public function test_identity_key_is_database_managed_and_lowercase_schema_is_explicit(): void
    {
        $model = new KhachHangDiaChi;

        $this->assertSame('khachhang_diachi', $model->getTable());
        $this->assertSame('diachiid', $model->getKeyName());
        $this->assertNotContains('diachiid', $model->getFillable());
        $this->assertTrue($model->getIncrementing());
    }
}
