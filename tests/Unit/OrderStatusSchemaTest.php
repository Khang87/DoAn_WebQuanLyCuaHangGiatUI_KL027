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

    public function test_status_history_table_is_not_used_as_the_dropdown_catalog(): void
    {
        $schema = file_get_contents(dirname(__DIR__, 2).'/schema.sql');

        $this->assertNotFalse($schema);
        $this->assertStringContainsString('CREATE TABLE "public"."donhang_trangthai"', $schema);
        $this->assertStringContainsString('"trangthaicu" character varying(30)', $schema);
        $this->assertStringContainsString('"trangthaimoi" character varying(30) NOT NULL', $schema);

        $historyTable = substr(
            $schema,
            strpos($schema, 'CREATE TABLE "public"."donhang_trangthai"'),
        );
        $historyTable = substr($historyTable, 0, strpos($historyTable, ');'));

        $this->assertStringNotContainsString('"TrangThai"', $historyTable);
    }
}
