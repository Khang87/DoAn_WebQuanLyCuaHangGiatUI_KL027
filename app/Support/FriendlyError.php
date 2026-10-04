<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

/**
 * Chuyển lỗi kỹ thuật (SQL, ràng buộc khóa ngoại, validate) thành thông báo
 * tiếng Việt thân thiện, không làm lộ câu SQL cho người dùng cuối.
 */
class FriendlyError
{
    /**
     * Vế sau của thông báo khi dữ liệu đã phát sinh giao dịch nên không thể xóa.
     * Ghép sau tên đối tượng để thành câu hoàn chỉnh.
     */
    public const FK_BLOCKED = 'do đã có giao dịch liên quan trên hệ thống.';

    public static function message(Throwable $e, string $subject = 'dữ liệu này'): string
    {
        if ($e instanceof ValidationException) {
            return 'Dữ liệu không hợp lệ, vui lòng kiểm tra lại các trường đã nhập.';
        }

        $raw = mb_strtolower(self::exceptionMessages($e));
        $sqlState = self::sqlState($e);
        $isMissingRelation = $sqlState === '42P01';

        if ($isMissingRelation && str_contains($raw, 'donhang_trangthai')) {
            return 'Không thể tạo đơn hàng vì cấu hình nhật ký trạng thái cũ trên CSDL Supabase chưa tương thích. Booking chưa được xác nhận; vui lòng liên hệ quản trị viên.';
        }

        if ($isMissingRelation) {
            return 'Không thể xác nhận Booking hoặc tạo đơn hàng vì CSDL Supabase đang thiếu một bảng được ứng dụng hoặc trigger tham chiếu. Giao dịch đã được hủy; vui lòng liên hệ quản trị viên kiểm tra schema và trigger.';
        }

        if ($sqlState === '42703') {
            if (str_contains($raw, 'old.trangthai') || str_contains($raw, 'old" has no field "trangthai')) {
                return 'Không thể tạo đơn hàng vì trigger Supabase đang đọc sai tên cột trạng thái: schema dùng "TrangThai" có phân biệt chữ hoa/thường. Booking chưa được xác nhận; giao dịch đã được hủy.';
            }

            return 'Không thể xử lý '.$subject.' vì câu lệnh hoặc trigger trên CSDL Supabase đang tham chiếu đến cột không tồn tại hoặc sai chữ hoa/thường. Giao dịch đã được hủy; vui lòng liên hệ quản trị viên kiểm tra tên cột với schema.';
        }

        if (str_contains($raw, 'record_legacy_order_status_change')) {
            return 'Không thể tạo đơn hàng vì trigger nhật ký trạng thái cũ trên CSDL Supabase chưa tương thích. Booking chưa được xác nhận; vui lòng liên hệ quản trị viên.';
        }

        if ($sqlState === '25P02') {
            return 'Giao dịch CSDL đã bị hủy do một lỗi xảy ra trước đó. Booking chưa được xác nhận; vui lòng kiểm tra log lỗi PostgreSQL trước khi thử lại.';
        }

        if ($e instanceof QueryException) {
            $isForeignKey = str_contains($raw, 'foreign key constraint failed')
                || str_contains($raw, 'foreign key')
                || in_array((string) $e->getCode(), ['23000', '23503'], true);

            if ($isForeignKey) {
                return 'Không thể xóa '.$subject.' '.self::FK_BLOCKED;
            }

            if (str_contains($raw, 'unique constraint failed') || in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                return 'Không thể lưu '.$subject.' vì dữ liệu đã tồn tại.';
            }

            if (str_contains($raw, 'not null constraint failed')) {
                return 'Không thể lưu '.$subject.' vì thiếu thông tin bắt buộc.';
            }
        }

        return 'Có lỗi xảy ra khi xử lý. Vui lòng thử lại.';
    }

    public static function sqlState(Throwable $exception): ?string
    {
        $errorInfo = $exception instanceof PDOException ? $exception->errorInfo : null;
        $sqlState = is_array($errorInfo) ? ($errorInfo[0] ?? null) : null;

        if (is_string($sqlState) && preg_match('/^[0-9A-Z]{5}$/', $sqlState) === 1) {
            return $sqlState;
        }

        if (preg_match('/SQLSTATE\[([0-9A-Z]{5})\]/i', $exception->getMessage(), $matches) === 1) {
            return strtoupper($matches[1]);
        }

        $code = strtoupper((string) $exception->getCode());

        return preg_match('/^[0-9A-Z]{5}$/', $code) === 1 ? $code : null;
    }

    private static function exceptionMessages(Throwable $exception): string
    {
        $messages = [];

        do {
            $messages[] = $exception->getMessage();
            $exception = $exception->getPrevious();
        } while ($exception !== null);

        return implode("\n", $messages);
    }
}
