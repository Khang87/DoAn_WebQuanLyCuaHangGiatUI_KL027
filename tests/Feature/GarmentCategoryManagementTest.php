<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Models\DanhMucLoaiDoGiat;
use App\Models\LoaiDoGiat;
use App\Models\User;
use App\Services\GarmentCategoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GarmentCategoryManagementTest extends TestCase
{
    private bool $testSchemaCreated = false;

    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();

        // Fail before Tests\TestCase can inspect or seed RBAC tables.
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Garment category tests require isolated SQLite in-memory storage.');
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
            $table->string('TenLoaiDoGiat', 150);
            $table->string('MoTa', 255)->nullable();
            $table->string('TrangThai', 30)->default('Hoạt động');
            $table->unsignedBigInteger('DanhMucID');
            $table->foreign('DanhMucID')
                ->references('DanhMucID')
                ->on('DanhMucLoaiDoGiat')
                ->restrictOnDelete();
        });

        foreach (['BangGia', 'ChiTietDonHang', 'ChiTietBooking', 'BookingYeuCauDichVu'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->increments('ReferenceID');
                $table->unsignedInteger('LoaiDoGiatID')->nullable();
                $table->foreign('LoaiDoGiatID')
                    ->references('LoaiDoGiatID')
                    ->on('LoaiDoGiat')
                    ->restrictOnDelete();
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

    public function test_existing_category_model_matches_schema_and_relates_garment_types(): void
    {
        $category = new DanhMucLoaiDoGiat;

        $this->assertSame('DanhMucLoaiDoGiat', $category->getTable());
        $this->assertSame('DanhMucID', $category->getKeyName());
        $this->assertFalse($category->usesTimestamps());
        $this->assertSame('DanhMucID', $category->loaiDoGiats()->getForeignKeyName());
    }

    public function test_categories_can_be_created_filtered_updated_and_deleted_without_ddl(): void
    {
        $service = app(GarmentCategoryService::class);
        $clothing = $service->create([
            'TenDanhMuc' => 'Quần áo',
            'MoTa' => 'Trang phục thường ngày',
            'TrangThai' => 'Hoạt động',
        ]);
        $bedding = $service->create([
            'TenDanhMuc' => 'Chăn ga',
            'TrangThai' => 'Tạm ngưng',
        ]);

        $service->update($clothing, ['TenDanhMuc' => 'Quần áo thường ngày']);

        $this->assertSame(
            ['Quần áo thường ngày'],
            $service->getAll(['search' => (string) $clothing->DanhMucID, 'status' => 'Hoạt động'])
                ->getCollection()
                ->pluck('TenDanhMuc')
                ->all(),
        );
        $this->assertTrue($service->delete($bedding));
        $this->assertNull($bedding->fresh());
    }

    public function test_category_list_displays_creation_date_and_sorts_by_it(): void
    {
        DB::table('DanhMucLoaiDoGiat')->insert([
            [
                'DanhMucID' => 1,
                'TenDanhMuc' => 'Danh mục cũ',
                'TrangThai' => 'Hoạt động',
                'NgayTao' => '2026-10-01 08:15:00',
            ],
            [
                'DanhMucID' => 2,
                'TenDanhMuc' => 'Danh mục mới',
                'TrangThai' => 'Hoạt động',
                'NgayTao' => '2026-10-05 16:40:00',
            ],
        ]);

        $categories = app(GarmentCategoryService::class)->getAll(['sort' => 'latest']);
        $html = view('admin.garment-categories.index', [
            'categories' => $categories,
            'statuses' => RecordStatus::databaseOptions(),
        ])->render();

        $this->assertSame(
            ['Danh mục mới', 'Danh mục cũ'],
            $categories->getCollection()->pluck('TenDanhMuc')->all(),
        );
        $this->assertStringContainsString('<th class="fw-bold text-dark">Ngày tạo</th>', $html);
        $this->assertStringContainsString('05/10/2026 16:40', $html);
        $this->assertStringContainsString('01/10/2026 08:15', $html);
    }

    public function test_category_list_matches_pricing_action_styles_and_keeps_confirmation_routes(): void
    {
        Gate::before(static fn (?User $user): bool => true);
        $category = DanhMucLoaiDoGiat::query()->create([
            'TenDanhMuc' => 'Danh mục có tên rất dài để kiểm tra bố cục',
            'TrangThai' => 'Hoạt động',
        ]);

        $html = view('admin.garment-categories.index', [
            'categories' => app(GarmentCategoryService::class)->getAll(),
            'statuses' => RecordStatus::databaseOptions(),
        ])->render();

        $this->assertStringContainsString('table-custom category-table mb-0', $html);
        $this->assertStringContainsString('category-actions-column', $html);
        $this->assertStringContainsString('d-flex align-items-center gap-2', $html);
        $this->assertStringContainsString('btn btn-order-action view', $html);
        $this->assertStringContainsString('btn btn-order-action edit', $html);
        $this->assertStringContainsString('btn btn-order-action delete', $html);
        $this->assertStringNotContainsString('category-action--', $html);
        $this->assertStringContainsString(route('garment-categories.show', $category), $html);
        $this->assertStringContainsString(route('garment-categories.edit', $category), $html);
        $this->assertStringContainsString(route('garment-categories.destroy', $category), $html);
        $this->assertStringContainsString('data-confirm-form', $html);
    }

    public function test_category_action_column_keeps_its_width_when_actions_are_not_permitted(): void
    {
        Gate::before(static fn (?User $user): bool => false);
        $category = DanhMucLoaiDoGiat::query()->create([
            'TenDanhMuc' => 'Danh mục dài để kiểm tra nhóm thao tác không bị dịch chuyển',
            'TrangThai' => 'Hoạt động',
        ]);

        $html = view('admin.garment-categories.index', [
            'categories' => app(GarmentCategoryService::class)->getAll(),
            'statuses' => RecordStatus::databaseOptions(),
        ])->render();

        $this->assertStringContainsString('class="category-actions-column"', $html);
        $this->assertStringNotContainsString(route('garment-categories.show', $category), $html);
        $this->assertStringNotContainsString(route('garment-categories.edit', $category), $html);
        $this->assertStringNotContainsString(route('garment-categories.destroy', $category), $html);
    }

    public function test_category_in_use_is_suspended_with_all_children_and_remains_visible(): void
    {
        $category = DanhMucLoaiDoGiat::query()->create([
            'TenDanhMuc' => 'Quần áo',
            'TrangThai' => 'Hoạt động',
        ]);
        $used = LoaiDoGiat::query()->create([
            'TenLoaiDoGiat' => 'Áo sơ mi',
            'DanhMucID' => $category->DanhMucID,
            'TrangThai' => 'Hoạt động',
        ]);
        $unused = LoaiDoGiat::query()->create([
            'TenLoaiDoGiat' => 'Áo mới',
            'DanhMucID' => $category->DanhMucID,
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('ChiTietDonHang')->insert(['LoaiDoGiatID' => $used->getKey()]);
        DB::table('BangGia')->insert([
            ['LoaiDoGiatID' => $used->getKey()],
            ['LoaiDoGiatID' => $unused->getKey()],
        ]);

        $response = $this->actingAs($this->userWithPermissions([
            'garment_categories.view', 'garment_categories.delete',
        ]))->get(route('garment-categories.index'))->assertOk();
        $text = $this->confirmationText($response->getContent(), route('garment-categories.destroy', $category));
        $this->assertStringContainsString("'Tạm ngưng'", $text);
        $this->assertStringContainsString('đơn hàng/Booking', $text);
        $this->assertStringContainsString('bảo toàn dữ liệu lịch sử', $text);
        $this->assertStringContainsString('Các loại đồ giặt thuộc danh mục cũng sẽ được tạm ngưng.', $text);
        $this->assertStringNotContainsString('xóa vĩnh viễn', $text);

        $this->assertFalse(app(GarmentCategoryService::class)->delete($category));
        $this->assertSame('Tạm ngưng', $category->fresh()->TrangThai);
        $this->assertSame('Tạm ngưng', $used->fresh()->TrangThai);
        $this->assertSame('Tạm ngưng', $unused->fresh()->TrangThai);
        $this->assertDatabaseHas('ChiTietDonHang', ['LoaiDoGiatID' => $used->getKey()]);
        $this->assertDatabaseCount('BangGia', 2);

        $this->get(route('garment-categories.index'))
            ->assertOk()
            ->assertSee($category->TenDanhMuc)
            ->assertViewHas('categories', fn ($categories): bool => $categories->getCollection()->contains(
                fn ($record): bool => $record->getKey() === $category->getKey() && $record->TrangThai === 'Tạm ngưng',
            ));
    }

    public function test_unused_nonempty_category_warns_about_cascade_and_deletes_children_and_prices(): void
    {
        $category = DanhMucLoaiDoGiat::query()->create([
            'TenDanhMuc' => 'Danh mục chưa sử dụng',
            'TrangThai' => 'Hoạt động',
        ]);
        $children = collect(['Áo mới', 'Quần mới'])->map(fn (string $name) => LoaiDoGiat::query()->create([
            'TenLoaiDoGiat' => $name,
            'DanhMucID' => $category->getKey(),
            'TrangThai' => 'Hoạt động',
        ]));
        foreach ($children as $child) {
            DB::table('BangGia')->insert(['LoaiDoGiatID' => $child->getKey()]);
        }
        $otherCategory = DanhMucLoaiDoGiat::query()->create([
            'TenDanhMuc' => 'Danh mục khác đang dùng',
            'TrangThai' => 'Hoạt động',
        ]);
        $other = LoaiDoGiat::query()->create([
            'TenLoaiDoGiat' => 'Chăn khác',
            'DanhMucID' => $otherCategory->getKey(),
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('BangGia')->insert(['LoaiDoGiatID' => $other->getKey()]);
        DB::table('ChiTietBooking')->insert(['LoaiDoGiatID' => $other->getKey()]);
        DB::table('BookingKiemKe')->insert(['DuLieuThucTe' => json_encode([
            '_previous_versions' => [['items' => [['LoaiDoGiatID' => $other->getKey()]]]],
        ], JSON_THROW_ON_ERROR)]);

        $response = $this->actingAs($this->userWithPermissions([
            'garment_categories.view', 'garment_categories.delete',
        ]))->get(route('garment-categories.index'))->assertOk();
        $text = $this->confirmationText($response->getContent(), route('garment-categories.destroy', $category));
        $this->assertStringContainsString('xóa vĩnh viễn', $text);
        $this->assertStringContainsString('Các loại đồ giặt chưa sử dụng thuộc danh mục và bảng giá liên quan cũng sẽ bị xóa.', $text);
        $this->assertStringContainsString('Hành động này không thể hoàn tác.', $text);
        $this->assertStringNotContainsString('Tạm ngưng', $text);
        $otherText = $this->confirmationText($response->getContent(), route('garment-categories.destroy', $otherCategory));
        $this->assertStringContainsString("'Tạm ngưng'", $otherText);
        $this->assertStringNotContainsString('xóa vĩnh viễn', $otherText);

        $this->assertTrue(app(GarmentCategoryService::class)->delete($category));
        $this->assertNull($category->fresh());
        foreach ($children as $child) {
            $this->assertNull($child->fresh());
            $this->assertDatabaseMissing('BangGia', ['LoaiDoGiatID' => $child->getKey()]);
        }
        $this->assertSame('Hoạt động', $otherCategory->fresh()->TrangThai);
        $this->assertSame('Hoạt động', $other->fresh()->TrangThai);
        $this->assertDatabaseHas('BangGia', ['LoaiDoGiatID' => $other->getKey()]);
        $this->assertDatabaseHas('ChiTietBooking', ['LoaiDoGiatID' => $other->getKey()]);
        $this->assertDatabaseCount('BookingKiemKe', 1);
    }

    #[DataProvider('bookingHistorySources')]
    public function test_booking_history_of_one_child_suspends_entire_category(string $source): void
    {
        $category = DanhMucLoaiDoGiat::query()->create([
            'TenDanhMuc' => 'Đồ có lịch sử Booking',
            'TrangThai' => 'Hoạt động',
        ]);
        $used = LoaiDoGiat::query()->create([
            'TenLoaiDoGiat' => 'Áo đã kiểm kê',
            'DanhMucID' => $category->getKey(),
            'TrangThai' => 'Hoạt động',
        ]);
        $unused = LoaiDoGiat::query()->create([
            'TenLoaiDoGiat' => 'Áo chưa sử dụng',
            'DanhMucID' => $category->getKey(),
            'TrangThai' => 'Hoạt động',
        ]);
        if ($source === 'ChiTietBooking') {
            DB::table('ChiTietBooking')->insert(['LoaiDoGiatID' => $used->getKey()]);
        } else {
            $payload = ['items' => [['details' => ['LoaiDoGiatID' => $source === '_previous_versions' ? (string) $used->getKey() : $used->getKey()]]]];
            if ($source === '_previous_versions') {
                $payload = ['items' => [], '_previous_versions' => [
                    ['items' => [], '_previous_versions' => [$payload]],
                ]];
            }
            DB::table('BookingKiemKe')->insert(['DuLieuThucTe' => json_encode($payload, JSON_THROW_ON_ERROR)]);
        }
        $historyBefore = DB::table($source === 'ChiTietBooking' ? 'ChiTietBooking' : 'BookingKiemKe')->get()->toArray();

        $response = $this->actingAs($this->userWithPermissions([
            'garment_categories.view', 'garment_categories.delete',
        ]))->get(route('garment-categories.index'))->assertOk();
        $text = $this->confirmationText($response->getContent(), route('garment-categories.destroy', $category));
        $this->assertStringContainsString("'Tạm ngưng'", $text);
        $this->assertStringContainsString('bảo toàn dữ liệu lịch sử', $text);
        $this->assertStringNotContainsString('xóa vĩnh viễn', $text);

        $this->assertFalse(app(GarmentCategoryService::class)->delete($category));
        foreach ([$category, $used, $unused] as $record) {
            $this->assertSame('Tạm ngưng', $record->fresh()->TrangThai);
        }
        $this->assertEquals($historyBefore, DB::table($source === 'ChiTietBooking' ? 'ChiTietBooking' : 'BookingKiemKe')->get()->toArray());
        $this->assertDatabaseCount('ChiTietDonHang', 0);
    }

    public static function bookingHistorySources(): array
    {
        return [
            'booking detail' => ['ChiTietBooking'],
            'current inspection with numeric ID' => ['BookingKiemKe'],
            'nested previous versions with string ID' => ['_previous_versions'],
        ];
    }

    public function test_view_only_employee_cannot_see_or_submit_category_delete_confirmation(): void
    {
        $category = DanhMucLoaiDoGiat::query()->create([
            'TenDanhMuc' => 'Danh mục chỉ được xem',
            'TrangThai' => 'Hoạt động',
        ]);
        $this->actingAs($this->userWithPermissions(['garment_categories.view']))
            ->get(route('garment-categories.index'))
            ->assertOk()
            ->assertSee($category->TenDanhMuc)
            ->assertSee(route('garment-categories.show', $category), false)
            ->assertDontSee('data-confirm-form', false)
            ->assertDontSee(route('garment-categories.edit', $category), false);

        $this->delete(route('garment-categories.destroy', $category))->assertForbidden();
        $this->assertSame('Hoạt động', $category->fresh()->TrangThai);
    }

    public function test_category_management_routes_use_existing_garment_permissions(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertSame(
            'garment-categories',
            $routes->getByName('garment-categories.index')->uri(),
        );
        $this->assertContains(
            'permission:garment_categories.view',
            $routes->getByName('garment-categories.index')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:garment_categories.create',
            $routes->getByName('garment-categories.store')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:garment_categories.edit',
            $routes->getByName('garment-categories.update')->getAction('middleware'),
        );
        $this->assertContains(
            'permission:garment_categories.delete',
            $routes->getByName('garment-categories.destroy')->getAction('middleware'),
        );
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
    private function userWithPermissions(array $permissions): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['TenDangNhap' => 'Catalog tester', 'AvatarURL' => '/assets/images/user_1.jpg']);
        $user->setRelation('nhanVien', null);
        $user->setRelation('khachHang', null);
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isCustomer')->andReturn(false);
        $user->shouldReceive('roleSlug')->andReturn('staff');
        $user->shouldReceive('canPermission')
            ->andReturnUsing(fn (string $permission): bool => in_array($permission, $permissions, true));

        return $user;
    }
}
