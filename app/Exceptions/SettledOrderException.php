<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Đơn hàng (hoặc hóa đơn của đơn hàng) đã quyết toán nên bị khoá chỉnh sửa.
 *
 * Đây là lớp exception ở tầng nghiệp vụ để chặn ở cả controller lẫn service,
 * tránh việc chỉ ẩn nút trên giao diện mà vẫn gọi được API.
 */
class SettledOrderException extends RuntimeException
{
    public static function forOrder(int|string|null $code = null): self
    {
        return new self(sprintf(
            'Đơn hàng %s đã hoàn thành hoặc đã thanh toán nên chỉ có thể xem, không thể chỉnh sửa hoặc xóa.',
            $code ? (string) $code : 'này'
        ));
    }

    public static function forInvoice(int|string|null $code = null): self
    {
        return new self(sprintf(
            'Hóa đơn %s đã thanh toán nên số tiền đã quyết toán, không thể thay đổi.',
            $code ? (string) $code : 'này'
        ));
    }
}
