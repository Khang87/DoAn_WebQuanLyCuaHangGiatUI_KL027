<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Models\LoaiDichVu;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceCategoryActionLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('LoaiDichVu', function (Blueprint $table): void {
            $table->increments('LoaiDichVuID');
            $table->string('TenLoaiDichVu');
            $table->string('MoTa')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('LoaiDichVu');

        parent::tearDown();
    }

    public function test_service_category_list_uses_the_shared_action_layout_and_preserves_routes(): void
    {
        Gate::before(static fn (?User $user): bool => true);
        $category = LoaiDichVu::query()->create([
            'TenLoaiDichVu' => 'Danh mục dịch vụ có tên rất dài để kiểm tra bố cục',
            'TrangThai' => 'Hoạt động',
        ]);

        $html = view('admin.service-categories.index', [
            'categories' => LoaiDichVu::query()->paginate(10),
            'statuses' => RecordStatus::databaseOptions(),
        ])->render();

        $this->assertStringContainsString('table-custom category-table mb-0', $html);
        $this->assertStringContainsString('category-actions-column', $html);
        $this->assertStringContainsString('d-flex align-items-center gap-2', $html);
        $this->assertStringContainsString('btn btn-order-action view', $html);
        $this->assertStringContainsString('btn btn-order-action edit', $html);
        $this->assertStringContainsString('btn btn-order-action delete', $html);
        $this->assertStringNotContainsString('category-action--', $html);
        $this->assertStringContainsString(route('service-categories.show', $category), $html);
        $this->assertStringContainsString(route('service-categories.edit', $category), $html);
        $this->assertStringContainsString(route('service-categories.destroy', $category), $html);
        $this->assertStringContainsString('data-confirm-form', $html);
    }

    public function test_service_category_action_column_keeps_its_width_when_actions_are_not_permitted(): void
    {
        Gate::before(static fn (?User $user): bool => false);
        $category = LoaiDichVu::query()->create([
            'TenLoaiDichVu' => 'Danh mục dịch vụ dài để kiểm tra bố cục',
            'TrangThai' => 'Hoạt động',
        ]);

        $html = view('admin.service-categories.index', [
            'categories' => LoaiDichVu::query()->paginate(10),
            'statuses' => RecordStatus::databaseOptions(),
        ])->render();

        $this->assertStringContainsString('class="category-actions-column"', $html);
        $this->assertStringNotContainsString(route('service-categories.show', $category), $html);
        $this->assertStringNotContainsString(route('service-categories.edit', $category), $html);
        $this->assertStringNotContainsString(route('service-categories.destroy', $category), $html);
    }
}
