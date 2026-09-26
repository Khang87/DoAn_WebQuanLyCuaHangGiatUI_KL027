<?php

namespace Tests;

use App\Models\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacIfNeeded();
    }

    /**
     * Nạp sẵn vai trò + quyền hạn để test chạy trên đúng ngữ cảnh RBAC thật,
     * thay vì bỏ trống bảng `roles` / `permissions` khiến mọi quyền đều bị từ chối.
     */
    private function seedRbacIfNeeded(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        if (Role::query()->exists()) {
            return;
        }

        $this->seed(RoleAndPermissionSeeder::class);
    }
}
