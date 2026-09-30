<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserAuthorizationStatusTest extends TestCase
{
    public function test_inactive_account_cannot_use_permission_or_owner_bypass(): void
    {
        $user = new User([
            'TaiKhoanID' => 42,
            'TrangThai' => 'Khóa',
        ]);

        $this->assertFalse($user->isOwner());
        $this->assertFalse($user->canPermission('roles.manage'));
    }
}
