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
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS "public"."NhatKyHeThong"', $schema);
        $this->assertStringContainsString('"DuLieuCu" jsonb', $schema);
        $this->assertStringContainsString('"DuLieuMoi" jsonb', $schema);
        $this->assertSame(
            0,
            preg_match('/CREATE TABLE(?: IF NOT EXISTS)?\s+"public"\."donhang_trangthai"/i', $schema),
        );
    }

    public function test_completed_order_milestones_are_distinguished_from_in_progress_statuses(): void
    {
        foreach ([OrderStatus::Received, OrderStatus::Washed, OrderStatus::Delivered, OrderStatus::Paid] as $status) {
            $this->assertTrue($status->isCompletedMilestone());
        }

        foreach ([OrderStatus::Pending, OrderStatus::Washing, OrderStatus::Delivering, OrderStatus::Cancelled] as $status) {
            $this->assertFalse($status->isCompletedMilestone());
        }
    }

    public function test_each_in_progress_order_status_has_its_next_process_action(): void
    {
        $this->assertSame(OrderStatus::Washing, OrderStatus::Received->nextProcessStatus());
        $this->assertSame(OrderStatus::Washed, OrderStatus::Washing->nextProcessStatus());
        $this->assertSame(OrderStatus::Delivering, OrderStatus::Washed->nextProcessStatus());
        $this->assertSame(OrderStatus::Delivered, OrderStatus::Delivering->nextProcessStatus());

        foreach ([OrderStatus::Pending, OrderStatus::Delivered, OrderStatus::Paid, OrderStatus::Cancelled] as $status) {
            $this->assertNull($status->nextProcessStatus());
        }
    }
}
