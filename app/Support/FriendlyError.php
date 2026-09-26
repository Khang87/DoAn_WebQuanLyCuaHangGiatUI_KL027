<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
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

        if ($e instanceof QueryException) {
            $raw = mb_strtolower($e->getMessage());

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
}
