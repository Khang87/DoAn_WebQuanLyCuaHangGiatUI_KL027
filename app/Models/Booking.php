<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\ReceiveMethod;
use App\Enums\ReturnMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Booking extends Model
{
    use HasFactory;

    protected $table = 'Booking';

    protected $primaryKey = 'BookingID';

    public $timestamps = false;

    public static $snakeAttributes = false;

    /** Cột thời gian tiếng Việt thay cho created_at/updated_at. */
    public const CREATED_AT = 'NgayTao';

    public const UPDATED_AT = 'NgayCapNhat';

    /** Tiền tố mã tham chiếu đặt lịch. */
    public const CODE_PREFIX = 'DL';

    protected $fillable = [
        'MaBooking', 'KhachHangID', 'HinhThucNhanDo', 'DiaChiNhan', 'HinhThucTraDo', 'DiaChiTra',
        'NgayHen', 'GioHen', 'GhiChu', 'TrangThai', 'NgayTao', 'NgayCapNhat',
        'IdempotencyKey', 'BookingID', 'NhanVienID', 'NhanVienXacNhanID',
        'ThoiGianXacNhan', 'DiemSuDung', 'TienGiamDoDiem', 'DiemDaTru'];

    protected $casts = [
        'BookingID' => 'integer',
        'KhachHangID' => 'integer',
        'NhanVienID' => 'integer',
        'NhanVienXacNhanID' => 'integer',
        'NgayHen' => 'date',
        'NgayTao' => 'datetime',
        'NgayCapNhat' => 'datetime',
        'ThoiGianXacNhan' => 'datetime',
        'DiemSuDung' => 'integer',
        'TienGiamDoDiem' => 'float',
        'DiemDaTru' => 'boolean',
    ];

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'KhachHangID');
    }

    public function chiTietBookings(): HasMany
    {
        return $this->hasMany(ChiTietBooking::class, 'BookingID', 'BookingID');
    }

    public function donHangs()
    {
        return $this->hasMany(DonHang::class, 'BookingID');
    }

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'NhanVienID');
    }

    public function nhanVienXacNhan(): BelongsTo
    {
        return $this->belongsTo(NhanVien::class, 'NhanVienXacNhanID', 'NhanVienID');
    }

    /* ---------------------------------------------------------------------
     | Alias tiếng Anh cho tầng trên (controller / service / view)
     |---------------------------------------------------------------------*/

    public function customer()
    {
        return $this->khachHang();
    }

    public function staff()
    {
        return $this->nhanVien();
    }

    public function orders()
    {
        return $this->donHangs();
    }

    public function getCodeAttribute(): ?string
    {
        return $this->MaBooking;
    }

    public function getCustomerIdAttribute(): ?int
    {
        return $this->KhachHangID !== null ? (int) $this->KhachHangID : null;
    }

    public function getStaffIdAttribute(): ?int
    {
        return $this->NhanVienID !== null ? (int) $this->NhanVienID : null;
    }

    public function getMethodAttribute(): ?string
    {
        return $this->HinhThucNhanDo;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->GhiChu;
    }

    public function getStatusAttribute(): ?string
    {
        return $this->TrangThai;
    }

    /* ---------------------------------------------------------------------
     | Cột `GioHen` là kiểu TIME nên không dùng cast datetime:
     | ghi xuống luôn "H:i:s", đọc lên trả về Carbon để view format() được.
     |---------------------------------------------------------------------*/

    public function getGioHenAttribute($value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        $value = (string) $value;

        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    public function setGioHenAttribute($value): void
    {
        $this->attributes['GioHen'] = blank($value)
            ? null
            : Carbon::parse($value)->format('H:i:s');
    }

    /* ---------------------------------------------------------------------
     | Nghiệp vụ
     |---------------------------------------------------------------------*/

    public function statusEnum(): BookingStatus
    {
        return BookingStatus::parse($this->TrangThai);
    }

    public function receiveMethodEnum(): ?ReceiveMethod
    {
        return ReceiveMethod::tryFrom((string) $this->HinhThucNhanDo);
    }

    public function returnMethodEnum(): ?ReturnMethod
    {
        return ReturnMethod::tryFrom((string) $this->HinhThucTraDo);
    }

    /**
     * Trạng thái có đủ điều kiện chuyển thành đơn hàng hay không.
     * Booking chờ xác nhận được kiểm tra thực tế trước khi tạo đơn.
     */
    public function isConvertibleToOrder(): bool
    {
        return $this->statusEnum() === BookingStatus::Pending;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusEnum()->label();
    }

    public function getMethodLabelAttribute(): string
    {
        return $this->receiveMethodEnum()?->label() ?? 'Chưa bổ sung';
    }

    public function getReturnMethodLabelAttribute(): string
    {
        return $this->returnMethodEnum()?->label() ?? 'Chưa bổ sung';
    }

    public function getMethodIconAttribute(): string
    {
        return $this->receiveMethodEnum()?->icon() ?? 'bi-question-circle';
    }

    /**
     * Sinh mã tham chiếu kế tiếp, ví dụ DL0007.
     * Dùng MAX(BookingID) + 1 để không phụ thuộc sequence của cột id.
     */
    public static function nextCode(): string
    {
        $next = ((int) static::max('BookingID')) + 1;

        do {
            $code = self::CODE_PREFIX.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (static::where('MaBooking', $code)->exists());

        return $code;
    }

    protected static function booted(): void
    {
        // Mỗi lịch hẹn luôn có mã tham chiếu để đơn hàng trỏ về dễ dàng.
        static::creating(function (Booking $booking) {
            if (blank($booking->MaBooking)) {
                $booking->MaBooking = self::nextCode();
            }
        });
    }
}
