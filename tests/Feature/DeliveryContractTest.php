<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Admin\GiaoNhanController;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Models\Booking;
use App\Models\BookingGiaoNhan;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\User;
use App\Services\BookingService;
use App\Services\DeliveryService;
use App\Services\EmployeeAssignment;
use App\Services\OrderService;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeliveryContractTest extends TestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Guard before the base TestCase checks/seeds the RBAC schema.
        $this->assertInMemoryDatabase();

        return $app;
    }

    private function assertInMemoryDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertInMemoryDatabase();
        Http::preventStrayRequests();
        Http::fake();
        $this->travelTo(now()->setDate(2026, 10, 7)->startOfDay());
        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang')->default('DH-test');
            $table->unsignedInteger('BookingID')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai')->default(OrderStatus::Washed->value);
            $table->dateTime('NgayTao')->nullable();
            $table->dateTime('NgayCapNhat')->nullable();
        });
        Schema::create('Booking', function (Blueprint $table): void {
            $table->increments('BookingID');
            $table->string('MaBooking')->default('DL-test');
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai')->default('DaXacNhan');
            $table->string('HinhThucNhanDo')->nullable();
            $table->string('DiaChiNhan')->nullable();
            $table->string('HinhThucTraDo')->default('Tại nhà');
            $table->string('DiaChiTra')->nullable();
            $table->date('NgayHen')->nullable();
            $table->time('GioHen')->nullable();
            $table->string('GhiChu')->nullable();
        });
        Schema::create('BookingGiaoNhan', function (Blueprint $table): void {
            $table->increments('BookingGiaoNhanID');
            $table->unsignedInteger('BookingID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->unsignedInteger('BookingKiemKeID')->nullable();
            $table->string('LoaiGiaoNhan')->default('NHAN_DO');
            $table->string('HinhThuc')->default('Tại cửa hàng');
            $table->string('DiaChi')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->dateTime('ThoiGianDuKien')->nullable();
            $table->dateTime('ThoiGianThucTe')->nullable();
            $table->decimal('PhiGiaoNhan', 12, 2)->default(0);
            $table->string('TrangThai')->default('Chờ thực hiện');
            $table->dateTime('NgayTao')->nullable();
        });
        Schema::create('BookingKiemKe', function (Blueprint $table): void {
            $table->increments('BookingKiemKeID');
            $table->unsignedInteger('BookingID')->unique();
            $table->text('DuLieuThucTe')->nullable();
            $table->boolean('CoChenhLech')->default(false);
            $table->unsignedInteger('PhienBan')->default(1);
            $table->dateTime('CapNhatLuc')->nullable();
        });
        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen')->nullable();
            $table->string('SoDienThoai')->nullable();
        });
        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('HoTen')->nullable();
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
            $table->date('NgayVaoLam')->nullable();
            $table->string('ChucDanh')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('VaiTro', function (Blueprint $table): void {
            $table->increments('VaiTroID');
            $table->string('TenVaiTro');
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('TaiKhoan_VaiTro', function (Blueprint $table): void {
            $table->unsignedInteger('TaiKhoanID');
            $table->unsignedInteger('VaiTroID');
        });
        Schema::create('VaiTro_Quyen', function (Blueprint $table): void {
            $table->unsignedInteger('VaiTroID');
            $table->unsignedInteger('QuyenID');
        });
        Schema::create('Quyen', function (Blueprint $table): void {
            $table->increments('QuyenID');
            $table->string('MaQuyen');
            $table->string('TrangThai')->default('Hoạt động');
        });
        Schema::create('GiaoNhan', function (Blueprint $table): void {
            $table->increments('GiaoNhanID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('LoaiGiaoNhan');
            $table->string('HinhThuc');
            $table->string('DiaChi')->nullable();
            $table->dateTime('ThoiGianDuKien')->nullable();
            $table->dateTime('ThoiGianThucTe')->nullable();
            $table->decimal('PhiGiaoNhan', 12, 2)->default(0);
            $table->string('TrangThai');
            $table->string('GhiChu')->nullable();
        });
        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->unsignedInteger('DonHangID');
            $table->string('TrangThai');
            $table->decimal('SoTien', 12, 2)->default(0);
        });
        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID');
            $table->decimal('ThanhTien', 12, 2)->default(0);
            $table->string('TrangThai')->nullable();
        });
        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedInteger('BanGhiID');
            $table->text('DuLieuCu')->nullable();
            $table->text('DuLieuMoi')->nullable();
            $table->string('LyDo')->nullable();
            $table->dateTime('ThoiGian');
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });
        DB::table('Booking')->insert(['BookingID' => 1, 'HinhThucTraDo' => 'Tại nhà', 'DiaChiTra' => '12 Nguyễn Huệ']);
        DB::table('DonHang')->insert(['DonHangID' => 1, 'BookingID' => 1]);
        DB::table('BookingGiaoNhan')->insert([
            'BookingID' => 1,
            'DonHangID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'PhiGiaoNhan' => 4200,
        ]);
        DB::table('NhanVien')->insert([['NhanVienID' => 1, 'TrangThai' => 'Hoạt động'], ['NhanVienID' => 2, 'TrangThai' => 'Khóa']]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->app) {
                $this->assertInMemoryDatabase();
                foreach (['NhatKyHeThong', 'HoaDon', 'ThanhToan', 'BookingKiemKe', 'BookingGiaoNhan', 'GiaoNhan', 'VaiTro_Quyen', 'Quyen', 'TaiKhoan_VaiTro', 'VaiTro', 'TaiKhoan', 'NhanVien', 'KhachHang', 'Booking', 'DonHang'] as $table) {
                    Schema::dropIfExists($table);
                }
                $this->travelBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    private function assertReadOnlyBookingDocument(TestResponse $response): void
    {
        $response->assertViewIs('admin.deliveries.booking-document')
            ->assertViewMissing('canInspect')
            ->assertViewMissing('canManageIntake')
            ->assertDontSee('Thao tác')
            ->assertDontSee('Kiểm tra và tạo đơn')
            ->assertDontSee('Chỉnh sửa phiếu nhận đồ')
            ->assertDontSee('Đơn hàng đã được tạo từ kiểm kê')
            ->assertDontSee('Chỉ nhân viên được phân công mới có thể kiểm kê phiếu này.');

        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $header = '//div[contains(concat(" ", normalize-space(@class), " "), " detail-header ")]';
        $content = $header.'/following-sibling::div[contains(concat(" ", normalize-space(@class), " "), " row ")][1]';

        $this->assertSame(1, $xpath->query($header.'//a[@href="'.route('deliveries.index').'"][contains(., "Quay lại")]')->length);
        $this->assertSame(1, $xpath->query($content.'/div')->length);
        $this->assertSame(1, $xpath->query($content.'/div[contains(concat(" ", normalize-space(@class), " "), " col-12 ")]')->length);
        $this->assertSame(0, $xpath->query($content.'/div[contains(concat(" ", normalize-space(@class), " "), " col-lg-8 ")]')->length);
        $this->assertSame(0, $xpath->query($content.'//form | '.$content.'//input | '.$content.'//select | '.$content.'//textarea')->length);
    }

    private function data(array $overrides = []): array
    {
        return array_merge(['order_id' => 1, 'employee_id' => 1, 'method' => 'giao_do', 'fulfillment' => 'Tại nhà', 'address' => '12 Nguyễn Huệ'], $overrides);
    }

    private function createBookingIntake(array $overrides = []): BookingGiaoNhan
    {
        $id = DB::table('BookingGiaoNhan')->insertGetId(array_merge([
            'BookingID' => 1,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'NhanVienID' => 1,
            'ThoiGianDuKien' => '2026-10-08 10:00:00',
            'TrangThai' => 'Chờ thực hiện',
        ], $overrides), 'BookingGiaoNhanID');

        return BookingGiaoNhan::query()->findOrFail($id);
    }

    private function reject(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Invalid mutation accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors(), json_encode($exception->errors()));
        }
    }

    public function test_staff_can_only_assign_and_access_their_own_deliveries(): void
    {
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['HoTen' => 'Trần Văn Bình']);
        DB::table('NhanVien')->insert(['NhanVienID' => 3, 'HoTen' => 'Nhân viên khác', 'TrangThai' => 'Hoạt động']);
        $user = Mockery::mock(User::class)->makePartial();
        $user->TaiKhoanID = 100;
        $user->NhanVienID = 1;
        $user->shouldReceive('isManager')->andReturn(false);
        $this->actingAs($user)->withoutMiddleware();
        view()->share('errors', new ViewErrorBag);

        $this->get(route('deliveries.create'))->assertOk()->assertSee('Trần Văn Bình')
            ->assertSee('type="hidden" name="employee_id" value="1"', false)
            ->assertSee('id="employee_select" value="Trần Văn Bình" readonly', false)
            ->assertDontSee('data-employee-toggle', false)
            ->assertDontSee('<select class="form-select" id="employee_id"', false);
        $this->postJson(route('deliveries.store'), $this->data(['employee_id' => 3]))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->assertSame(0, GiaoNhan::count());

        $ownData = $this->data();
        unset($ownData['employee_id']);
        $this->post(route('deliveries.store'), $ownData)->assertSessionHasNoErrors();
        $this->assertSame(1, (int) GiaoNhan::firstOrFail()->NhanVienID);

        DB::table('DonHang')->insert(['DonHangID' => 2]);
        DB::table('GiaoNhan')->insert([
            'DonHangID' => 2, 'NhanVienID' => 3, 'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà', 'DiaChi' => '12 Nguyễn Huệ', 'TrangThai' => 'Chờ thực hiện',
        ]);
        $other = GiaoNhan::where('DonHangID', 2)->firstOrFail();
        $this->get(route('deliveries.show', $other))->assertForbidden();
        $this->get(route('deliveries.edit', $other))->assertForbidden();
        $this->put(route('deliveries.update', $other), $this->data(['order_id' => 2, 'employee_id' => 3]))->assertForbidden();
        $this->delete(route('deliveries.destroy', $other))->assertForbidden();
        $this->assertSame(1, app(DeliveryService::class)->getAll(['employee_id' => 1])->total());
    }

    public function test_edit_form_shows_locked_delivery_details_and_ignores_tampered_values(): void
    {
        DB::table('KhachHang')->insert(['KhachHangID' => 1, 'HoTen' => 'Khoa Vo']);
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['HoTen' => 'Trần Văn Bình']);
        DB::table('DonHang')->where('DonHangID', 1)->update([
            'MaDonHang' => 'DH00000072',
            'KhachHangID' => 1,
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 2,
            'MaDonHang' => 'DH00000073',
            'TrangThai' => OrderStatus::Washed->value,
        ]);
        DB::table('GiaoNhan')->insert([
            'GiaoNhanID' => 1,
            'DonHangID' => 1,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'TrangThai' => 'Chờ thực hiện',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->NhanVienID = 1;
        $user->shouldReceive('isManager')->andReturn(false);
        $this->actingAs($user)->withoutMiddleware();
        view()->share('errors', new ViewErrorBag);

        $this->get(route('deliveries.edit', 1))
            ->assertOk()
            ->assertSee('href="'.route('deliveries.show', 1).'"', false)
            ->assertSee('Quay lại')
            ->assertSee('Lưu thay đổi')
            ->assertDontSee('>Hủy</a>', false)
            ->assertSee('DH00000072 · Khoa Vo')
            ->assertSee('value="Giao đồ"', false)
            ->assertSee('value="Tại nhà"', false)
            ->assertSee('value="Trần Văn Bình"', false)
            ->assertSee('class="form-control delivery-fixed-field" id="order_display"', false)
            ->assertSee('class="form-control delivery-fixed-field" id="method_display"', false)
            ->assertSee('class="form-control delivery-fixed-field" id="fulfillment_display"', false)
            ->assertDontSee('Chọn đơn hàng')
            ->assertDontSee('<select class="form-select" id="employee_id"', false);

        $this->put(route('deliveries.update', 1), $this->data([
            'order_id' => 2,
            'method' => 'nhan_do',
            'fulfillment' => 'Tại cửa hàng',
            'employee_id' => 1,
        ]))->assertSessionHasNoErrors();

        $delivery = GiaoNhan::findOrFail(1);
        $this->assertSame(1, (int) $delivery->DonHangID);
        $this->assertSame('GIAO_DO', $delivery->LoaiGiaoNhan);
        $this->assertSame('Tại nhà', $delivery->HinhThuc);
        $this->reject(
            fn () => app(DeliveryService::class)->update($delivery, ['fulfillment' => 'Tại cửa hàng']),
            'fulfillment',
        );

        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        $this->actingAs($manager)->withoutMiddleware();

        $this->get(route('deliveries.edit', 1))
            ->assertOk()
            ->assertSee('<select class="form-select" id="employee_id" name="employee_id"', false);
    }

    public function test_manager_assignment_list_excludes_owner_and_manager_profiles(): void
    {
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['HoTen' => 'Nhân viên giao nhận']);
        DB::table('NhanVien')->insert([
            ['NhanVienID' => 3, 'HoTen' => 'Chủ cửa hàng mẫu', 'ChucDanh' => 'Chủ cửa hàng', 'TrangThai' => 'Hoạt động'],
            ['NhanVienID' => 4, 'HoTen' => 'Quản lý mẫu', 'ChucDanh' => null, 'TrangThai' => 'Hoạt động'],
        ]);
        DB::table('TaiKhoan')->insert(['TaiKhoanID' => 44, 'NhanVienID' => 4]);
        DB::table('VaiTro')->insert(['VaiTroID' => 4, 'TenVaiTro' => 'Quản lý']);
        DB::table('TaiKhoan_VaiTro')->insert(['TaiKhoanID' => 44, 'VaiTroID' => 4]);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        view()->share('errors', new ViewErrorBag);

        $this->actingAs($manager)->withoutMiddleware()->get(route('deliveries.create'))
            ->assertOk()->assertSee('Nhân viên giao nhận')
            ->assertSee('Tài khoản chưa có nhân viên hợp lệ được liên kết')
            ->assertDontSee('Chủ cửa hàng mẫu')->assertDontSee('Quản lý mẫu');
        $this->postJson(route('deliveries.store'), $this->data(['employee_id' => 4]))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
    }

    public function test_create_form_defaults_to_linked_eligible_employee_and_supports_name_search(): void
    {
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['HoTen' => 'Nhân viên giao nhận']);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->NhanVienID = 1;
        $manager->shouldReceive('isManager')->andReturn(true);
        view()->share('errors', new ViewErrorBag);

        $this->actingAs($manager)->withoutMiddleware()->get(route('deliveries.create'))
            ->assertOk()
            ->assertSee('class="form-select text-start text-truncate" id="employee_select" type="button"', false)
            ->assertSee('data-employee-toggle', false)
            ->assertSee('data-employee-dropdown hidden', false)
            ->assertSee('data-employee-search', false)
            ->assertSee('delivery-employee-options', false)
            ->assertSee('class="form-select text-start text-truncate"', false)
            ->assertSee('id="employee_search" type="search"', false)
            ->assertSee('name="employee_search"', false)
            ->assertSee('placeholder="Tìm theo tên nhân viên..."', false)
            ->assertSee('data-selected-employee-name>Nhân viên giao nhận</span>', false)
            ->assertDontSee('Thay đổi')
            ->assertDontSee('bi-person-fill')
            ->assertSee('id="employee_id" name="employee_id"', false)
            ->assertDontSee('<datalist', false)
            ->assertDontSee('(#1)')
            ->assertDontSee('Chưa xác định được nhân viên mặc định');
    }

    public function test_manager_defaults_to_own_linked_profile_even_when_that_profile_is_not_assignable_to_others(): void
    {
        DB::table('NhanVien')->insert([
            'NhanVienID' => 4,
            'HoTen' => 'Trần Văn Quản lý',
            'ChucDanh' => 'Quản lý',
            'TrangThai' => 'Hoạt động',
        ]);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->NhanVienID = 4;
        $manager->shouldReceive('isManager')->andReturn(true);
        view()->share('errors', new ViewErrorBag);

        $this->actingAs($manager)->withoutMiddleware()->get(route('deliveries.create'))
            ->assertOk()
            ->assertSee('data-selected-employee-name>Trần Văn Quản lý</span>', false)
            ->assertSee('id="employee_id" name="employee_id"', false)
            ->assertSee('value="4"', false)
            ->assertDontSee('(#4)');

        $this->post(route('deliveries.store'), $this->data([
            'employee_id' => 4,
            'employee_search' => 'Trần Văn Quản lý',
        ]))->assertRedirect(route('deliveries.show', 1))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, (int) GiaoNhan::firstOrFail()->NhanVienID);
    }

    public function test_create_form_restores_the_selected_employee_after_validation_redirect(): void
    {
        DB::table('NhanVien')->insert([
            'NhanVienID' => 3,
            'HoTen' => 'Nguyễn Văn B',
            'TrangThai' => 'Hoạt động',
        ]);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isActive')->andReturn(true);
        $manager->shouldReceive('isManager')->andReturn(true);
        $manager->shouldReceive('isCustomer')->andReturn(false);
        $manager->shouldReceive('isOwner')->andReturn(false);
        $manager->shouldReceive('canPermission')->andReturn(false);
        view()->share('errors', new ViewErrorBag);

        $this->actingAs($manager)->withoutMiddleware(EnsureUserHasPermission::class)->withSession([
            '_old_input' => [
                'employee_id' => 3,
                'employee_search' => 'Nguyễn Văn B',
            ],
        ])->get(route('deliveries.create'))
            ->assertOk()
            ->assertSee('data-selected-employee-name>Nguyễn Văn B</span>', false)
            ->assertSee('data-employee-id="3"', false)
            ->assertSee('value="3"', false)
            ->assertSee('data-selected-employee-search', false);
    }

    public function test_duplicate_employee_names_are_disambiguated_but_selected_id_is_saved_exactly(): void
    {
        DB::table('NhanVien')->where('NhanVienID', 1)->update([
            'HoTen' => 'Nguyễn Văn Trùng',
            'SoDienThoai' => '0900000001',
        ]);
        DB::table('NhanVien')->insert([
            'NhanVienID' => 3,
            'HoTen' => 'Nguyễn Văn Trùng',
            'SoDienThoai' => '0900000003',
            'TrangThai' => 'Hoạt động',
        ]);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        view()->share('errors', new ViewErrorBag);

        $this->actingAs($manager)->withoutMiddleware()->get(route('deliveries.create'))
            ->assertOk()
            ->assertSee('data-employee-name="Nguyễn Văn Trùng"', false)
            ->assertSee('data-employee-description="0900000001"', false)
            ->assertSee('data-employee-description="0900000003"', false)
            ->assertDontSee('Nguyễn Văn Trùng (#1)')
            ->assertDontSee('Nguyễn Văn Trùng (#3)');

        $this->post(route('deliveries.store'), $this->data([
            'employee_id' => 3,
            'employee_search' => 'Nguyễn Văn Trùng',
        ]))->assertRedirect(route('deliveries.show', 1))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, (int) GiaoNhan::firstOrFail()->NhanVienID);
    }

    public function test_free_typed_employee_name_cannot_submit_an_old_or_guessed_employee_id(): void
    {
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['HoTen' => 'Nhân viên hợp lệ']);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);

        $this->actingAs($manager)->withoutMiddleware()
            ->postJson(route('deliveries.store'), $this->data([
                'employee_id' => 1,
                'employee_search' => 'Tên nhập tự do',
            ]))->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_booking_intake_documents_are_listed_and_open_without_an_order(): void
    {
        DB::table('KhachHang')->insert(['KhachHangID' => 2, 'HoTen' => 'Khách Booking', 'SoDienThoai' => '0900000000']);
        DB::table('Booking')->insert([
            'BookingID' => 2,
            'MaBooking' => 'DL-BOOK-2',
            'KhachHangID' => 2,
            'TrangThai' => 'Đã xác nhận',
            'HinhThucNhanDo' => 'Tại cửa hàng',
            'HinhThucTraDo' => 'Tại cửa hàng',
            'NgayHen' => '2026-10-08',
        ]);
        $documentId = DB::table('BookingGiaoNhan')->insertGetId([
            'BookingID' => 2,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'NhanVienID' => 1,
            'TrangThai' => 'Chờ thực hiện',
        ], 'BookingGiaoNhanID');
        DB::table('GiaoNhan')->insert([
            'GiaoNhanID' => $documentId,
            'DonHangID' => 1,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'TrangThai' => 'Chờ thực hiện',
        ]);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        $manager->shouldReceive('isActive')->andReturn(true);
        $manager->shouldReceive('isOwner')->andReturn(false);
        $manager->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => in_array($permission, [
                'deliveries.view',
                'bookings.view',
                'orders.view',
            ], true),
        );
        view()->share('errors', new ViewErrorBag);

        $this->actingAs($manager)->withoutMiddleware()->get(route('deliveries.index'))
            ->assertOk()
            ->assertSee('Quản lý giao nhận')
            ->assertDontSee('Chứng từ')
            ->assertSee('DL-BOOK-2')
            ->assertSee('Khách Booking')
            ->assertSee('BGN-'.$documentId)
            ->assertSee('GN-'.$documentId)
            ->assertSee('data-delivery-key="booking:'.$documentId.'"', false)
            ->assertSee('data-delivery-key="operational:'.$documentId.'"', false)
            ->assertSee('data-detail-url="'.route('deliveries.booking-documents.show', $documentId).'"', false)
            ->assertSee('data-detail-url="'.route('deliveries.show', $documentId).'"', false)
            ->assertSee(route('deliveries.booking-documents.show', $documentId), false)
            ->assertSee(route('deliveries.show', $documentId), false);

        $detailResponse = $this->get(route('deliveries.booking-documents.show', $documentId))
            ->assertOk()
            ->assertSee('Phiếu nhận đồ từ đặt lịch')
            ->assertSee('Mã phiếu')
            ->assertSee('BGN-'.$documentId)
            ->assertDontSee('Chứng từ')
            ->assertSee('Khách Booking')
            ->assertSee('0900000000')
            ->assertSee('08/10/2026')
            ->assertSee('href="'.route('bookings.show', 2).'"', false)
            ->assertSee('Chưa tạo đơn hàng')
            ->assertSee('Không yêu cầu địa chỉ giao tận nhà')
            ->assertDontSee('Kết quả kiểm kê');
        $this->assertReadOnlyBookingDocument($detailResponse);
        $this->get(route('deliveries.show', $documentId))
            ->assertOk()
            ->assertSee('Chi tiết phiếu giao nhận')
            ->assertSee('Mã phiếu')
            ->assertSee('GN-'.$documentId);

        DB::table('DonHang')->insert([
            'DonHangID' => 2,
            'MaDonHang' => 'DH-BOOK-2',
            'BookingID' => 2,
            'TrangThai' => OrderStatus::Received->value,
        ]);
        DB::table('BookingGiaoNhan')->where('BookingGiaoNhanID', $documentId)
            ->update(['DonHangID' => 2]);
        $this->get(route('deliveries.index'))
            ->assertOk()
            ->assertSee('DH-BOOK-2')
            ->assertSee('DL-BOOK-2');
        $linkedDetailResponse = $this->get(route('deliveries.booking-documents.show', $documentId))
            ->assertOk()
            ->assertSee('DH-BOOK-2')
            ->assertSee('href="'.route('orders.show', 2).'"', false)
            ->assertSee('href="'.route('bookings.show', 2).'"', false)
            ->assertDontSee('Chưa tạo đơn hàng');
        $this->assertReadOnlyBookingDocument($linkedDetailResponse);
    }

    public function test_unified_delivery_actions_respect_source_permission_and_mutable_state(): void
    {
        DB::table('BookingGiaoNhan')->insert([
            'BookingID' => 1,
            'DonHangID' => 1,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại nhà',
            'NhanVienID' => 1,
            'TrangThai' => 'Đã nhận',
        ]);
        $pendingId = DB::table('GiaoNhan')->insertGetId([
            'DonHangID' => 1,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'TrangThai' => 'Chờ thực hiện',
        ], 'GiaoNhanID');
        $completedId = DB::table('GiaoNhan')->insertGetId([
            'DonHangID' => 1,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'TrangThai' => 'Hoàn thành',
        ], 'GiaoNhanID');

        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        $manager->shouldReceive('isActive')->andReturn(true);
        $manager->shouldReceive('isOwner')->andReturn(false);
        $manager->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => in_array($permission, [
                'deliveries.view',
                'deliveries.edit',
                'deliveries.delete',
            ], true),
        );
        view()->share('errors', new ViewErrorBag);

        $response = $this->actingAs($manager)->withoutMiddleware()->get(route('deliveries.index'))->assertOk();
        $response->assertSee(route('deliveries.edit', $pendingId), false)
            ->assertSee('action="'.route('deliveries.cancel-return', $pendingId).'"', false)
            ->assertSee('data-confirm-form', false)
            ->assertSee('method="POST"', false)
            ->assertSee('title="Hủy lịch giao"', false)
            ->assertDontSee(route('deliveries.edit', $completedId), false)
            ->assertDontSee('deliveries/booking-documents/1/edit', false)
            ->assertDontSee('deleteDeliveryForm', false)
            ->assertDontSee('value="DELETE"', false);
        $this->get(route('deliveries.show', $pendingId))
            ->assertOk()
            ->assertSee(route('deliveries.edit', $pendingId), false)
            ->assertSee('class="btn btn-primary w-100 py-2"', false)
            ->assertSee('Chỉnh sửa')
            ->assertSee('class="d-flex flex-column gap-2"', false)
            ->assertSee('class="btn btn-outline-danger py-2 w-100"', false)
            ->assertSee('Hủy lịch giao')
            ->assertDontSee('btn-order-action delete', false)
            ->assertSee('action="'.route('deliveries.cancel-return', $pendingId).'"', false)
            ->assertSee('data-confirm-form', false)
            ->assertSee('method="POST"', false);
        $this->get(route('deliveries.edit', $completedId))->assertForbidden();
        $successBadge = 'badge status-badge bg-success-subtle text-success-emphasis border border-success rounded-pill px-3 py-2';
        $this->assertSame(2, substr_count($response->getContent(), $successBadge));
        $response->assertSee('Đã nhận đồ')->assertSee('Hoàn thành')
            ->assertSee('badge status-badge bg-warning-subtle text-warning-emphasis border border-warning rounded-pill px-3 py-2', false)
            ->assertSee('Chờ xác nhận');
    }

    public function test_booking_intake_can_be_edited_but_has_no_separate_cancellation_route_or_action(): void
    {
        DB::table('Booking')->insert([
            'BookingID' => 2,
            'MaBooking' => 'DL-EDIT-2',
            'TrangThai' => 'DaXacNhan',
            'HinhThucNhanDo' => 'Tại nhà',
        ]);
        DB::table('NhanVien')->insert(['NhanVienID' => 3, 'HoTen' => 'Nhân viên mới', 'TrangThai' => 'Hoạt động']);
        $intake = $this->createBookingIntake(['BookingID' => 2]);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        $manager->shouldReceive('isActive')->andReturn(true);
        $manager->shouldReceive('isOwner')->andReturn(false);
        $manager->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => in_array($permission, [
                'deliveries.view',
                'deliveries.edit',
                'deliveries.delete',
                'bookings.view',
                'orders.view',
            ], true),
        );
        view()->share('errors', new ViewErrorBag);
        $this->actingAs($manager)->withoutMiddleware();

        $editResponse = $this->get(route('deliveries.booking-documents.edit', $intake))
            ->assertOk()
            ->assertSee('Cập nhật phiếu nhận đồ')
            ->assertSee('<a href="'.route('deliveries.index').'" class="btn btn-outline-secondary btn-sm">', false)
            ->assertDontSee('href="'.route('deliveries.booking-documents.show', $intake).'"', false)
            ->assertSee('Quay lại')
            ->assertSee('<button type="submit" class="btn btn-primary">Cập nhật</button>', false)
            ->assertSee('<form action="'.route('deliveries.booking-documents.update', $intake).'" method="POST">', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('BGN-'.$intake->BookingGiaoNhanID)
            ->assertSee('name="address"', false)
            ->assertSee('name="pickup_date"', false)
            ->assertSee('name="pickup_time"', false);
        $this->assertSame(1, substr_count($editResponse->getContent(), '</i>Quay lại'));

        $this->put(route('deliveries.booking-documents.update', $intake), [
            'employee_id' => 3,
            'address' => '99 Lê Lợi',
            'pickup_date' => '2026-10-09',
            'pickup_time' => '11:30',
            'BookingID' => 999,
        ])->assertRedirect(route('deliveries.booking-documents.show', $intake))
            ->assertSessionHas('success');
        $intake->refresh();
        $this->assertSame(2, (int) $intake->BookingID);
        $this->assertSame(3, (int) $intake->NhanVienID);
        $this->assertSame('99 Lê Lợi', $intake->DiaChi);
        $this->assertSame('2026-10-09 11:30:00', $intake->ThoiGianDuKien->format('Y-m-d H:i:s'));
        $assignmentAudit = DB::table('NhatKyHeThong')
            ->where('BangDuLieu', 'BookingGiaoNhan')
            ->where('BanGhiID', $intake->BookingGiaoNhanID)
            ->first();
        $this->assertNotNull($assignmentAudit);
        $this->assertSame(3, json_decode($assignmentAudit->DuLieuMoi, true)['NhanVienID']);

        $this->assertFalse(Route::has('deliveries.booking-documents.cancel'));
        $this->post('/deliveries/booking-documents/'.$intake->BookingGiaoNhanID.'/cancel')->assertNotFound();
        $this->assertSame('Chờ thực hiện', $intake->fresh()->TrangThai);

        $this->get(route('deliveries.index'))
            ->assertOk()
            ->assertSee('BGN-'.$intake->BookingGiaoNhanID)
            ->assertSee(route('deliveries.booking-documents.edit', $intake), false)
            ->assertDontSee('cancelBookingDeliveryForm_'.$intake->BookingGiaoNhanID, false);
        $this->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertDontSee('Hủy phiếu nhận đồ');

        $staff = Mockery::mock(User::class)->makePartial();
        $staff->shouldReceive('isActive')->andReturn(true);
        $staff->shouldReceive('isManager')->andReturn(false);
        $staff->shouldReceive('isOwner')->andReturn(false);
        $staff->shouldReceive('isCustomer')->andReturn(false);
        $staff->shouldReceive('canPermission')->andReturn(false);
        $this->actingAs($staff)
            ->get(route('deliveries.booking-documents.edit', $intake))
            ->assertForbidden();
    }

    public function test_booking_document_is_detail_only_for_fully_permitted_user_and_list_edit_remains_reachable(): void
    {
        DB::table('Booking')->insert([
            'BookingID' => 8,
            'MaBooking' => 'DL-ACTIONS-2',
            'TrangThai' => BookingStatus::Confirmed->value,
        ]);
        $intake = $this->createBookingIntake([
            'BookingID' => 8,
            'NhanVienID' => 1,
        ]);
        DB::table('Booking')->insert([
            'BookingID' => 9,
            'MaBooking' => 'DL-ACTIONS-9',
            'TrangThai' => BookingStatus::Confirmed->value,
        ]);
        $receivedIntake = $this->createBookingIntake([
            'BookingID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => 'Đã nhận',
            'ThoiGianThucTe' => '2026-10-07 10:00:00',
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->TaiKhoanID = 101;
        $user->NhanVienID = 1;
        $user->shouldReceive('isManager')->andReturn(true);
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isOwner')->andReturn(false);
        $user->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => in_array($permission, [
                'deliveries.view', 'deliveries.create', 'deliveries.edit', 'deliveries.delete',
                'bookings.view', 'bookings.confirm', 'orders.view', 'orders.create',
            ], true),
        );
        $this->actingAs($user)->withoutMiddleware();
        view()->share('errors', new ViewErrorBag);

        foreach ([$intake, $receivedIntake] as $document) {
            $response = $this->get(route('deliveries.booking-documents.show', $document))
                ->assertOk()
                ->assertSee('BGN-'.$document->BookingGiaoNhanID)
                ->assertSee('Thông tin phiếu giao nhận')
                ->assertSee('Thông tin Booking')
                ->assertSee('href="'.route('bookings.show', $document->BookingID).'"', false)
                ->assertDontSee('href="'.route('bookings.inspection', $document->BookingID).'"', false)
                ->assertDontSee('href="'.route('deliveries.booking-documents.edit', $document).'"', false);
            $this->assertReadOnlyBookingDocument($response);
        }
        $response->assertSee('07/10/2026 10:00');

        $this->get(route('deliveries.index'))
            ->assertOk()
            ->assertDontSee('Kiểm tra và tạo đơn')
            ->assertDontSee('href="'.route('bookings.inspection', 8).'"', false)
            ->assertSee('href="'.route('deliveries.booking-documents.edit', $intake).'"', false)
            ->assertSee(route('deliveries.booking-documents.show', $intake), false)
            ->assertSee(route('deliveries.booking-documents.show', $receivedIntake), false);
        $this->get(route('deliveries.booking-documents.edit', $intake))
            ->assertOk()
            ->assertSee('Cập nhật phiếu nhận đồ')
            ->assertSee('href="'.route('deliveries.index').'"', false);
    }

    public static function bookingInspectionMetadata(): array
    {
        return [
            'accepted with confirmation timestamp' => [[
                'employee_name' => 'Trần Văn Kiểm kê',
                'confirmed_at' => '2026-10-07T03:15:30Z',
                'decided_at' => '2026-10-06T01:00:00Z',
                'customer_accepted' => true,
                'agreed_total' => 125000,
                'charged_total' => 50000,
            ], '07/10/2026 10:15:30', 'Có'],
            'declined with decision timestamp' => [[
                'employee_name' => 'Trần Văn Kiểm kê',
                'decided_at' => '2026-10-07T04:20:15Z',
                'customer_accepted' => false,
                'agreed_total' => 125000,
                'charged_total' => 0,
                'reason' => 'Khách chưa đồng ý giá thực tế',
            ], '07/10/2026 11:20:15', 'Không'],
        ];
    }

    #[DataProvider('bookingInspectionMetadata')]
    public function test_booking_document_retains_inspection_metadata_and_read_only_details(
        array $decision,
        string $expectedTimestamp,
        string $acceptedLabel,
    ): void {
        DB::table('Booking')->where('BookingID', 1)->update([
            'HinhThucNhanDo' => 'Tại nhà',
            'NgayHen' => '2026-10-08',
            'GhiChu' => 'Gọi trước khi nhận đồ',
        ]);
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['HoTen' => 'Nhân viên nhận đồ']);
        DB::table('BookingKiemKe')->insert([
            'BookingKiemKeID' => 1,
            'BookingID' => 1,
            'DuLieuThucTe' => json_encode(['_decision' => $decision]),
        ]);
        $intake = $this->createBookingIntake([
            'DonHangID' => 1,
            'BookingKiemKeID' => 1,
            'TrangThai' => 'Đã nhận',
            'ThoiGianThucTe' => '2026-10-07 10:00:00',
            'PhiGiaoNhan' => 12500,
        ]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->NhanVienID = 1;
        $user->shouldReceive('isManager')->andReturn(true);
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isOwner')->andReturn(false);
        $user->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => in_array($permission, [
                'deliveries.view', 'deliveries.create', 'deliveries.edit', 'deliveries.delete',
                'bookings.view', 'bookings.confirm', 'orders.view', 'orders.create',
            ], true),
        );
        view()->share('errors', new ViewErrorBag);

        $response = $this->actingAs($user)->withoutMiddleware()
            ->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertSee('BGN-'.$intake->BookingGiaoNhanID)
            ->assertSee('Nhân viên nhận đồ')
            ->assertSee('12 Nguyễn Huệ')
            ->assertSee('08/10/2026 10:00')
            ->assertSee('07/10/2026 10:00')
            ->assertSee('12.500 VNĐ')
            ->assertSee('Gọi trước khi nhận đồ')
            ->assertSee('href="'.route('bookings.show', 1).'"', false)
            ->assertSee('href="'.route('orders.show', 1).'"', false)
            ->assertSee('Kết quả kiểm kê')
            ->assertSee($decision['employee_name'])
            ->assertSee('Thời gian chốt')
            ->assertSee($expectedTimestamp)
            ->assertSeeInOrder(['Khách đồng ý báo giá', $acceptedLabel, 'Tổng tiền khách đồng ý', '125.000 VNĐ', 'Số tiền đã thu', number_format($decision['charged_total'], 0, ',', '.').' VNĐ'])
            ->assertDontSee('href="'.route('bookings.inspection', 1).'"', false)
            ->assertDontSee('href="'.route('deliveries.booking-documents.edit', $intake).'"', false);
        if (isset($decision['reason'])) {
            $response->assertSee('Lý do khách từ chối')->assertSee($decision['reason']);
        } else {
            $response->assertDontSee('Lý do khách từ chối');
        }
        $this->assertReadOnlyBookingDocument($response);
    }

    public function test_booking_intake_assignment_is_limited_to_authorized_active_candidates_and_audited(): void
    {
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['HoTen' => 'Nhân viên đang phụ trách']);
        DB::table('Booking')->insert([
            'BookingID' => 2,
            'TrangThai' => BookingStatus::Confirmed->value,
        ]);
        DB::table('NhanVien')->insert(['NhanVienID' => 3, 'HoTen' => 'Nhân viên cấp dưới', 'TrangThai' => 'Hoạt động']);
        DB::table('NhanVien')->insert(['NhanVienID' => 4, 'HoTen' => 'Quản lý hiện tại', 'ChucDanh' => 'Quản lý', 'TrangThai' => 'Hoạt động']);
        DB::table('NhanVien')->insert(['NhanVienID' => 5, 'HoTen' => 'Quản lý khác', 'ChucDanh' => 'Quản lý', 'TrangThai' => 'Hoạt động']);
        DB::table('NhanVien')->insert([
            'NhanVienID' => 6,
            'HoTen' => 'Nhân viên cấp dưới',
            'SoDienThoai' => '0900000006',
            'TrangThai' => 'Hoạt động',
        ]);
        $intake = $this->createBookingIntake(['BookingID' => 2, 'NhanVienID' => 1]);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->TaiKhoanID = 104;
        $manager->NhanVienID = 4;
        $manager->shouldReceive('isManager')->andReturn(true);
        $manager->shouldReceive('isActive')->andReturn(true);
        $manager->shouldReceive('isOwner')->andReturn(false);
        $manager->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => $permission === 'deliveries.edit',
        );
        $this->actingAs($manager)->withoutMiddleware();
        view()->share('errors', new ViewErrorBag);

        $this->get(route('deliveries.booking-documents.edit', $intake))
            ->assertOk()
            ->assertSee('Bản thân tôi')
            ->assertSee('Quản lý hiện tại')
            ->assertSee('Nhân viên cấp dưới')
            ->assertSee('0900000006')
            ->assertDontSee('value="5"', false)
            ->assertSee('value="1" selected', false);

        $payload = [
            'address' => '12 Nguyễn Huệ',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ];
        $allowedEmployeeIds = EmployeeAssignment::bookingIntakeCandidates($manager, $intake->NhanVienID)
            ->orderBy('NhanVienID')
            ->pluck('NhanVienID')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $this->assertSame([1, 3, 4, 6], $allowedEmployeeIds);
        $this->assertTrue(Validator::make(
            ['employee_id' => 5],
            ['employee_id' => EmployeeAssignment::bookingIntakeRule($manager, $intake->NhanVienID)],
        )->fails());
        $forbiddenEmployeeResponse = $this->put(route('deliveries.booking-documents.update', $intake), $payload + ['employee_id' => 5]);
        $this->assertSame(302, $forbiddenEmployeeResponse->status(), $forbiddenEmployeeResponse->getContent());
        $errors = $forbiddenEmployeeResponse->getSession()->get('errors')->getBag('default')->messages();
        $this->assertArrayHasKey('employee_id', $errors, json_encode($errors));
        $this->assertSame(1, (int) $intake->fresh()->NhanVienID);

        $this->withSession([]);
        $this->put(route('deliveries.booking-documents.update', $intake), $payload + ['employee_id' => 4])
            ->assertRedirect(route('deliveries.booking-documents.show', $intake));
        $this->assertSame(4, (int) $intake->fresh()->NhanVienID);

        $this->put(route('deliveries.booking-documents.update', $intake), $payload + ['employee_id' => 3])
            ->assertRedirect(route('deliveries.booking-documents.show', $intake));
        $this->assertSame(3, (int) $intake->fresh()->NhanVienID);
        $this->assertSame(2, DB::table('NhatKyHeThong')
            ->where('BangDuLieu', 'BookingGiaoNhan')
            ->where('BanGhiID', $intake->BookingGiaoNhanID)
            ->count());

        DB::table('BookingGiaoNhan')->where('BookingGiaoNhanID', $intake->BookingGiaoNhanID)->update([
            'TrangThai' => DeliveryStatus::Completed->dbValue(),
            'ThoiGianThucTe' => '2026-10-07 10:00:00',
        ]);
        $this->put(route('deliveries.booking-documents.update', $intake), $payload + ['employee_id' => 4])
            ->assertForbidden();
        $this->assertSame(3, (int) $intake->fresh()->NhanVienID);
    }

    public function test_user_without_assignment_permission_cannot_change_booking_intake_employee_id(): void
    {
        DB::table('Booking')->insert([
            'BookingID' => 2,
            'TrangThai' => BookingStatus::Confirmed->value,
        ]);
        $intake = $this->createBookingIntake(['BookingID' => 2, 'NhanVienID' => 1]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->TaiKhoanID = 100;
        $user->shouldReceive('isManager')->andReturn(true);
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isOwner')->andReturn(false);
        $user->shouldReceive('canPermission')->andReturn(false);
        $this->actingAs($user)->withoutMiddleware();
        view()->share('errors', new ViewErrorBag);

        $this->get(route('deliveries.booking-documents.show', $intake))
            ->assertOk()
            ->assertDontSee(route('deliveries.booking-documents.edit', $intake), false)
            ->assertDontSee('Kiểm tra và tạo đơn');

        $this->get(route('deliveries.booking-documents.edit', $intake))
            ->assertOk()
            ->assertDontSee('<select class="form-select" id="employee_id"', false)
            ->assertSee('id="employee_id" value="Chưa phân công" readonly', false);

        $this->put(route('deliveries.booking-documents.update', $intake), [
            'employee_id' => 2,
            'address' => '12 Nguyễn Huệ',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ])->assertForbidden();

        $this->assertSame(1, (int) $intake->fresh()->NhanVienID);
    }

    public function test_booking_intake_mutation_is_denied_without_permission_or_after_workflow_progress(): void
    {
        $intake = $this->createBookingIntake();
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isManager')->andReturn(false);
        $user->shouldReceive('isOwner')->andReturn(false);
        $user->shouldReceive('isCustomer')->andReturn(false);
        $user->shouldReceive('canPermission')->andReturn(false);
        $this->actingAs($user);
        $this->get(route('deliveries.booking-documents.edit', $intake))->assertForbidden();
        $this->post('/deliveries/booking-documents/'.$intake->BookingGiaoNhanID.'/cancel')->assertNotFound();
        $this->assertSame('Chờ thực hiện', $intake->fresh()->TrangThai);

        foreach ([2, 3, 4, 5] as $bookingId) {
            DB::table('Booking')->insert([
                'BookingID' => $bookingId,
                'TrangThai' => 'DaXacNhan',
                'HinhThucNhanDo' => 'Tại nhà',
            ]);
        }
        $received = $this->createBookingIntake([
            'BookingID' => 2,
            'TrangThai' => 'Đã nhận',
            'ThoiGianThucTe' => '2026-10-08 10:05:00',
        ]);
        $inspected = $this->createBookingIntake(['BookingID' => 3]);
        DB::table('BookingKiemKe')->insert(['BookingKiemKeID' => 1, 'BookingID' => 3]);
        $ordered = $this->createBookingIntake(['BookingID' => 4, 'DonHangID' => 2]);
        DB::table('DonHang')->insert(['DonHangID' => 2, 'BookingID' => 4]);
        $cancelled = $this->createBookingIntake(['BookingID' => 5, 'TrangThai' => 'Đã hủy']);
        $service = app(DeliveryService::class);

        foreach ([$received, $inspected, $ordered, $cancelled] as $document) {
            $this->assertFalse($service->canManageBookingIntake($document));
            $this->reject(fn () => $service->updateBookingIntake($document, ['address' => 'Thay đổi']), 'booking_delivery');
        }

        DB::table('Booking')->insert([
            'BookingID' => 6,
            'TrangThai' => BookingStatus::Cancelled->value,
            'HinhThucNhanDo' => 'Tại nhà',
        ]);
        $intakeForCancelledBooking = $this->createBookingIntake(['BookingID' => 6]);
        $this->assertFalse($service->canManageBookingIntake($intakeForCancelledBooking));
        $this->reject(
            fn () => $service->updateBookingIntake($intakeForCancelledBooking, ['address' => 'Không được sửa']),
            'booking_delivery',
        );
    }

    public function test_cancelled_booking_intake_cannot_be_confirmed_as_received(): void
    {
        $intake = $this->createBookingIntake(['TrangThai' => 'Đã hủy']);
        $staff = Mockery::mock(User::class)->makePartial();
        $staff->NhanVienID = 1;
        $this->actingAs($staff);

        $this->reject(
            fn () => app(BookingService::class)->markReceived(Booking::query()->findOrFail(1)),
            'booking',
        );
        $this->assertSame('Đã hủy', $intake->fresh()->TrangThai);
        $this->assertNull($intake->fresh()->ThoiGianThucTe);
        $this->assertSame(1, DB::table('DonHang')->where('BookingID', 1)->count());
        $this->assertSame(0, DB::table('BookingKiemKe')->where('BookingID', 1)->count());
    }

    public function test_unified_delivery_list_search_and_filters_cover_both_sources(): void
    {
        DB::table('KhachHang')->insert([
            'KhachHangID' => 2,
            'HoTen' => 'Khách Tìm Chung',
            'SoDienThoai' => '0901234567',
        ]);
        DB::table('Booking')->insert([
            'BookingID' => 2,
            'MaBooking' => 'DL-SEARCH-2',
            'KhachHangID' => 2,
            'TrangThai' => 'Đã xác nhận',
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 2,
            'MaDonHang' => 'DH-SEARCH-2',
            'KhachHangID' => 2,
            'BookingID' => 2,
        ]);
        DB::table('BookingGiaoNhan')->insert([
            'BookingID' => 2,
            'DonHangID' => 2,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'NhanVienID' => 1,
            'ThoiGianDuKien' => '2026-10-09 09:00:00',
            'TrangThai' => 'Đã nhận',
        ]);
        DB::table('GiaoNhan')->insert([
            'DonHangID' => 2,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => 'Địa chỉ khách tìm chung',
            'ThoiGianDuKien' => '2026-10-10 09:00:00',
            'TrangThai' => 'Đang thực hiện',
        ]);
        DB::table('GiaoNhan')->insert([
            'DonHangID' => 2,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => 'Địa chỉ nhận trùng với phiếu Booking',
            'ThoiGianDuKien' => '2026-10-09 09:00:00',
            'TrangThai' => 'Hoàn thành',
        ]);

        $service = app(DeliveryService::class);
        $searchResults = $service->getUnifiedDeliveries(['search' => 'Khách Tìm Chung']);
        $this->assertSame(2, $searchResults->total());
        $this->assertSame(
            ['booking', 'operational'],
            $searchResults->getCollection()->pluck('source_type')->sort()->values()->all(),
        );

        $received = $service->getUnifiedDeliveries(['method' => 'nhan_do', 'status' => 'received']);
        $this->assertSame(1, $received->total());
        $this->assertSame('booking', $received->first()->source_type);

        $delivering = $service->getUnifiedDeliveries(['method' => 'giao_do', 'status' => 'delivering']);
        $this->assertSame(1, $delivering->total());
        $this->assertSame('operational', $delivering->first()->source_type);

        $sorted = $service->getUnifiedDeliveries(['sort_order' => 'desc']);
        $this->assertSame('operational', $sorted->first()->source_type);
        $this->assertSame('2026-10-10 09:00:00', $sorted->first()->expected_at);
    }

    public function test_delivery_service_sets_created_at_when_creating_operational_delivery(): void
    {
        if (Schema::hasTable('GiaoNhan') && ! Schema::hasColumn('GiaoNhan', 'NgayTao')) {
            Schema::table('GiaoNhan', function (Blueprint $table): void {
                $table->dateTime('NgayTao')->nullable();
            });
        }

        $staff = DB::table('NhanVien')->insertGetId([
            'HoTen' => 'Nhân viên tạo phiếu',
            'TrangThai' => 'Hoạt động',
        ]);
        $order = DonHang::query()->forceCreate([
            'MaDonHang' => 'DH-CREATED-AT',
            'BookingID' => null,
            'KhachHangID' => null,
            'TrangThai' => OrderStatus::Pending->value,
            'NgayTao' => '2026-10-07 09:00:00',
            'NgayCapNhat' => '2026-10-07 09:00:00',
        ]);

        $delivery = app(DeliveryService::class)->create([
            'order_id' => $order->DonHangID,
            'method' => 'nhan_do',
            'fulfillment' => 'Tại cửa hàng',
            'employee_id' => $staff,
            'status' => 'pending',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '09:00',
            'NgayTao' => '2000-01-01 00:00:00',
        ]);

        $this->assertNotNull($delivery->fresh()->NgayTao);
        $this->assertEqualsWithDelta(now()->timestamp, $delivery->NgayTao->timestamp, 5);

        $createdAt = $delivery->NgayTao->toDateTimeString();
        $updated = app(DeliveryService::class)->update($delivery, [
            'notes' => 'Cập nhật ghi chú',
            'NgayTao' => '2001-01-01 00:00:00',
        ]);

        $this->assertSame($createdAt, $updated->NgayTao->toDateTimeString());
    }

    public function test_unified_delivery_list_orders_by_created_at_and_keeps_nulls_last(): void
    {
        if (Schema::hasTable('GiaoNhan') && ! Schema::hasColumn('GiaoNhan', 'NgayTao')) {
            Schema::table('GiaoNhan', function (Blueprint $table): void {
                $table->dateTime('NgayTao')->nullable();
            });
        }

        DB::table('Booking')->insert([
            'BookingID' => 9,
            'MaBooking' => 'DL-CREATED-9',
            'KhachHangID' => 1,
            'TrangThai' => 'Đã xác nhận',
        ]);
        DB::table('Booking')->insert([
            'BookingID' => 10,
            'MaBooking' => 'DL-CREATED-10',
            'KhachHangID' => 1,
            'TrangThai' => 'Đã xác nhận',
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 9,
            'MaDonHang' => 'DH-CREATED-9',
            'KhachHangID' => 1,
            'BookingID' => 9,
            'TrangThai' => OrderStatus::Washed->value,
            'NgayTao' => '2026-10-07 09:00:00',
            'NgayCapNhat' => '2026-10-07 09:00:00',
        ]);
        DB::table('BookingGiaoNhan')->insert([
            'BookingGiaoNhanID' => 21,
            'BookingID' => 9,
            'DonHangID' => 9,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'NhanVienID' => 1,
            'ThoiGianDuKien' => '2026-10-12 09:00:00',
            'TrangThai' => 'Đã nhận',
            'NgayTao' => '2026-10-06 08:00:00',
        ]);
        DB::table('BookingGiaoNhan')->insert([
            'BookingGiaoNhanID' => 22,
            'BookingID' => 10,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'NhanVienID' => 1,
            'ThoiGianDuKien' => '2026-10-08 09:00:00',
            'TrangThai' => 'Chờ thực hiện',
            'NgayTao' => '2026-10-07 11:00:00',
        ]);
        DB::table('GiaoNhan')->insert([
            'GiaoNhanID' => 21,
            'DonHangID' => 9,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Hàm Nghi',
            'ThoiGianDuKien' => '2026-10-09 09:00:00',
            'TrangThai' => 'Đang thực hiện',
            'NgayTao' => null,
        ]);
        DB::table('GiaoNhan')->insert([
            'GiaoNhanID' => 22,
            'DonHangID' => 9,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'DiaChi' => null,
            'ThoiGianDuKien' => '2026-10-10 09:00:00',
            'TrangThai' => 'Đang thực hiện',
            'NgayTao' => '2026-10-07 11:00:00',
        ]);

        $service = app(DeliveryService::class);
        $descendingRows = collect($service->getUnifiedDeliveries(['sort_order' => 'desc'])->items());
        $descending = $descendingRows
            ->filter(fn ($row) => $row->created_at !== null)
            ->pluck('created_at')
            ->all();
        $this->assertSame(
            collect($descending)->sortDesc()->values()->all(),
            $descending,
        );
        $this->assertSame(
            [['booking', 22], ['operational', 22], ['booking', 21]],
            $descendingRows->filter(fn ($row) => $row->created_at !== null)
                ->map(fn ($row) => [$row->source_type, (int) $row->source_id])
                ->values()
                ->all(),
        );
        $firstNullIndex = $descendingRows->search(fn ($row) => $row->created_at === null);
        $this->assertNotFalse($firstNullIndex);
        $this->assertTrue($descendingRows->slice($firstNullIndex)->every(fn ($row) => $row->created_at === null));

        $ascendingRows = collect($service->getUnifiedDeliveries(['sort_order' => 'asc'])->items());
        $ascending = $ascendingRows
            ->filter(fn ($row) => $row->created_at !== null)
            ->pluck('created_at')
            ->all();
        $this->assertSame(
            collect($ascending)->sort()->values()->all(),
            $ascending,
        );
        $this->assertSame(
            [['booking', 21], ['booking', 22], ['operational', 22]],
            $ascendingRows->filter(fn ($row) => $row->created_at !== null)
                ->map(fn ($row) => [$row->source_type, (int) $row->source_id])
                ->values()
                ->all(),
        );
        $firstNullIndex = $ascendingRows->search(fn ($row) => $row->created_at === null);
        $this->assertNotFalse($firstNullIndex);
        $this->assertTrue($ascendingRows->slice($firstNullIndex)->every(fn ($row) => $row->created_at === null));
    }

    public function test_unified_delivery_pagination_merges_before_paging_without_duplicates(): void
    {
        for ($index = 1; $index <= 6; $index++) {
            $bookingId = $index + 1;
            DB::table('Booking')->insert([
                'BookingID' => $bookingId,
                'MaBooking' => 'DL-PAGE-'.$bookingId,
                'TrangThai' => 'Đã xác nhận',
            ]);
            DB::table('BookingGiaoNhan')->insert([
                'BookingID' => $bookingId,
                'LoaiGiaoNhan' => 'NHAN_DO',
                'HinhThuc' => 'Tại cửa hàng',
                'NhanVienID' => 1,
                'ThoiGianDuKien' => now()->addDays($index)->toDateTimeString(),
                'TrangThai' => 'Chờ thực hiện',
            ]);
            DB::table('GiaoNhan')->insert([
                'DonHangID' => 1,
                'NhanVienID' => 1,
                'LoaiGiaoNhan' => 'GIAO_DO',
                'HinhThuc' => 'Tại nhà',
                'DiaChi' => 'Địa chỉ '.$index,
                'ThoiGianDuKien' => now()->addDays($index)->addHours(1)->toDateTimeString(),
                'TrangThai' => 'Chờ thực hiện',
            ]);
        }

        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        view()->share('errors', new ViewErrorBag);
        $this->actingAs($manager)->withoutMiddleware();

        $firstPage = $this->get(route('deliveries.index'))->assertOk();
        $secondPage = $this->get(route('deliveries.index', ['page' => 2]))->assertOk();
        preg_match_all('/data-delivery-key="([^"]+)"/', $firstPage->getContent(), $firstMatches);
        preg_match_all('/data-delivery-key="([^"]+)"/', $secondPage->getContent(), $secondMatches);
        $firstKeys = $firstMatches[1];
        $secondKeys = $secondMatches[1];

        $this->assertCount(10, $firstKeys);
        $this->assertCount(3, $secondKeys);
        $this->assertSame([], array_values(array_intersect($firstKeys, $secondKeys)));
        $this->assertCount(13, array_unique([...$firstKeys, ...$secondKeys]));
    }

    public function test_unified_delivery_list_handles_missing_sources_and_scopes_staff_rows(): void
    {
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        $manager->shouldReceive('canPermission')->andReturn(true);
        $this->actingAs($manager)->withoutMiddleware();
        DB::table('BookingGiaoNhan')->delete();
        $this->get(route('deliveries.index'))
            ->assertOk()
            ->assertSee('Chưa có dữ liệu giao nhận');

        DB::table('BookingGiaoNhan')->insert([
            'BookingID' => 1,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'NhanVienID' => 2,
            'TrangThai' => 'Chờ thực hiện',
        ]);
        DB::table('GiaoNhan')->insert([
            'DonHangID' => 1,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'TrangThai' => 'Chờ thực hiện',
        ]);
        $staff = Mockery::mock(User::class)->makePartial();
        $staff->TaiKhoanID = 100;
        $staff->NhanVienID = 1;
        $staff->TrangThai = 'Hoạt động';
        $staff->shouldReceive('isManager')->andReturn(false);
        view()->share('errors', new ViewErrorBag);

        $this->actingAs($staff)->withoutMiddleware()->get(route('deliveries.index'))
            ->assertOk()
            ->assertSee('data-delivery-key="operational:1"', false)
            ->assertDontSee('data-delivery-key="booking:2"', false);
    }

    public function test_received_booking_document_filter_does_not_leak_into_operational_deliveries(): void
    {
        DB::table('BookingGiaoNhan')->insert([
            'BookingID' => 1,
            'DonHangID' => null,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'TrangThai' => 'Đã nhận',
        ]);

        $this->assertSame(0, app(DeliveryService::class)->getAll(['status' => 'received'])->total());
        $this->assertSame(1, app(DeliveryService::class)->getBookingDocuments(['status' => 'received'])->total());
    }

    public function test_picking_status_filter_targets_intake_documents_in_both_lists(): void
    {
        DB::table('GiaoNhan')->insert([
            'DonHangID' => 1,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'TrangThai' => 'Đang thực hiện',
        ]);
        DB::table('BookingGiaoNhan')->insert([
            'BookingID' => 1,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại cửa hàng',
            'TrangThai' => 'Đang thực hiện',
        ]);

        $deliveries = app(DeliveryService::class)->getAll(['status' => 'picking']);
        $bookingDocuments = app(DeliveryService::class)->getBookingDocuments(['status' => 'picking']);

        $this->assertSame(1, $deliveries->total());
        $this->assertSame(1, $bookingDocuments->total());
        $this->assertSame('page', $deliveries->getPageName());
        $this->assertSame('booking_page', $bookingDocuments->getPageName());
    }

    public function test_booking_document_detail_route_rejects_users_without_delivery_view_permission(): void
    {
        $user = new User;
        $user->forceFill([
            'TaiKhoanID' => 100,
            'NhanVienID' => 1,
            'TrangThai' => 'Hoạt động',
        ]);

        $this->actingAs($user)
            ->get(route('deliveries.booking-documents.show', 1))
            ->assertForbidden();
    }

    public function test_pending_return_can_be_unscheduled_and_notes_edit_preserves_schedule_and_status(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        $this->assertNull($delivery->ThoiGianDuKien);
        $delivery = $service->update($delivery, ['notes' => 'Chưa chốt lịch']);
        $this->assertNull($delivery->ThoiGianDuKien);
        $this->assertSame('Chờ thực hiện', $delivery->TrangThai);
        $this->assertSame('Chưa chốt lịch', $delivery->GhiChu);
        $before = $delivery->getAttributes();
        $delivery = $service->update($delivery, ['notes' => 'Updated notes only']);
        unset($before['GhiChu']);
        $after = $delivery->getAttributes();
        unset($after['GhiChu']);
        $this->assertSame($before, $after, '77: omitted fields must be preserved');
    }

    public function test_54_stale_model_rejects_changed_parent_for_update_and_delete(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        DB::table('DonHang')->insert(['DonHangID' => 2]);
        DB::table('GiaoNhan')->where('GiaoNhanID', $delivery->getKey())->update(['DonHangID' => 2]);
        $this->reject(fn () => $service->update($delivery, ['notes' => 'Stale edit']), 'order_id');
        $this->reject(fn () => $service->delete($delivery), 'delivery');
        $this->assertSame(2, $delivery->fresh()->DonHangID);
        $this->assertNull($delivery->fresh()->GhiChu);
    }

    public static function malformedServicePayloads(): array
    {
        return [
            [['method' => 'other'], 'method'], [['status' => 'unknown'], 'status'],
            [['address' => ''], 'address'], [['address' => str_repeat('a', 256)], 'address'],
            [['pickup_date' => 'bad', 'pickup_time' => '10:00'], 'pickup_date'],
            [['pickup_date' => '2026-10-08', 'pickup_time' => '25:90'], 'pickup_time'],
            [['employee_id' => 999], 'employee_id'], [['employee_id' => 'abc'], 'employee_id'],
            [['employee_id' => 2], 'employee_id'], [['fulfillment' => 'other'], 'fulfillment'],
        ];
    }

    #[DataProvider('malformedServicePayloads')]
    public function test_55_direct_service_rejects_malformed_payload(array $changes, string $field): void
    {
        $this->reject(fn () => app(DeliveryService::class)->create($this->data($changes)), $field);
        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_84_86_87_delivery_assignment_request_and_service_matrix(): void
    {
        foreach (['Hoạt động', 'Ngừng hoạt động', 'Khóa'] as $status) {
            DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => $status]);
            $response = $this->withoutMiddleware()->post(route('deliveries.store'), $this->data());
            if ($status === 'Hoạt động') {
                $response->assertRedirect(route('deliveries.show', 1))
                    ->assertSessionHasNoErrors()
                    ->assertSessionMissing('error');
                $this->assertSame(1, GiaoNhan::count());
                GiaoNhan::query()->delete();
            } else {
                $response->assertSessionHasErrors('employee_id');
                $this->reject(fn () => app(DeliveryService::class)->create($this->data()), 'employee_id');
            }
            $this->assertSame(0, GiaoNhan::count());
        }
        foreach ([999, 'abc'] as $id) {
            $this->withoutMiddleware()->post(route('deliveries.store'), $this->data(['employee_id' => $id]))->assertSessionHasErrors('employee_id');
        }
    }

    public function test_88_delivery_requires_an_assigned_employee(): void
    {
        foreach ([null, ''] as $employeeId) {
            $response = $this->withoutMiddleware()->post(route('deliveries.store'), $this->data(['employee_id' => $employeeId]));
            $errors = $response->getSession()->get('errors')->getBag('default')->messages();
            $this->assertArrayHasKey('employee_id', $errors);
            $this->reject(fn () => app(DeliveryService::class)->create($this->data(['employee_id' => $employeeId])), 'employee_id');
            $this->assertSame(0, GiaoNhan::count());
        }
    }

    public function test_request_accepts_unscheduled_return_and_rejects_incomplete_date_pair(): void
    {
        $this->withoutMiddleware()->post(route('deliveries.store'), $this->data())
            ->assertRedirect(route('deliveries.show', 1))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');
        $this->assertSame(1, GiaoNhan::count());
        $this->withoutMiddleware()->put(route('deliveries.update', 1), $this->data(['pickup_date' => '2026-10-08']))
            ->assertSessionHasErrors('pickup_time');
        $this->assertNull(GiaoNhan::first()->ThoiGianDuKien);
    }

    public function test_booking_return_creation_is_idempotent_and_cancelled_leg_can_be_replaced(): void
    {
        $service = app(DeliveryService::class);
        $return = $service->create($this->data());
        $service->create($this->data(['method' => 'nhan_do', 'pickup_date' => '2026-10-08', 'pickup_time' => '10:00']));
        $repeatedReturn = $service->create($this->data());
        $this->assertSame($return->GiaoNhanID, $repeatedReturn->GiaoNhanID);
        $service->cancelReturnDelivery($return);
        $service->create($this->data());
        $this->assertSame(3, GiaoNhan::count());
    }

    public function test_http_repeated_booking_return_creation_reuses_the_existing_slip(): void
    {
        $delivery = app(DeliveryService::class)->create($this->data());
        $this->withSession([])->withoutMiddleware()->post(route('deliveries.store'), $this->data())
            ->assertRedirect(route('deliveries.show', $delivery))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, GiaoNhan::count());
    }

    public function test_home_return_assignment_screen_does_not_change_order_or_create_a_slip(): void
    {
        $order = DonHang::findOrFail(1);
        $response = app(GiaoNhanController::class)->assignForOrder(
            $this->requestWithPermissions(['deliveries.create']),
            $order,
        );

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('admin.deliveries.create', $response->name());
        $this->assertSame(1, $response->getData()['selectedOrderId']);
        $this->assertSame('giao_do', $response->getData()['selectedMethod']);
        $this->assertTrue($response->getData()['lockDeliveryType']);
        view()->share('errors', new ViewErrorBag);
        $html = $response->render();
        $this->assertMatchesRegularExpression('/<select[^>]*id="method"[^>]*disabled/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="method" value="giao_do">/', $html);
        $this->assertStringContainsString('value="12 Nguyễn Huệ"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="address"[^>]*disabled/', $html);
        $this->assertStringContainsString('Lưu lịch giao nhận', $html);
        $this->assertStringContainsString('btn btn-success', $html);
        $this->assertDoesNotMatchRegularExpression('/>Hủy<\/a>/', $html);
        $this->assertSame(OrderStatus::Washed->value, $order->fresh()->TrangThai);
        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_booking_store_return_assignment_screen_uses_store_fulfillment_without_an_address(): void
    {
        DB::table('Booking')->where('BookingID', 1)->update([
            'HinhThucTraDo' => 'Tại cửa hàng',
            'DiaChiTra' => null,
        ]);
        DB::table('BookingGiaoNhan')->where('BookingID', 1)->update([
            'HinhThuc' => 'Tại cửa hàng',
            'DiaChi' => null,
        ]);
        $response = app(GiaoNhanController::class)->assignForOrder(
            $this->requestWithPermissions(['deliveries.create']),
            DonHang::findOrFail(1),
        );

        $this->assertInstanceOf(View::class, $response);
        $this->assertSame('Tại cửa hàng', $response->getData()['selectedFulfillment']);
        $this->assertNull($response->getData()['selectedAddress']);
        view()->share('errors', new ViewErrorBag);
        $html = $response->render();
        $this->assertMatchesRegularExpression('/<input[^>]*class="form-control delivery-locked-control bg-light text-muted"[^>]*id="address"[^>]*disabled/', $html);
        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_washing_completion_does_not_automatically_create_a_booking_return_slip(): void
    {
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Washing->value]);

        app(OrderService::class)->updateStatus(DonHang::findOrFail(1), OrderStatus::Washed->value);

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame(0, GiaoNhan::where('DonHangID', 1)->where('LoaiGiaoNhan', 'GIAO_DO')->count());
    }

    public function test_existing_return_slip_is_reused_by_assignment_screen(): void
    {
        $delivery = app(DeliveryService::class)->create($this->data());
        $response = app(GiaoNhanController::class)->assignForOrder(
            $this->requestWithPermissions(['deliveries.edit']),
            DonHang::findOrFail(1),
        );

        $this->assertSame(route('deliveries.edit', $delivery), $response->getTargetUrl());
        $this->assertSame(1, GiaoNhan::count());
        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
    }

    public function test_assignment_screen_requires_create_permission_when_no_return_slip_exists(): void
    {
        try {
            app(GiaoNhanController::class)->assignForOrder(
                $this->requestWithPermissions(['deliveries.edit']),
                DonHang::findOrFail(1),
            );
            $this->fail('A user without delivery-create permission was allowed to open the assignment screen.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_assignment_screen_requires_edit_permission_for_an_existing_return_slip(): void
    {
        app(DeliveryService::class)->create($this->data());

        try {
            app(GiaoNhanController::class)->assignForOrder(
                $this->requestWithPermissions(['deliveries.create']),
                DonHang::findOrFail(1),
            );
            $this->fail('A user without delivery-edit permission was allowed to edit an active delivery.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_store_return_cannot_create_a_home_delivery_leg(): void
    {
        DB::table('Booking')->where('BookingID', 1)->update(['HinhThucTraDo' => 'Tại cửa hàng']);
        DB::table('BookingGiaoNhan')->where('BookingID', 1)->update(['HinhThuc' => 'Tại cửa hàng', 'DiaChi' => null]);

        $this->reject(fn () => app(DeliveryService::class)->create($this->data()), 'method');

        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_booking_store_return_is_created_after_washing_and_handover_completes_booking(): void
    {
        DB::table('Booking')->where('BookingID', 1)->update([
            'HinhThucTraDo' => 'Tại cửa hàng',
            'DiaChiTra' => null,
        ]);
        DB::table('BookingGiaoNhan')->where('BookingID', 1)->update([
            'HinhThuc' => 'Tại cửa hàng',
            'DiaChi' => null,
            'PhiGiaoNhan' => 0,
        ]);

        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data([
            'fulfillment' => 'Tại cửa hàng',
            'address' => null,
        ]));

        $this->assertSame('Tại cửa hàng', $delivery->HinhThuc);
        $this->assertNull($delivery->DiaChi);
        $this->assertSame('Chờ thực hiện', $delivery->TrangThai);
        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame('DaXacNhan', DB::table('Booking')->where('BookingID', 1)->value('TrangThai'));

        $this->withoutMiddleware()
            ->post(route('deliveries.advance-progress', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery))
            ->assertSessionHas('success');

        $completed = $delivery->fresh();
        $this->assertSame('Hoàn thành', $completed->TrangThai);
        $this->assertNotNull($completed->ThoiGianThucTe);
        $this->assertSame(OrderStatus::Delivered->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame('HoanThanh', DB::table('Booking')->where('BookingID', 1)->value('TrangThai'));
        $this->assertSame(0, DB::table('ThanhToan')->count());

        $this->post(route('deliveries.advance-progress', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery))
            ->assertSessionHas('error');
        $this->assertSame(1, GiaoNhan::where('DonHangID', 1)->where('LoaiGiaoNhan', 'GIAO_DO')->count());
    }

    public function test_booking_return_creation_requires_washed_order_and_rejects_cancelled_booking_over_http(): void
    {
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Washing->value]);

        $this->withoutMiddleware()->postJson(route('deliveries.store'), $this->data())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_id');
        $this->assertSame(0, GiaoNhan::count());

        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Washed->value]);
        DB::table('Booking')->where('BookingID', 1)->update(['TrangThai' => 'DaHuy']);

        $this->postJson(route('deliveries.store'), $this->data())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking');
        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_return_leg_cannot_start_without_an_active_assignee(): void
    {
        $delivery = app(DeliveryService::class)->create($this->data());
        DB::table('GiaoNhan')->where('GiaoNhanID', $delivery->GiaoNhanID)->update(['NhanVienID' => null]);
        $delivery = $delivery->fresh();
        $this->reject(fn () => app(DeliveryService::class)->update($delivery, [
            'status' => 'delivering',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ]), 'employee_id');

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame('Chờ thực hiện', $delivery->fresh()->TrangThai);
    }

    public function test_delivery_start_and_completion_sync_order_atomically_without_changing_payment(): void
    {
        DB::table('NhanVien')->insert(['NhanVienID' => 3, 'TrangThai' => 'Hoạt động']);
        $delivery = app(DeliveryService::class)->create($this->data(['employee_id' => 3]));
        $this->assertSame(4200.0, (float) $delivery->PhiGiaoNhan);
        DB::table('ThanhToan')->insert([
            'DonHangID' => 1,
            'TrangThai' => PaymentStatus::Paid->value,
            'SoTien' => 10000,
        ]);

        $service = app(DeliveryService::class);
        $delivery = $service->update($delivery, [
            'status' => 'delivering',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ]);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);
        $this->assertSame(OrderStatus::Delivering->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));

        $delivery = $service->update($delivery, ['status' => 'completed']);
        $this->assertSame('Hoàn thành', $delivery->TrangThai);
        $this->assertSame(OrderStatus::Delivered->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame('HoanThanh', DB::table('Booking')->where('BookingID', 1)->value('TrangThai'));
        $this->assertNotNull($delivery->fresh()->ThoiGianThucTe);
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 1)->count());
    }

    public function test_progress_action_advances_pickup_to_completion_and_rejects_terminal_slip(): void
    {
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Received->value]);
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data([
            'method' => 'nhan_do',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ]));

        $delivery = $service->advanceProgress($delivery);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);

        $delivery = $service->advanceProgress($delivery);
        $this->assertSame('Hoàn thành', $delivery->TrangThai);
        $this->reject(fn () => $service->advanceProgress($delivery), 'status');
    }

    public function test_progress_action_route_starts_return_delivery_and_syncs_order(): void
    {
        $delivery = app(DeliveryService::class)->create($this->data([
            'employee_id' => 1,
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ]));

        $this->withoutMiddleware()
            ->post(route('deliveries.advance-progress', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery))
            ->assertSessionHas('success');

        $this->assertSame('Đang thực hiện', $delivery->fresh()->TrangThai);
        $this->assertSame(OrderStatus::Delivering->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
    }

    public function test_progress_action_reports_missing_return_schedule_without_changing_status(): void
    {
        $delivery = app(DeliveryService::class)->create($this->data(['employee_id' => 1]));

        $this->withoutMiddleware()
            ->post(route('deliveries.advance-progress', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery))
            ->assertSessionHas('error');

        $this->assertSame('Chờ thực hiện', $delivery->fresh()->TrangThai);
        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
    }

    public function test_progress_action_shows_a_clear_message_when_pickup_schedule_is_missing(): void
    {
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Received->value]);
        DB::table('GiaoNhan')->insert([
            'DonHangID' => 1,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'NHAN_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'TrangThai' => 'Chờ thực hiện',
        ]);
        $delivery = GiaoNhan::query()->firstOrFail();

        $this->withoutMiddleware()
            ->post(route('deliveries.advance-progress', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery))
            ->assertSessionHas('error', 'Vui lòng chọn ngày giao nhận trước khi tiếp tục.');

        $this->assertSame('Chờ thực hiện', $delivery->fresh()->TrangThai);
    }

    public function test_technical_failure_rolls_back_delivery_and_order_together(): void
    {
        $delivery = app(DeliveryService::class)->create($this->data());
        $orderService = Mockery::mock(OrderService::class);
        $orderService->shouldReceive('syncStatusFromDelivery')
            ->once()
            ->andThrow(new RuntimeException('Simulated sync failure.'));
        $service = new DeliveryService($orderService);

        try {
            $service->update($delivery, [
                'status' => 'delivering',
                'pickup_date' => '2026-10-08',
                'pickup_time' => '10:00',
            ]);
            $this->fail('Expected the transactional sync to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated sync failure.', $exception->getMessage());
        }

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame('Chờ thực hiện', $delivery->fresh()->TrangThai);
    }

    public function test_failed_attempt_is_preserved_and_allows_a_new_return_attempt(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        $delivery = $service->update($delivery, [
            'status' => 'delivering',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ]);
        $delivery = $service->cancelReturnDelivery($delivery);

        $this->assertSame('Đã hủy', $delivery->TrangThai);
        $this->assertSame(OrderStatus::Delivering->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertSame(1, GiaoNhan::count());

        $retry = $service->create($this->data());

        $this->assertNotSame($delivery->getKey(), $retry->getKey());
        $this->assertSame(2, GiaoNhan::count());
        $this->assertSame('Đã hủy', $delivery->fresh()->TrangThai);
        $this->assertSame('Chờ thực hiện', $retry->TrangThai);
    }

    public function test_return_cancellation_keeps_delivery_order_and_payment_history_and_requires_delete_permission(): void
    {
        DB::table('ThanhToan')->insert([
            'DonHangID' => 1,
            'TrangThai' => 'Đã thanh toán',
            'SoTien' => 15000,
        ]);
        $delivery = app(DeliveryService::class)->create($this->data());
        $delivery = app(DeliveryService::class)->update($delivery, [
            'status' => 'delivering',
            'pickup_date' => '2026-10-08',
            'pickup_time' => '10:00',
        ]);
        $this->reject(
            fn () => app(DeliveryService::class)->update($delivery, ['status' => 'cancelled']),
            'status',
        );
        $editor = Mockery::mock(User::class)->makePartial();
        $editor->shouldReceive('isManager')->andReturn(true);
        $editor->shouldReceive('isActive')->andReturn(true);
        $editor->shouldReceive('isCustomer')->andReturn(false);
        $editor->shouldReceive('canPermission')
            ->andReturnUsing(fn (string $permission): bool => $permission === 'deliveries.edit');
        $this->actingAs($editor)
            ->put(route('deliveries.update', $delivery), $this->data(['status' => 'cancelled']))
            ->assertSessionHasErrors('status');
        $this->assertSame('Đang thực hiện', $delivery->fresh()->TrangThai);

        $unauthorized = Mockery::mock(User::class)->makePartial();
        $unauthorized->shouldReceive('isActive')->andReturn(true);
        $unauthorized->shouldReceive('isCustomer')->andReturn(false);
        $unauthorized->shouldReceive('isOwner')->andReturn(false);
        $unauthorized->shouldReceive('canPermission')->andReturn(false);
        $this->actingAs($unauthorized)
            ->post(route('deliveries.cancel-return', $delivery))
            ->assertForbidden();
        $this->assertSame('Đang thực hiện', $delivery->fresh()->TrangThai);

        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        $manager->shouldReceive('isActive')->andReturn(true);
        $manager->shouldReceive('isCustomer')->andReturn(false);
        $manager->shouldReceive('isOwner')->andReturn(false);
        $manager->shouldReceive('canPermission')->andReturnUsing(
            fn (string $permission): bool => in_array($permission, ['deliveries.delete', 'deliveries.view'], true),
        );
        $this->actingAs($manager)
            ->post(route('deliveries.cancel-return', $delivery))
            ->assertRedirect(route('deliveries.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, GiaoNhan::query()->whereKey($delivery->getKey())->count());
        $this->assertSame('Đã hủy', $delivery->fresh()->TrangThai);
        $this->assertSame(OrderStatus::Delivering->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
        $this->assertDatabaseHas('ThanhToan', [
            'DonHangID' => 1,
            'TrangThai' => 'Đã thanh toán',
            'SoTien' => 15000,
        ]);
        $this->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Đã hủy')
            ->assertDontSee(route('deliveries.cancel-return', $delivery), false)
            ->assertDontSee(route('deliveries.edit', $delivery), false);

        $this->assertSame(2, app(DeliveryService::class)->create($this->data())->GiaoNhanID);
        $this->assertSame(2, GiaoNhan::query()->count());
    }

    public function test_staff_cannot_cancel_a_return_delivery_outside_their_assignment_scope(): void
    {
        DB::table('NhanVien')->insert(['NhanVienID' => 3, 'HoTen' => 'Nhân viên khác', 'TrangThai' => 'Hoạt động']);
        $delivery = app(DeliveryService::class)->create($this->data());
        $staff = Mockery::mock(User::class)->makePartial();
        $staff->NhanVienID = 3;
        $staff->shouldReceive('isActive')->andReturn(true);
        $staff->shouldReceive('isManager')->andReturn(false);
        $staff->shouldReceive('isOwner')->andReturn(false);
        $staff->shouldReceive('isCustomer')->andReturn(false);
        $staff->shouldReceive('canPermission')
            ->andReturnUsing(fn (string $permission): bool => $permission === 'deliveries.delete');

        $this->actingAs($staff)
            ->post(route('deliveries.cancel-return', $delivery))
            ->assertForbidden();

        $this->assertSame('Chờ thực hiện', $delivery->fresh()->TrangThai);
    }

    public function test_completed_return_delivery_cannot_be_cancelled_and_cannot_be_physically_deleted(): void
    {
        DB::table('Booking')->where('BookingID', 1)->update(['HinhThucTraDo' => 'Tại cửa hàng']);
        DB::table('BookingGiaoNhan')->where('BookingID', 1)->where('LoaiGiaoNhan', 'GIAO_DO')
            ->update(['HinhThuc' => 'Tại cửa hàng']);
        $delivery = app(DeliveryService::class)->create($this->data(['fulfillment' => 'Tại cửa hàng']));
        $delivery = app(DeliveryService::class)->update($delivery, ['status' => 'completed']);
        $manager = Mockery::mock(User::class)->makePartial();
        $manager->shouldReceive('isManager')->andReturn(true);
        $manager->shouldReceive('isActive')->andReturn(true);
        $manager->shouldReceive('isCustomer')->andReturn(false);
        $manager->shouldReceive('canPermission')->andReturn(true);
        $this->actingAs($manager);

        $this->post(route('deliveries.cancel-return', $delivery))
            ->assertRedirect(route('deliveries.show', $delivery))
            ->assertSessionHasErrors('delivery');
        $this->delete(route('deliveries.destroy', $delivery))
            ->assertSessionHasErrors('delivery');
        try {
            app(DeliveryService::class)->delete($delivery);
            $this->fail('GIAO_DO must not be physically deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delivery', $exception->errors());
        }

        $this->assertSame('Hoàn thành', $delivery->fresh()->TrangThai);
        $this->assertSame(1, GiaoNhan::query()->count());
        $this->assertSame(OrderStatus::Delivered->value, DB::table('DonHang')->where('DonHangID', 1)->value('TrangThai'));
    }

    public function test_paid_order_return_delivery_is_not_cancellable_when_current_flow_cannot_reschedule_it(): void
    {
        DB::table('DonHang')->insert(['DonHangID' => 2, 'TrangThai' => OrderStatus::Washed->value]);
        $deliveryId = DB::table('GiaoNhan')->insertGetId([
            'DonHangID' => 2,
            'NhanVienID' => 1,
            'LoaiGiaoNhan' => 'GIAO_DO',
            'HinhThuc' => 'Tại nhà',
            'DiaChi' => '12 Nguyễn Huệ',
            'TrangThai' => 'Chờ thực hiện',
        ], 'GiaoNhanID');
        DB::table('DonHang')->where('DonHangID', 2)->update(['TrangThai' => OrderStatus::Paid->value]);
        $delivery = GiaoNhan::query()->findOrFail($deliveryId);

        $this->assertFalse(app(DeliveryService::class)->canCancelReturnDelivery($delivery));
        $this->reject(
            fn () => app(DeliveryService::class)->cancelReturnDelivery($delivery),
            'delivery',
        );
        $this->assertSame('Chờ thực hiện', $delivery->fresh()->TrangThai);
        $this->assertSame(OrderStatus::Paid->value, DB::table('DonHang')->where('DonHangID', 2)->value('TrangThai'));
    }

    private function requestWithPermissions(array $permissions): Request
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isManager')->andReturn(true);
        $user->shouldReceive('canPermission')
            ->andReturnUsing(fn (string $permission): bool => in_array($permission, $permissions, true));

        $request = Request::create('/orders/1/delivery-assignment');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    #[DataProvider('immutableOrders')]
    public function test_all_mutations_reject_cancelled_or_paid_parent(string $status): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => $status]);
        foreach ([fn () => $service->create($this->data(['method' => 'nhan_do'])), fn () => $service->update($delivery, ['notes' => 'Changed']), fn () => $service->delete($delivery)] as $operation) {
            try {
                $operation();
                $this->fail('Immutable order accepted a mutation.');
            } catch (ValidationException|SettledOrderException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertSame(1, GiaoNhan::count());
        $this->assertNull($delivery->fresh()->GhiChu);
    }

    public static function immutableOrders(): array
    {
        return [[OrderStatus::Cancelled->value]];
    }

    public function test_return_execution_waits_for_washing_and_requires_schedule(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Received->value]);
        $this->reject(fn () => $service->update($delivery, ['status' => 'delivering']), 'pickup_date');
        $scheduled = ['pickup_date' => '2026-10-08', 'pickup_time' => '10:00'];
        $this->reject(fn () => $service->update($delivery, $scheduled + ['status' => 'delivering']), 'status');
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Washed->value]);
        $delivery = $service->update($delivery, $scheduled + ['status' => 'delivering']);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);
        $delivery = $service->update($delivery, ['notes' => 'Đang giao']);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);
        $delivery = $service->update($delivery, ['status' => 'completed']);
        $this->reject(fn () => $service->update($delivery, ['status' => 'pending']), 'delivery');
        $this->reject(fn () => $service->delete($delivery), 'delivery');
        $this->assertSame($delivery->GiaoNhanID, $service->create($this->data())->GiaoNhanID);
        $this->assertSame(1, GiaoNhan::count());
    }

    public function test_pickup_execution_preserves_type_when_editing_notes_and_cannot_go_back(): void
    {
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Received->value]);
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data(['method' => 'nhan_do', 'status' => 'picking', 'pickup_date' => '2026-10-08', 'pickup_time' => '10:00']));
        $delivery = $service->update($delivery, ['notes' => 'Đang nhận đồ']);
        $this->assertSame('Đang thực hiện', $delivery->TrangThai);
        $this->reject(fn () => $service->update($delivery, ['status' => 'pending']), 'status');
        $this->reject(fn () => $service->update($delivery, ['status' => 'delivering']), 'status');
    }

    public function test_pickup_requires_schedule_and_cannot_execute_after_washing_starts(): void
    {
        $service = app(DeliveryService::class);
        $this->reject(fn () => $service->create($this->data(['method' => 'nhan_do'])), 'pickup_date');
        DB::table('DonHang')->where('DonHangID', 1)->update(['TrangThai' => OrderStatus::Washing->value]);
        $this->reject(fn () => $service->create($this->data(['method' => 'nhan_do', 'status' => 'picking', 'pickup_date' => '2026-10-08', 'pickup_time' => '10:00'])), 'status');
        $this->assertSame(0, GiaoNhan::count());
    }

    public function test_existing_slip_cannot_change_order_or_leg(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data());
        DB::table('DonHang')->insert(['DonHangID' => 2]);
        $this->reject(fn () => $service->update($delivery, ['order_id' => 2]), 'order_id');
        $this->reject(fn () => $service->update($delivery, ['method' => 'nhan_do', 'pickup_date' => '2026-10-08', 'pickup_time' => '10:00']), 'order_id');
        $this->assertSame(1, $delivery->fresh()->DonHangID);
    }

    public function test_active_assignment_and_unchanged_historical_employee(): void
    {
        $service = app(DeliveryService::class);
        $this->reject(fn () => $service->create($this->data(['employee_id' => 2])), 'employee_id');
        $delivery = $service->create($this->data());
        DB::table('NhanVien')->where('NhanVienID', 1)->update(['TrangThai' => 'Khóa']);
        $delivery = $service->update($delivery, ['notes' => 'Giữ phân công cũ']);
        $this->assertSame(1, $delivery->NhanVienID);
        $this->reject(fn () => $service->update($delivery, ['employee_id' => 2]), 'employee_id');
    }

    public function test_unchanged_past_schedule_is_preserved_but_new_past_schedule_rejected(): void
    {
        $service = app(DeliveryService::class);
        $delivery = $service->create($this->data(['pickup_date' => '2026-10-08', 'pickup_time' => '10:00']));
        DB::table('GiaoNhan')->where('GiaoNhanID', $delivery->GiaoNhanID)->update(['ThoiGianDuKien' => '2026-10-08 10:00:37']);
        $delivery = $delivery->fresh();
        $this->travelTo(now()->addDays(3));
        $delivery = $service->update($delivery, ['notes' => 'Cập nhật ghi chú']);
        $this->assertSame('2026-10-08 10:00:37', $delivery->ThoiGianDuKien->format('Y-m-d H:i:s'));
        $this->withoutMiddleware()->put(route('deliveries.update', $delivery), $this->data(['pickup_date' => '2026-10-08', 'pickup_time' => '10:00']))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-08 10:00:37', $delivery->fresh()->ThoiGianDuKien->format('Y-m-d H:i:s'));
        $this->reject(fn () => $service->update($delivery, ['pickup_date' => '2026-10-09', 'pickup_time' => '10:00']), 'pickup_time');
        $delivery = $service->update($delivery, ['pickup_date' => null, 'pickup_time' => null]);
        $this->assertNull($delivery->ThoiGianDuKien);
    }

    public static function deliveryProgressDisplayCases(): array
    {
        return [
            ['NHAN_DO', 'Đang thực hiện', 'Đang nhận đồ', 'Hiện tại'],
            ['GIAO_DO', 'Đang thực hiện', 'Đang giao đồ', 'Hiện tại'],
            ['GIAO_DO', 'Hoàn thành', 'Hoàn thành', 'Đã hoàn tất'],
        ];
    }

    #[DataProvider('deliveryProgressDisplayCases')]
    public function test_delivery_detail_displays_progress_for_the_selected_leg(
        string $deliveryType,
        string $status,
        string $currentStep,
        string $currentLabel,
    ): void {
        $delivery = (new GiaoNhan)->forceFill([
            'GiaoNhanID' => 1,
            'MaGiaoNhan' => 'GH0001',
            'LoaiGiaoNhan' => $deliveryType,
            'HinhThuc' => 'Tại nhà',
            'TrangThai' => $status,
        ]);
        $delivery->setRelation('donHang', null);
        $delivery->setRelation('nhanVien', null);

        $html = view('admin.deliveries.show', [
            'delivery' => $delivery,
            'displayCode' => 'GN-1',
            'canCancelReturnDelivery' => false,
            'canEditReturnDelivery' => false,
        ])->render();

        $this->assertStringContainsString('GN-1', $html);
        $this->assertStringContainsString('Tiến độ giao nhận', $html);
        $this->assertStringContainsString($currentStep, $html);
        $this->assertStringContainsString($currentLabel, $html);
    }
}
