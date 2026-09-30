<?php

namespace App\Models;

use App\Support\QuyenMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vai trò trong bảng `VaiTro` của Supabase (TenVaiTro: Chủ cửa hàng, Quản lý,
 * Nhân viên, Khách hàng).
 *
 * Vai trò nối với tài khoản qua `TaiKhoan_VaiTro` và với quyền qua
 * `VaiTro_Quyen`.
 */
class VaiTro extends Model
{
    /** Tên vai trò cao nhất, luôn có toàn bộ quyền. */
    public const OWNER = 'Chủ cửa hàng';

    /**
     * Chữ cái có dấu trong tên vai trò, dùng để bỏ dấu khi tạo slug.
     *
     * @var array<string, string>
     */
    private const VIETNAMESE_BASES = [
        'đ' => 'd', 'Đ' => 'D',
        'à' => 'a', 'á' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a', 'â' => 'a',
        'ằ' => 'a', 'ắ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
        'ầ' => 'a', 'ấ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
        'è' => 'e', 'é' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e', 'ê' => 'e',
        'ề' => 'e', 'ế' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
        'ì' => 'i', 'í' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
        'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ộ' => 'o',
        'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
        'ừ' => 'u', 'ứ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
        'ỳ' => 'y', 'ý' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
    ];

    /**
     * Mã quyền (dạng dùng trong code) chỉ Chủ cửa hàng được giữ.
     *
     * @return list<string>
     */
    public static function ownerOnlyPermissions(): array
    {
        return QuyenMapper::ownerOnlyCodes();
    }

    protected $table = 'VaiTro';

    protected $primaryKey = 'VaiTroID';

    public $timestamps = false;

    /** Giữ nguyên tên cột tiếng Việt khi sinh SQL. */
    public static $snakeAttributes = false;

    protected $fillable = ['TenVaiTro', 'MoTa', 'TrangThai', 'VaiTroID'];

    protected $casts = [
        'VaiTroID' => 'integer',
    ];

    /* ---------------------------------------------------------------------
     | Quan hệ
     |--------------------------------------------------------------------*/

    public function taiKhoanVaiTros(): HasMany
    {
        return $this->hasMany(TaiKhoanVaiTro::class, 'VaiTroID');
    }

    public function vaiTroQuyens(): HasMany
    {
        return $this->hasMany(VaiTroQuyen::class, 'VaiTroID');
    }

    /**
     * Mã quyền (`Quyen`) đang được cấp cho vai trò này.
     */
    public function quyens(): BelongsToMany
    {
        return $this->belongsToMany(
            Quyen::class,
            'VaiTro_Quyen',
            'VaiTroID',
            'QuyenID',
            'VaiTroID',
            'QuyenID',
        );
    }

    public function taiKhoans(): BelongsToMany
    {
        return $this->belongsToMany(
            TaiKhoan::class,
            'TaiKhoan_VaiTro',
            'VaiTroID',
            'TaiKhoanID',
        );
    }

    /* ---------------------------------------------------------------------
     | Tiện ích cho Blade / Service
     |
     | Blade ma trận phân quyền và các test cũ dùng tên tiếng Anh
     | (`name`, `description`, `slug`) trong khi cột DB là tiếng Việt.
     |--------------------------------------------------------------------*/

    public function getNameAttribute(): string
    {
        return (string) $this->TenVaiTro;
    }

    public function getDescriptionAttribute(): string
    {
        return (string) ($this->MoTa ?? '');
    }

    /**
     * Tên vai trò không dấu, dùng làm khoá cho ô input của ma trận quyền.
     *
     * Bỏ dấu tiếng Việt thủ công thay vì dùng `Str::slug()` vì hàm đó cần
     * ext-intl, thiếu thì mọi vai trò ra cùng một slug.
     */
    public function getSlugAttribute(): string
    {
        $text = strtr(mb_strtolower(trim((string) $this->TenVaiTro)), self::VIETNAMESE_BASES);

        // Bỏ dấu thanh và dấu phụ còn sót lại (u + tone, o + horn, ...).
        $text = preg_replace('/[̀-ͯ]/u', '', $text) ?? $text;

        $slug = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim($slug, '-') ?: 'khac';
    }

    public function isOwner(): bool
    {
        return mb_strtolower(trim((string) $this->TenVaiTro)) === mb_strtolower(self::OWNER);
    }

    /**
     * Vai trò này có được phép giữ mã quyền trong ma trận không.
     *
     * Quyền đặc biệt (sửa/xoá bản ghi đã quyết toán, hoàn tiền, quản lý vai trò)
     * bị từ chối với mọi vai trò khác Chủ cửa hàng, kể cả khi payload gửi lên
     * cố gán.
     */
    public function mayHold(string $permissionCode): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        return ! QuyenMapper::isOwnerOnly($permissionCode);
    }

    /**
     * Danh sách mã quyền (`MaQuyen`) đang được cấp.
     *
     * @return list<string>
     */
    public function permissionMaQuyens(): array
    {
        return $this->quyens->pluck('MaQuyen')->all();
    }

    /**
     * Mã quyền mà vai trò này thực sự có, đã tính cả quyền được `*_MANAGE` bao
     * trùm (dùng để tô sẵn ô tích chọn trên ma trận).
     *
     * @return list<string>
     */
    public function coveredPermissionCodes(): array
    {
        return QuyenMapper::coveredCodes($this->permissionMaQuyens());
    }
}
