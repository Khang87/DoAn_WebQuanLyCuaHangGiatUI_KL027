<?php

namespace Tests\Unit;

use App\Support\FriendlyError;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class FriendlyErrorTest extends TestCase
{
    public function test_missing_order_status_audit_table_returns_an_actionable_message(): void
    {
        $exception = new QueryException(
            'pgsql',
            'insert into "DonHang"',
            [],
            new PDOException('SQLSTATE[42P01]: relation "public.donhang_trangthai" does not exist'),
        );

        $this->assertSame(
            'Không thể tạo đơn hàng vì cấu hình nhật ký trạng thái cũ trên CSDL Supabase chưa tương thích. Booking chưa được xác nhận; vui lòng liên hệ quản trị viên.',
            FriendlyError::message($exception),
        );
    }

    public function test_other_missing_relations_do_not_expose_database_details(): void
    {
        $exception = new QueryException(
            'pgsql',
            'select * from "private_table"',
            [],
            new PDOException('SQLSTATE[42P01]: relation "private_table" does not exist'),
        );

        $this->assertSame(
            'Không thể xác nhận Booking hoặc tạo đơn hàng vì CSDL Supabase đang thiếu một bảng được ứng dụng hoặc trigger tham chiếu. Giao dịch đã được hủy; vui lòng liên hệ quản trị viên kiểm tra schema và trigger.',
            FriendlyError::message($exception),
        );
    }

    public function test_missing_order_status_table_is_recognized_without_public_schema_prefix(): void
    {
        $exception = new QueryException(
            'pgsql',
            'insert into "DonHang"',
            [],
            new PDOException('SQLSTATE[42P01]: relation "donhang_trangthai" does not exist'),
        );

        $this->assertStringContainsString(
            'cấu hình nhật ký trạng thái cũ trên CSDL Supabase',
            FriendlyError::message($exception),
        );
    }

    public function test_missing_relation_is_detected_from_query_exception_sql_state(): void
    {
        $exception = new QueryException(
            'pgsql',
            'insert into "DonHang"',
            [],
            new PDOException('missing relation', 0, null),
        );
        $exception->errorInfo = ['42P01', 7, 'relation does not exist'];

        $this->assertSame(
            '42P01',
            FriendlyError::sqlState($exception),
        );
        $this->assertStringContainsString(
            'CSDL Supabase đang thiếu một bảng',
            FriendlyError::message($exception),
        );
    }

    public function test_legacy_trigger_error_is_reported_without_exposing_database_details(): void
    {
        $exception = new PDOException(
            'SQLSTATE[23514]: failure in private.record_legacy_order_status_change()',
        );

        $this->assertStringContainsString(
            'trigger nhật ký trạng thái cũ trên CSDL Supabase chưa tương thích',
            FriendlyError::message($exception),
        );
        $this->assertStringNotContainsString(
            'private.record_legacy_order_status_change',
            FriendlyError::message($exception),
        );
    }

    public function test_undefined_column_error_explains_schema_or_case_mismatch_without_exposing_details(): void
    {
        $exception = new QueryException(
            'pgsql',
            'insert into "DonHang"',
            [],
            new PDOException('SQLSTATE[42703]: Undefined column: record "new" has no field "donhangid"'),
        );

        $this->assertSame(
            'Không thể xử lý dữ liệu này vì câu lệnh hoặc trigger trên CSDL Supabase đang tham chiếu đến cột không tồn tại hoặc sai chữ hoa/thường. Giao dịch đã được hủy; vui lòng liên hệ quản trị viên kiểm tra tên cột với schema.',
            FriendlyError::message($exception),
        );
        $this->assertStringNotContainsString(
            'donhangid',
            FriendlyError::message($exception),
        );
        $this->assertStringContainsString(
            'Không thể xử lý loại đồ giặt',
            FriendlyError::message($exception, 'loại đồ giặt'),
        );
    }

    public function test_legacy_trigger_lowercase_status_field_error_is_identified(): void
    {
        $exception = new QueryException(
            'pgsql',
            'insert into "DonHang"',
            [],
            new PDOException('SQLSTATE[42703]: Undefined column: record "old" has no field "trangthai" CONTEXT: PL/pgSQL function private.record_legacy_order_status_change() line 4'),
        );

        $this->assertSame(
            'Không thể tạo đơn hàng vì trigger Supabase đang đọc sai tên cột trạng thái: schema dùng "TrangThai" có phân biệt chữ hoa/thường. Booking chưa được xác nhận; giao dịch đã được hủy.',
            FriendlyError::message($exception),
        );
        $this->assertStringNotContainsString(
            'old" has no field',
            FriendlyError::message($exception),
        );
    }

    public function test_aborted_transaction_error_is_not_shown_as_a_generic_failure(): void
    {
        $exception = new QueryException(
            'pgsql',
            'insert into "DonHang"',
            [],
            new PDOException('SQLSTATE[25P02]: current transaction is aborted'),
        );

        $this->assertSame(
            'Giao dịch CSDL đã bị hủy do một lỗi xảy ra trước đó. Booking chưa được xác nhận; vui lòng kiểm tra log lỗi PostgreSQL trước khi thử lại.',
            FriendlyError::message($exception),
        );
    }
}
