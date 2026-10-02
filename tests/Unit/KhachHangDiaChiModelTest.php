<?php

namespace Tests\Unit;

use App\Models\KhachHang;
use App\Models\KhachHangDiaChi;
use Tests\TestCase;

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

    public function test_customer_has_many_addresses_through_the_lowercase_foreign_key(): void
    {
        $relation = (new KhachHang)->diaChis();

        $this->assertSame('khachhangid', $relation->getForeignKeyName());
        $this->assertSame('KhachHangID', $relation->getLocalKeyName());
        $this->assertSame('khachhang_diachi', $relation->getRelated()->getTable());
    }
}
