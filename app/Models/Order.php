<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Trạng thái đơn đã quyết toán: tiền đã chốt nên chỉ được xem.
     *
     * @return array<int, string>
     */
    public static function settledStatuses(): array
    {
        return OrderStatus::settledValues();
    }

    protected $fillable = [
        'code',
        'customer_id',
        'employee_id',
        'service_id',
        'promotion_id',
        'booking_id',
        'points_used',
        'weight_kg',
        'quantity_items',
        'subtotal',
        'discount_by_promotion',
        'discount_by_points',
        'total_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_by_promotion' => 'decimal:2',
        'discount_by_points' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'points_used' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Khách hàng của đơn. Nạp cả bản ghi đã xoá mềm: đơn là chứng tứ lịch sử
     * tài chính, nên khi khách bị xoá mềm thì đơn vẫn phải hiện tên khách,
     * không được mất liên kết và làm trang danh sách lỗi.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class)->withTrashed();
    }

    /**
     * Chi tiết đơn. KHÔNG nạp bản ghi đã xoá mềm: danh sách và tổng tiền phải
     * khớp với chứng từ, dùng itemsWithTrashed() khi cần xem lịch sử.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Chi tiết đơn gồm cả dòng đã xoá mềm, dùng cho màn hình lịch sử/đối soát.
     */
    public function itemsWithTrashed(): HasMany
    {
        return $this->hasMany(OrderItem::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withTrashed();
    }

    /**
     * Hóa đơn của đơn. Nạp cả bản ghi đã xoá mềm vì hóa đơn đã thanh toán
     * rồi vẫn phải khoá đơn.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->withTrashed();
    }

    /**
     * Đặt lịch đã được chuyển thành đơn hàng này.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    /**
     * Mã tham chiếu của lịch đặt đã sinh ra đơn này (ví dụ DL0007), null nếu
     * đơn được tạo trực tiếp. Đọc qua quan hệ `booking` đã nạp sẵn nên không
     * phát sinh truy vấn N+1 trong danh sách đơn.
     */
    public function getBookingCodeAttribute(): ?string
    {
        return $this->booking?->code;
    }

    /** Đơn này có được sinh tự động từ một lịch đặt không. */
    public function comesFromBooking(): bool
    {
        return $this->booking_id !== null;
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class)->withTrashed();
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class)->withTrashed();
    }

    /**
     * Đơn đã ở trạng thái quyết toán (hoàn thành) chưa?
     */
    public function isSettledByStatus(): bool
    {
        return OrderStatus::parse($this->status)->isSettled();
    }

    /**
     * Đơn đã có hóa đơn được thanh toán (kể cả hóa đơn đã bị xoá mềm) chưa?
     */
    public function hasPaidInvoice(): bool
    {
        if (array_key_exists('invoice', $this->relations)) {
            return InvoiceStatus::valueIsPaid($this->getRelation('invoice')?->status);
        }

        return Invoice::withTrashed()
            ->where('order_id', $this->id)
            ->whereIn('status', InvoiceStatus::paidValues())
            ->exists();
    }

    /**
     * Đơn đã thu đủ tiền qua các khoản thanh toán chưa?
     *
     * Đây là phép tính tiền thuần tuý, KHÔNG phụ thuộc cờ cấu hình, nên cột
     * "Thanh toán" và hasSettledPayment() luôn nhất quán với nhau.
     *
     * Khoản "partial" vẫn tính vào vì đã thu một phần; "failed"/"refunded"
     * không phải tiền đã vào quỹ nên không tính. Tổng tiền bằng 0 thì coi
     * như chưa thu để tránh khoá nhầm đơn miễn phí.
     */
    public function hasSettledPayment(): bool
    {
        $total = (float) $this->total_amount;

        if ($total <= 0) {
            return false;
        }

        return $this->paidAmount() >= $total;
    }

    /**
     * Định nghĩa DUY NHẤT của "đơn đã quyết toán": hoàn thành, HOẶC đã có hóa
     * đơn thanh toán, HOẶC đã thu đủ tiền. Đơn đã quyết toán là chỉ đọc:
     * không sửa, không xoá, không đổi trạng thái làm thay đổi số tiền.
     *
     * Điều kiện "đã thu đủ tiền" có thể tắt bằng LOCK_SETTLED_BY_PAYMENT=false
     * để rà soát lại các bản ghi bị khoá ngoài ý muốn mà không cần deploy lại.
     * Cờ chỉ ảnh hưởng luật khoá, không ảnh hưởng phép tính hasSettledPayment().
     */
    public function isLocked(): bool
    {
        if ($this->isSettledByStatus() || $this->hasPaidInvoice()) {
            return true;
        }

        return config('app.lock_settled.payment') !== false
            && $this->hasSettledPayment();
    }

    /**
     * Người đang đăng nhập có mở khoá được đơn đã quyết toán hay không.
     * Chỉ Chủ cửa hàng (người giữ orders.edit_completed) mở khoá được.
     */
    private function viewerCanOverrideSettled(string $code): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canPermission($code);
    }

    public function canEdit(): bool
    {
        if (! $this->isLocked()) {
            return true;
        }

        return $this->viewerCanOverrideSettled('orders.edit_completed');
    }

    public function canDelete(): bool
    {
        if (! $this->isLocked()) {
            return true;
        }

        return $this->viewerCanOverrideSettled('orders.delete_completed');
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->isLocked();
    }

    public function getCanEditAttribute(): bool
    {
        return $this->canEdit();
    }

    public function getCanDeleteAttribute(): bool
    {
        return $this->canDelete();
    }

    /**
     * Tổng số tiền đã thu của đơn. Các khoản "partial" tính vào vì đã thu một phần.
     *
     * Bản ghi đã xoá mềm KHÔNG phải tiền còn trong quỹ nên bị loại khỏi phép tính
     * (quan hệ payments() nạp cả bản ghi đã xoá để hiển thị lịch sử).
     * Vẫn dùng bản ghi đã nạp sẵn nếu có, để danh sách đơn không bị N+1.
     */
    public function paidAmount(): float
    {
        $payments = array_key_exists('payments', $this->relations)
            ? $this->getRelation('payments')
            : $this->payments()->whereNull('deleted_at')->get();

        return (float) $payments
            ->reject(fn ($payment) => $payment->deleted_at !== null)
            ->whereIn('status', ['paid', 'partial'])
            ->sum('amount');
    }

    /**
     * Trạng thái thanh toán của đơn (chưa có cột lưu trong DB nên tính từ dữ liệu).
     * Ưu tiên hóa đơn đã thanh toán, sau đó so số tiền đã thu với tổng tiền.
     */
    public function getPaymentStatusAttribute(): string
    {
        if ($this->hasPaidInvoice() || $this->hasSettledPayment()) {
            return 'paid';
        }

        return $this->paidAmount() > 0 ? 'partial' : 'pending';
    }

    /**
     * Đơn hàng đã thanh toán (payment_status === 'paid').
     * Dùng để ẩn nút Sửa/Xóa cho Nhân viên/Quản lý.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->isPaid();
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return InvoiceStatus::labelFor($this->paymentStatusEnum());
    }

    public function getPaymentStatusBadgeAttribute(): string
    {
        return InvoiceStatus::badgeClassFor($this->paymentStatusEnum());
    }

    public function getPaymentStatusIconAttribute(): string
    {
        return InvoiceStatus::iconFor($this->paymentStatusEnum());
    }

    /**
     * payment_status dùng bộ giá trị rút gọn (pending/partial/paid) nên được
     * quy đổi sang giá trị đúng của InvoiceStatus (unpaid/partial/paid) trước
     * khi tra nhãn/badge, để cột "Thanh toán" khớp với module Hóa đơn.
     */
    protected function paymentStatusEnum(): string
    {
        return match ($this->payment_status) {
            'paid' => InvoiceStatus::Paid->value,
            'partial' => InvoiceStatus::Partial->value,
            default => InvoiceStatus::Unpaid->value,
        };
    }
}
