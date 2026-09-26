<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'code', 'group'])]
class Permission extends Model
{
    use HasFactory;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_permissions');
    }

    /** Quyền này có bị giới hạn chỉ cho Chủ cửa hàng hay không. */
    public function isOwnerOnly(): bool
    {
        return in_array($this->code, Role::OWNER_ONLY_PERMISSIONS, true);
    }
}
