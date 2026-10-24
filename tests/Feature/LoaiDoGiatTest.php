<?php

namespace Tests\Feature;

use App\Models\LoaiDoGiat;
use App\Models\User;
use App\Services\LoaiDoGiatService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoaiDoGiatTest extends TestCase
{
    private bool $testSchemaCreated = false;

    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();

        // Fail before Tests\TestCase can inspect or seed RBAC tables.
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('LoaiDoGiat tests require isolated SQLite in-memory storage.');
        }

        Http::preventStrayRequests();
        Http::fake();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('DanhMucLoaiDoGiat', function (Blueprint $table): void {
            $table->bigIncrements('DanhMucID');
            $table->string('TenDanhMuc', 100);
            $table->text('MoTa')->nullable();
            $table->string('TrangThai', 30)->default('Hoạt động');
            $table->dateTime('NgayTao')->useCurrent();
        });

        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
            $table->string('TenLoaiDoGiat', 150)->unique();
            $table->string('MoTa', 255)->nullable();
            $table->string('TrangThai', 30)->default('Hoạt động');
            $table->unsignedBigInteger('DanhMucID');
        });
        DB::table('DanhMucLoaiDoGiat')->insert([
            'DanhMucID' => 1,
            'TenDanhMuc' => 'Đồ mặc',
            'TrangThai' => 'Hoạt động',
        ]);

        foreach (['BangGia', 'ChiTietDonHang', 'ChiTietBooking', 'BookingYeuCauDichVu'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->increments('ReferenceID');
                $table->unsignedInteger('LoaiDoGiatID')->nullable();
            });
        }

        Schema::create('BookingKiemKe', function (Blueprint $table): void {
            $table->increments('BookingKiemKeID');
            $table->json('DuLieuThucTe');
        });

        $this->testSchemaCreated = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->testSchemaCreated && $this->app) {
                $connection = $this->app['db']->connection();
                if ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:') {
                    Schema::dropIfExists('BookingYeuCauDichVu');
                    Schema::dropIfExists('BookingKiemKe');
                    Schema::dropIfExists('ChiTietBooking');
                    Schema::dropIfExists('ChiTietDonHang');
                    Schema::dropIfExists('BangGia');
                    Schema::dropIfExists('LoaiDoGiat');
                    Schema::dropIfExists('DanhMucLoaiDoGiat');
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_model_matches_pascal_case_schema_and_declares_foreign_key_relations(): void
    {
        $category = new LoaiDoGiat;

        $this->assertSame('LoaiDoGiat', $category->getTable());
        $this->assertSame('LoaiDoGiatID', $category->getKeyName());
        $this->assertTrue($category->getIncrementing());
        $this->assertFalse($category->usesTimestamps());
        $this->assertSame(['TenLoaiDoGiat', 'MoTa', 'TrangThai', 'DanhMucID'], $category->getFillable());
        $this->assertSame('LoaiDoGiatID', $category->chiTietDonHangs()->getForeignKeyName());
        $this->assertSame('LoaiDoGiatID', $category->bangGias()->getForeignKeyName());
        $this->assertSame('LoaiDoGiatID', $category->chiTietBookings()->getForeignKeyName());
        $this->assertSame('DanhMucID', $category->danhMuc()->getForeignKeyName());
    }

    public function test_category_filter_limits_garment_types_to_selected_catalog_category(): void
    {
        DB::table('DanhMucLoaiDoGiat')->insert([
            ['DanhMucID' => 10, 'TenDanhMuc' => 'Quần áo'],
            ['DanhMucID' => 20, 'TenDanhMuc' => 'Chăn ga'],
        ]);
        DB::table('LoaiDoGiat')->insert([
            ['TenLoaiDoGiat' => 'Áo sơ mi', 'DanhMucID' => 10],
            ['TenLoaiDoGiat' => 'Chăn mỏng', 'DanhMucID' => 20],
        ]);

        $garments = app(LoaiDoGiatService::class)->getAll(['category_id' => 10])
            ->getCollection();

        $this->assertSame(['Áo sơ mi'], $garments->pluck('TenLoaiDoGiat')->all());
    }

    public function test_category_can_be_created_listed_updated_and_deleted(): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo sơ mi',
            'MoTa' => 'Đồ cần giặt riêng',
            'DanhMucID' => 1,
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
        $this->assertTrue(app(LoaiDoGiatService::class)->delete($category->fresh()));
        $this->assertDatabaseMissing('LoaiDoGiat', ['LoaiDoGiatID' => $category->LoaiDoGiatID]);
    }

    public function test_categories_referenced_by_orders_or_booking_details_are_suspended_not_deleted(): void
    {
        foreach (['ChiTietDonHang', 'ChiTietBooking'] as $tableName) {
            $category = app(LoaiDoGiatService::class)->create([
                'TenLoaiDoGiat' => 'Loại '.$tableName,
                'DanhMucID' => 1,
            ]);
            DB::table($tableName)->insert(['LoaiDoGiatID' => $category->LoaiDoGiatID]);
            DB::table('BangGia')->insert(['LoaiDoGiatID' => $category->LoaiDoGiatID]);

            $this->assertFalse(app(LoaiDoGiatService::class)->delete($category));
            $this->assertDatabaseHas('LoaiDoGiat', [
                'LoaiDoGiatID' => $category->LoaiDoGiatID,
                'TrangThai' => 'Tạm ngưng',
            ]);
            $this->assertDatabaseHas($tableName, ['LoaiDoGiatID' => $category->LoaiDoGiatID]);
            $this->assertDatabaseHas('BangGia', ['LoaiDoGiatID' => $category->LoaiDoGiatID]);
        }
    }

    public function test_price_only_garment_and_its_prices_are_hard_deleted(): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo chỉ có bảng giá',
            'DanhMucID' => 1,
        ]);
        $other = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo khác',
            'DanhMucID' => 1,
        ]);
        DB::table('BangGia')->insert([
            ['LoaiDoGiatID' => $category->getKey()],
            ['LoaiDoGiatID' => $category->getKey()],
            ['LoaiDoGiatID' => $other->getKey()],
        ]);

        $this->assertTrue(app(LoaiDoGiatService::class)->delete($category));
        $this->assertNull($category->fresh());
        $this->assertDatabaseMissing('BangGia', ['LoaiDoGiatID' => $category->getKey()]);
        $this->assertDatabaseHas('BangGia', ['LoaiDoGiatID' => $other->getKey()]);
        $this->assertSame('Hoạt động', $other->fresh()->TrangThai);
        $this->assertDatabaseHas('DanhMucLoaiDoGiat', ['DanhMucID' => 1, 'TrangThai' => 'Hoạt động']);
    }

    #[DataProvider('inspectionHistoryVersions')]
    public function test_inspection_only_history_suspends_garment_and_preserves_snapshot(bool $previousVersion): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo có lịch sử kiểm kê',
            'DanhMucID' => 1,
        ]);
        $payload = ['items' => [['details' => ['LoaiDoGiatID' => $previousVersion ? (string) $category->getKey() : $category->getKey()]]]];
        if ($previousVersion) {
            $payload = ['items' => [], '_previous_versions' => [
                ['items' => [], '_previous_versions' => [$payload]],
            ]];
        }
        $snapshot = json_encode($payload, JSON_THROW_ON_ERROR);
        DB::table('BookingKiemKe')->insert(['DuLieuThucTe' => $snapshot]);
        DB::table('BangGia')->insert(['LoaiDoGiatID' => $category->getKey()]);

        $this->assertFalse(app(LoaiDoGiatService::class)->delete($category));
        $this->assertSame('Tạm ngưng', $category->fresh()->TrangThai);
        $this->assertDatabaseHas('BangGia', ['LoaiDoGiatID' => $category->getKey()]);
        $this->assertSame($snapshot, DB::table('BookingKiemKe')->value('DuLieuThucTe'));
        $this->assertDatabaseCount('ChiTietDonHang', 0);
        $this->assertDatabaseCount('ChiTietBooking', 0);
    }

    public static function inspectionHistoryVersions(): array
    {
        return [
            'current inspection with numeric ID' => [false],
            'nested previous versions with string ID' => [true],
        ];
    }

    public function test_actual_deletion_failure_throws_and_rolls_back_price_removal(): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo không xóa được',
            'DanhMucID' => 1,
        ]);
        DB::table('BangGia')->insert(['LoaiDoGiatID' => $category->getKey()]);
        DB::unprepared('CREATE TRIGGER prevent_garment_delete BEFORE DELETE ON "LoaiDoGiat" BEGIN SELECT RAISE(ABORT, \'test deletion failure\'); END');

        try {
            app(LoaiDoGiatService::class)->delete($category);
            $this->fail('An actual deletion failure must throw, not return false.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test deletion failure', $exception->getMessage());
        }

        $this->assertSame('Hoạt động', $category->fresh()->TrangThai);
        $this->assertDatabaseHas('BangGia', ['LoaiDoGiatID' => $category->getKey()]);
    }

    public function test_deleting_unreferenced_category_shows_success_flash(): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo không liên quan',
            'DanhMucID' => 1,
        ]);
        $employee = $this->userWithPermissions('staff', ['garment_categories.delete']);

        $this->actingAs($employee)
            ->delete(route('loaidogiat.destroy', $category->LoaiDoGiatID))
            ->assertRedirect(route('loaidogiat.index'))
            ->assertSessionHas('success', 'Loại đồ giặt đã được xóa.')
            ->assertSessionMissing('error');

        $this->assertDatabaseMissing('LoaiDoGiat', ['LoaiDoGiatID' => $category->LoaiDoGiatID]);
    }

    public function test_deleting_referenced_category_shows_success_flash_for_suspension(): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo đang dùng',
            'DanhMucID' => 1,
        ]);
        DB::table('ChiTietBooking')->insert([
            'LoaiDoGiatID' => $category->LoaiDoGiatID,
        ]);
        $employee = $this->userWithPermissions('staff', ['garment_categories.delete']);

        $this->actingAs($employee)
            ->delete(route('loaidogiat.destroy', $category->LoaiDoGiatID))
            ->assertRedirect(route('loaidogiat.index'))
            ->assertSessionHas('success', "Loại đồ giặt đã được chuyển sang trạng thái 'Tạm ngưng' để bảo toàn lịch sử đơn hàng/Booking.")
            ->assertSessionMissing('error');

        $this->assertDatabaseHas('LoaiDoGiat', [
            'LoaiDoGiatID' => $category->LoaiDoGiatID,
            'TrangThai' => 'Tạm ngưng',
        ]);
        $this->assertDatabaseHas('ChiTietBooking', ['LoaiDoGiatID' => $category->getKey()]);
    }

    public function test_index_confirmation_matches_history_and_includes_inactive_garments(): void
    {
        $used = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo đã đặt lịch',
            'DanhMucID' => 1,
        ]);
        $unused = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo mới có bảng giá',
            'DanhMucID' => 1,
        ]);
        $inactive = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo kiểm kê tạm ngưng',
            'DanhMucID' => 1,
            'TrangThai' => 'Tạm ngưng',
        ]);
        DB::table('ChiTietBooking')->insert(['LoaiDoGiatID' => $used->getKey()]);
        DB::table('BangGia')->insert(['LoaiDoGiatID' => $unused->getKey()]);
        DB::table('BookingKiemKe')->insert(['DuLieuThucTe' => json_encode([
            'items' => [], '_previous_versions' => [['items' => [['LoaiDoGiatID' => (string) $inactive->getKey()]]]],
        ], JSON_THROW_ON_ERROR)]);

        $response = $this->actingAs($this->userWithPermissions('staff', [
            'garment_categories.view', 'garment_categories.delete',
        ]))->get(route('loaidogiat.index'));

        $response->assertOk()
            ->assertSee($used->TenLoaiDoGiat)
            ->assertSee($unused->TenLoaiDoGiat)
            ->assertSee($inactive->TenLoaiDoGiat)
            ->assertViewHas('categories', fn ($categories): bool => $categories->getCollection()->pluck('LoaiDoGiatID')->all() === [
                $used->getKey(), $unused->getKey(), $inactive->getKey(),
            ]);

        foreach ([$used, $inactive] as $garment) {
            $text = $this->confirmationText($response->getContent(), route('loaidogiat.destroy', $garment));
            $this->assertStringContainsString("'Tạm ngưng'", $text);
            $this->assertStringContainsString('đơn hàng/Booking', $text);
            $this->assertStringContainsString('bảo toàn dữ liệu lịch sử', $text);
            $this->assertStringNotContainsString('xóa vĩnh viễn', $text);
        }
        $text = $this->confirmationText($response->getContent(), route('loaidogiat.destroy', $unused));
        $this->assertStringContainsString('xóa vĩnh viễn', $text);
        $this->assertStringContainsString('Bảng giá liên quan cũng sẽ bị xóa.', $text);
        $this->assertStringNotContainsString('Tạm ngưng', $text);
    }

    public function test_view_only_employee_cannot_see_or_submit_delete_confirmation(): void
    {
        $category = app(LoaiDoGiatService::class)->create([
            'TenLoaiDoGiat' => 'Áo chỉ được xem',
            'DanhMucID' => 1,
        ]);
        $this->actingAs($this->userWithPermissions('staff', ['garment_categories.view']))
            ->get(route('loaidogiat.index'))
            ->assertOk()
            ->assertSee($category->TenLoaiDoGiat)
            ->assertSee(route('loaidogiat.show', $category), false)
            ->assertDontSee('data-confirm-form', false)
            ->assertDontSee(route('loaidogiat.edit', $category), false);

        $this->delete(route('loaidogiat.destroy', $category))->assertForbidden();
        $this->assertSame('Hoạt động', $category->fresh()->TrangThai);
    }

    public function test_category_names_must_be_unique(): void
    {
        DB::table('LoaiDoGiat')->insert([
            'TenLoaiDoGiat' => 'Áo khoác',
            'TrangThai' => 'Hoạt động',
            'DanhMucID' => 1,
        ]);

        $this->actingAs($this->userWithPermissions('staff', ['garment_categories.create']))
            ->post(route('loaidogiat.store'), [
                'TenLoaiDoGiat' => 'Áo khoác',
                'TrangThai' => 'Hoạt động',
                'DanhMucID' => 1,
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
            ->from(route('loaidogiat.create'))
            ->post(route('loaidogiat.store'), [
                'TenLoaiDoGiat' => 'Chăn mỏng',
                'TrangThai' => 'Hoạt động',
                'DanhMucID' => 1,
            ])
            ->assertRedirect(route('loaidogiat.index'));

        $category = LoaiDoGiat::query()->firstOrFail();

        $this->actingAs($employee)
            ->put(route('loaidogiat.update', $category->LoaiDoGiatID), [
                'DanhMucID' => 1,
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
                'DanhMucID' => 1,
            ])
            ->assertRedirect(route('loaidogiat.index'));

        $this->assertDatabaseHas('LoaiDoGiat', [
            'TenLoaiDoGiat' => 'Vải lụa',
            'DanhMucID' => 1,
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

    public function test_category_creation_requires_a_valid_parent_category_and_persists_the_selection(): void
    {
        $employee = $this->userWithPermissions('staff', ['garment_categories.create']);

        $this->actingAs($employee)
            ->from(route('loaidogiat.create'))
            ->post(route('loaidogiat.store'), [
                'TenLoaiDoGiat' => 'Áo len',
                'TrangThai' => 'Hoạt động',
            ])
            ->assertRedirect(route('loaidogiat.create'))
            ->assertSessionHasErrors('DanhMucID');

        $this->actingAs($employee)
            ->post(route('loaidogiat.store'), [
                'TenLoaiDoGiat' => 'Áo len',
                'TrangThai' => 'Hoạt động',
                'DanhMucID' => 1,
            ])
            ->assertRedirect(route('loaidogiat.index'))
            ->assertSessionHas('success', 'Loại đồ giặt đã được tạo thành công.');

        $this->assertDatabaseHas('LoaiDoGiat', [
            'TenLoaiDoGiat' => 'Áo len',
            'DanhMucID' => 1,
            'TrangThai' => 'Hoạt động',
        ]);
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
        $this->assertSame('garment-categories', $routes->getByName('garment-categories.index')->uri());
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

        foreach (['/garments', '/laundry-categories'] as $legacyPath) {
            $this->actingAs($employee)
                ->get($legacyPath)
                ->assertRedirect(route('loaidogiat.index'));
        }
    }

    private function confirmationText(string $html, string $action): string
    {
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="'.$action.'"]');

        $this->assertCount(1, $forms);
        $form = $forms->item(0);
        $this->assertTrue($form->hasAttribute('data-confirm-form'));
        $this->assertSame('POST', $form->getAttribute('method'));
        $this->assertCount(1, $xpath->query('.//input[@name="_method" and @value="DELETE"]', $form));

        return $form->getAttribute('data-confirm-text');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(string $role, array $permissions): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['TenDangNhap' => 'Catalog tester', 'AvatarURL' => '/assets/images/user_1.jpg']);
        $user->setRelation('nhanVien', null);
        $user->setRelation('khachHang', null);
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isCustomer')->andReturn(false);
        $user->shouldReceive('roleSlug')->andReturn($role);
        $user->shouldReceive('canPermission')
            ->andReturnUsing(fn (string $permission): bool => in_array($permission, $permissions, true));

        return $user;
    }
}
