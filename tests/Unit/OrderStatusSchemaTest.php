<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusSchemaTest extends TestCase
{
    public function test_dropdown_values_match_the_live_donhang_status_constraint(): void
    {
        $schema = file_get_contents(dirname(__DIR__, 2).'/schema.sql');

        $this->assertNotFalse($schema);
        $this->assertSame(
            1,
            preg_match('/ADD CONSTRAINT "DonHang_TrangThai_check".*?ARRAY\[(.*?)\]/s', $schema, $constraint),
            'The schema must contain the DonHang.TrangThai check constraint.',
        );

        preg_match_all('/\'([^\']*)\'::character varying/', $constraint[1], $matches);

        $this->assertSame($matches[1], OrderStatus::values());
        $this->assertSame(array_keys(OrderStatus::options()), OrderStatus::values());
    }

    public function test_system_audit_log_replaces_the_removed_legacy_status_table(): void
    {
        $schema = file_get_contents(dirname(__DIR__, 2).'/schema.sql');

        $this->assertNotFalse($schema);
        $this->assertStringContainsString('CREATE TABLE "public"."NhatKyHeThong"', $schema);
        $this->assertStringContainsString('"DuLieuCu" jsonb', $schema);
        $this->assertStringContainsString('"DuLieuMoi" jsonb', $schema);
        $this->assertStringNotContainsString('donhang_trangthai', $schema);
    }
}
