<?php

namespace Tests\Feature;

use App\Models\LoaiDoGiat;
use App\Models\User;
use App\Services\LoaiDoGiatService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class LoaiDoGiatTest extends TestCase
{
    private bool $testSchemaCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->markTestSkipped('LoaiDoGiat tests require isolated SQLite in-memory storage.');
        }

        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
            $table->string('TenLoaiDoGiat', 150)->unique();
            $table->string('MoTa', 255)->nullable();
            $table->string('TrangThai', 30)->default('Hoạt động');
        });

        foreach (['BangGia', 'ChiTietDonHang', 'ChiTietBooking'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->increments('ReferenceID');
                $table->unsignedInteger('LoaiDoGiatID')->nullable();
            });
        }

        $this->testSchemaCreated = true;
    }

    protected function tearDown(): void
    {
        if ($this->testSchemaCreated) {
            Schema::dropIfExists('ChiTietBooking');
            Schema::dropIfExists('ChiTietDonHang');
            Schema::dropIfExists('BangGia');
            Schema::dropIfExists('LoaiDoGiat');
        }

        parent::tearDown();
    }

    public function test_model_matches_pascal_case_schema_and_declares_foreign_key_relations(): void
    {
        $category = new LoaiDoGiat;

        $this->assertSame('LoaiDoGiat', $category->getTable());
        $this->assertSame('LoaiDoGiatID', $category->getKeyName());
        $this->assertTrue($category->getIncrementing());
        $this->assertFalse($category->usesTimestamps());
        $this->assertSame(['TenLoaiDoGiat', 'MoTa', 'TrangThai'], $category->getFillable());
        $this->assertSame('LoaiDoGiatID', $category->chiTietDonHangs()->getForeignKeyName());
        $this->assertSame('LoaiDoGiatID', $category->bangGias()->getForeignKeyName());
        $this->assertSame('LoaiDoGiatID', $category->chiTietBookings()->getForeignKeyName());
    }

    public function test_category_can_be_created_listed_updated_and_deleted(): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo sơ mi',
            'MoTa' => 'Đồ cần giặt riêng',
        ]);

        $this->assertSame('Áo sơ mi', $category->TenLoaiDoGiat);
        $this->assertSame('Hoạt động', $category->TrangThai);
        $this->assertSame(
            [$category->LoaiDoGiatID],
            app(LoaiDoGiatService::class)->getAll()
                ->getCollection()
                ->pluck('LoaiDoGiatID')
                ->all(),
        );

        app(LoaiDoGiatService::class)->update($category, [
            'TenLoaiDoGiat' => 'Áo sơ mi cao cấp',
            'TrangThai' => 'Tạm ngưng',
        ]);

        $this->assertSame('Áo sơ mi cao cấp', $category->fresh()->TenLoaiDoGiat);
        $this->assertSame('Tạm ngưng', $category->fresh()->TrangThai);
        $this->assertNull(app(LoaiDoGiatService::class)->delete($category->fresh()));
        $this->assertDatabaseMissing('LoaiDoGiat', ['LoaiDoGiatID' => $category->LoaiDoGiatID]);
    }

    public function test_categories_referenced_by_prices_orders_or_booking_details_are_disabled_not_deleted(): void
    {
        foreach (['BangGia', 'ChiTietDonHang', 'ChiTietBooking'] as $tableName) {
            $category = app(LoaiDoGiatService::class)->create([
                'TenLoaiDoGiat' => 'Loại '.$tableName,
            ]);
            DB::table($tableName)->insert(['LoaiDoGiatID' => $category->LoaiDoGiatID]);

            $error = app(LoaiDoGiatService::class)->delete($category);

            $this->assertStringContainsString('Không thể xóa loại đồ giặt vì còn dữ liệu liên quan:', $error);
            $this->assertDatabaseHas('LoaiDoGiat', [
                'LoaiDoGiatID' => $category->LoaiDoGiatID,
                'TrangThai' => 'Tạm ngưng',
            ]);
        }
    }

    public function test_deleting_unreferenced_category_shows_success_flash(): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo không liên quan',
        ]);
        $employee = $this->userWithPermissions('staff', ['garment_categories.delete']);

        $this->actingAs($employee)
            ->delete(route('loaidogiat.destroy', $category->LoaiDoGiatID))
            ->assertRedirect(route('loaidogiat.index'))
            ->assertSessionHas('success', 'Loại đồ giặt đã được xóa.')
            ->assertSessionMissing('error');

        $this->assertDatabaseMissing('LoaiDoGiat', ['LoaiDoGiatID' => $category->LoaiDoGiatID]);
    }

    public function test_deleting_referenced_category_shows_error_flash_with_related_record_types(): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo đang dùng',
        ]);
        DB::table('ChiTietBooking')->insert([
            'LoaiDoGiatID' => $category->LoaiDoGiatID,
        ]);
        $employee = $this->userWithPermissions('staff', ['garment_categories.delete']);

        $this->actingAs($employee)
            ->delete(route('loaidogiat.destroy', $category->LoaiDoGiatID))
            ->assertRedirect(route('loaidogiat.index'))
            ->assertSessionHas('error', 'Không thể xóa loại đồ giặt vì còn dữ liệu liên quan: 1 chi tiết lịch hẹn. Loại đồ đã được tạm ngưng.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('LoaiDoGiat', [
            'LoaiDoGiatID' => $category->LoaiDoGiatID,
            'TrangThai' => 'Tạm ngưng',
        ]);
    }

    public function test_category_names_must_be_unique(): void
    {
        DB::table('LoaiDoGiat')->insert([
            'TenLoaiDoGiat' => 'Áo khoác',
            'TrangThai' => 'Hoạt động',
        ]);

        $this->actingAs($this->userWithPermissions('staff', ['garment_categories.create']))
            ->post(route('loaidogiat.store'), [
                'TenLoaiDoGiat' => 'Áo khoác',
                'TrangThai' => 'Hoạt động',
            ])
            ->assertSessionHasErrors('TenLoaiDoGiat');
    }

    public function test_employee_with_category_permissions_can_create_and_change_status(): void
    {
        $employee = $this->userWithPermissions('staff', [
            'garment_categories.create',
            'garment_categories.edit',
        ]);

        $this->actingAs($employee)
            ->post(route('loaidogiat.store'), [
                'TenLoaiDoGiat' => 'Chăn mỏng',
                'TrangThai' => 'Hoạt động',
            ])
            ->assertRedirect(route('loaidogiat.index'));

        $category = LoaiDoGiat::query()->firstOrFail();

        $this->actingAs($employee)
            ->put(route('loaidogiat.update', $category->LoaiDoGiatID), [
                'TenLoaiDoGiat' => 'Chăn mỏng',
                'TrangThai' => 'Tạm ngưng',
            ])
            ->assertRedirect(route('loaidogiat.index'));

        $this->assertSame('Tạm ngưng', $category->fresh()->TrangThai);
    }

    public function test_owner_with_category_permissions_can_create_categories(): void
    {
        $owner = $this->userWithPermissions('owner', ['garment_categories.create']);

        $this->actingAs($owner)
            ->post(route('loaidogiat.store'), [
                'TenLoaiDoGiat' => 'Vải lụa',
                'TrangThai' => 'Hoạt động',
            ])
            ->assertRedirect(route('loaidogiat.index'));

        $this->assertDatabaseHas('LoaiDoGiat', [
            'TenLoaiDoGiat' => 'Vải lụa',
            'TrangThai' => 'Hoạt động',
        ]);
    }

    public function test_employee_without_required_permission_cannot_manage_categories(): void
    {
        $employee = $this->userWithPermissions('staff', []);

        $this->actingAs($employee)
            ->post(route('loaidogiat.store'), [
                'TenLoaiDoGiat' => 'Quần tây',
                'TrangThai' => 'Hoạt động',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('LoaiDoGiat', 0);
    }

    public function test_manager_role_is_not_granted_category_access_by_role_alone(): void
    {
        $manager = $this->userWithPermissions('manager', []);

        $this->actingAs($manager)
            ->post(route('loaidogiat.store'), [
                'TenLoaiDoGiat' => 'Vải cotton',
                'TrangThai' => 'Hoạt động',
            ])
            ->assertForbidden();
    }

    public function test_category_resource_uses_action_specific_permissions_without_role_middleware(): void
    {
        $routes = app('router')->getRoutes();
        $indexRoute = $routes->getByName('loaidogiat.index');

        $this->assertSame('loai-do-giat', $indexRoute->uri());
        $this->assertNull($routes->getByName('garment-categories.index'));
        $this->assertNull($routes->getByName('laundry-categories.index'));

        $this->assertNotContains('role:admin|staff|employee', $indexRoute->getAction('middleware'));
        $this->assertContains(
            'permission:garment_categories.view',
            $indexRoute->getAction('middleware'),
        );
        $this->assertContains(
            'permission:garment_categories.create',
            $routes->getByName('loaidogiat.store')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:garment_categories.edit',
            $routes->getByName('loaidogiat.update')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:garment_categories.delete',
            $routes->getByName('loaidogiat.destroy')->getAction('middleware'),
        );
    }

    public function test_legacy_category_pages_redirect_to_the_single_canonical_module(): void
    {
        $employee = $this->userWithPermissions('staff', ['garment_categories.view']);

        foreach (['/garments', '/garment-categories', '/laundry-categories'] as $legacyPath) {
            $this->actingAs($employee)
                ->get($legacyPath)
                ->assertRedirect(route('loaidogiat.index'));
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(string $role, array $permissions): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isCustomer')->andReturn(false);
        $user->shouldReceive('roleSlug')->andReturn($role);
        $user->shouldReceive('canPermission')
            ->andReturnUsing(fn (string $permission): bool => in_array($permission, $permissions, true));

        return $user;
    }
}
