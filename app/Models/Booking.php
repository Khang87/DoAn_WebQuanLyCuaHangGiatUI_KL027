<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    /** Tiền tố mã tham chiếu đặt lịch. */
    public const CODE_PREFIX = 'DL';

    protected $fillable = [
        'code',
        'customer_id',
        'staff_id',
        'method',
        'scheduled_date',
        'scheduled_time',
        'notes',
        'status',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Sinh mã tham chiếu kế tiếp, ví dụ DL0007.
     * Dùng MAX(id) + 1 để không phụ thuộc sequence của cột id.
     */
    public static function nextCode(): string
    {
        $next = ((int) static::withTrashed()->max('id')) + 1;

        do {
            $code = self::CODE_PREFIX . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    protected static function booted(): void
    {
        // Mỗi lịch hẹn luôn có mã tham chiếu để đơn hàng trỏ về dễ dàng.
        static::creating(function (Booking $booking) {
            if (blank($booking->code)) {
                $booking->code = self::nextCode();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Đơn hàng được tạo tự động từ lịch hẹn này (nếu đã xác nhận).
     * withTrashed() vì đơn có thể đã bị xoá mềm nhưng liên kết vẫn còn.
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class)->withTrashed();
    }

    public function hasOrder(): bool
    {
        return $this->order !== null;
    }

    /**
     * Trạng thái có đủ điều kiện chuyển thành đơn hàng hay không.
     * "Chờ xác nhận" (pending) chưa đủ; "Đã xác nhận" (confirmed) thì đã đủ.
     */
    public function isConvertibleToOrder(): bool
    {
        return BookingStatus::parse($this->status) === BookingStatus::Confirmed;
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            'nhan_do' => 'Nhận đồ',
            'giao_do' => 'Giao đồ',
            default => 'Nhận đồ',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return BookingStatus::labelFor($this->status);
    }
}
