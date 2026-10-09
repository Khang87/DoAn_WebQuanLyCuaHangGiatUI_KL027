<?php

namespace Tests;

use App\Models\VaiTro;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Asset execution is verified separately by the real-browser production-build suite.
        $this->withoutVite();
        $this->seedRbacIfNeeded();
    }

    /**
     * Nạp sẵn vai trò + quyền hạn để test chạy trên đúng ngữ cảnh RBAC thật,
     * thay vì bỏ trống bảng `roles` / `permissions` khiến mọi quyền đều bị từ chối.
     */
    private function seedRbacIfNeeded(): void
    {
        if (! Schema::hasTable('VaiTro')) {
            return;
        }

        if (VaiTro::query()->exists()) {
            return;
        }

        $this->seed(RoleAndPermissionSeeder::class);
    }
}
