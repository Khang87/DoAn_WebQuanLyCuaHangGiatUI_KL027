<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\RejectCustomerRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CustomerUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
            $table->string('DiaChi')->nullable();
            $table->dateTime('NgayTao')->nullable();
            $table->string('TrangThai')->nullable();
        });

        Schema::create('DiemTichLuy', function (Blueprint $table): void {
            $table->increments('DiemTichLuyID');
            $table->unsignedInteger('KhachHangID')->unique();
            $table->integer('DiemHienTai')->default(0);
            $table->dateTime('NgayCapNhat')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('DiemTichLuy');
        Schema::dropIfExists('KhachHang');

        parent::tearDown();
    }

    public function test_customer_can_be_updated_without_changing_the_existing_email(): void
    {
        $customerId = DB::table('KhachHang')->insertGetId([
            'HoTen' => 'Tên cũ',
            'Email' => 'customer@example.com',
            'SoDienThoai' => '0901234567',
        ], 'KhachHangID');

        $response = $this->withoutMiddleware()->put(route('customers.update', $customerId), [
            'HoTen' => 'Tên mới',
            'Email' => 'customer@example.com',
            'SoDienThoai' => '0901234567',
            'DiaChi' => 'Địa chỉ mới',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('KhachHang', [
            'KhachHangID' => $customerId,
            'HoTen' => 'Tên mới',
            'Email' => 'customer@example.com',
            'SoDienThoai' => '0901234567',
            'DiaChi' => 'Địa chỉ mới',
        ]);
    }

    public function test_duplicate_customer_email_returns_the_specific_validation_message(): void
    {
        DB::table('KhachHang')->insert([
            ['HoTen' => 'Khách thứ nhất', 'Email' => 'duplicate@example.com', 'SoDienThoai' => null],
            ['HoTen' => 'Khách thứ hai', 'Email' => null, 'SoDienThoai' => null],
        ]);

        $response = $this->actingAs($this->makeAuthenticatedUser())->withoutMiddleware([
            RejectCustomerRole::class,
            EnsureUserHasPermission::class,
        ])->from(route('customers.create'))
            ->post(route('customers.store'), [
                'HoTen' => 'Khách mới',
                'Email' => 'duplicate@example.com',
            ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['Email']);
        $this->assertSame(
            'Email này đã được đăng ký trước đó rồi.',
            session('errors')->getBag('default')->first('Email'),
        );
    }

    public function test_duplicate_customer_phone_returns_the_specific_validation_message(): void
    {
        DB::table('KhachHang')->insert([
            ['HoTen' => 'Khách thứ nhất', 'Email' => null, 'SoDienThoai' => '0901234567'],
            ['HoTen' => 'Khách thứ hai', 'Email' => null, 'SoDienThoai' => null],
        ]);

        $response = $this->actingAs($this->makeAuthenticatedUser())->withoutMiddleware([
            RejectCustomerRole::class,
            EnsureUserHasPermission::class,
        ])->from(route('customers.create'))
            ->post(route('customers.store'), [
                'HoTen' => 'Khách mới',
                'SoDienThoai' => '0901234567',
            ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['SoDienThoai']);
        $this->assertSame(
            'Số điện thoại này đã được đăng ký trước đó rồi.',
            session('errors')->getBag('default')->first('SoDienThoai'),
        );
    }

    public function test_customer_creation_requires_at_least_one_contact_method(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => 'Khách không có liên hệ',
        ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['Email']);
        $this->assertSame(
            'Vui lòng nhập số điện thoại hoặc email.',
            session('errors')->getBag('default')->first('Email'),
        );
        $this->assertSame(0, DB::table('KhachHang')->count());
    }

    public function test_customer_can_be_created_with_phone_without_email(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => 'Khách dùng số điện thoại',
            'SoDienThoai' => '0901234567',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('KhachHang', [
            'HoTen' => 'Khách dùng số điện thoại',
            'SoDienThoai' => '0901234567',
            'Email' => null,
        ]);
    }

    public function test_customer_can_be_created_with_email_without_phone(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => 'Khách dùng email',
            'Email' => 'email-only@example.com',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('KhachHang', [
            'HoTen' => 'Khách dùng email',
            'Email' => 'email-only@example.com',
            'SoDienThoai' => null,
        ]);
    }

    public function test_customer_can_be_created_with_both_contact_methods(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => 'Khách có đủ liên hệ',
            'Email' => 'both@example.com',
            'SoDienThoai' => '0901234568',
        ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('KhachHang', [
            'HoTen' => 'Khách có đủ liên hệ',
            'Email' => 'both@example.com',
            'SoDienThoai' => '0901234568',
        ]);
    }

    public function test_customer_creation_requires_a_name(): void
    {
        $response = $this->submitCustomerCreation([
            'Email' => 'missing-name@example.com',
        ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['HoTen']);
        $this->assertSame(0, DB::table('KhachHang')->count());
    }

    public function test_create_form_requires_a_name_and_explains_the_contact_requirement(): void
    {
        $response = $this->withViewErrors([])->withoutMiddleware()->get(route('customers.create'));

        $response->assertOk()
            ->assertSee('name="HoTen"', false)
            ->assertSee('Vui lòng nhập ít nhất một trong hai thông tin: số điện thoại hoặc email.', false)
            ->assertSee('Vui lòng nhập số điện thoại hoặc email.', false)
            ->assertSee('setCustomValidity', false)
            ->assertDontSee('name="Email" required', false)
            ->assertDontSee('name="SoDienThoai" required', false);
    }

    public function test_existing_customer_can_still_be_updated_without_contact_details(): void
    {
        $customerId = DB::table('KhachHang')->insertGetId([
            'HoTen' => 'Khách hiện tại',
        ], 'KhachHangID');

        $response = $this->actingAs($this->makeAuthenticatedUser())->withoutMiddleware([
            RejectCustomerRole::class,
            EnsureUserHasPermission::class,
        ])->from(route('customers.edit', $customerId))
            ->put(route('customers.update', $customerId), [
                'HoTen' => 'Khách đã cập nhật',
            ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('KhachHang', [
            'KhachHangID' => $customerId,
            'HoTen' => 'Khách đã cập nhật',
        ]);
    }

    public function test_customer_cannot_be_updated_with_another_customers_email_or_phone(): void
    {
        $customerIds = DB::table('KhachHang')->insertGetId([
            'HoTen' => 'Khách hiện tại',
            'Email' => 'current@example.com',
            'SoDienThoai' => '0900000001',
        ], 'KhachHangID');
        DB::table('KhachHang')->insert([
            'HoTen' => 'Khách khác',
            'Email' => 'other@example.com',
            'SoDienThoai' => '0900000002',
        ]);

        $response = $this->actingAs($this->makeAuthenticatedUser())->withoutMiddleware([
            RejectCustomerRole::class,
            EnsureUserHasPermission::class,
        ])->from(route('customers.edit', $customerIds))
            ->put(route('customers.update', $customerIds), [
                'HoTen' => 'Khách hiện tại',
                'Email' => 'other@example.com',
                'SoDienThoai' => '0900000002',
            ]);

        $response->assertRedirect(route('customers.edit', $customerIds))
            ->assertSessionHasErrors(['Email', 'SoDienThoai']);
        $this->assertSame(
            'Email này đã được đăng ký trước đó rồi.',
            session('errors')->getBag('default')->first('Email'),
        );
        $this->assertSame(
            'Số điện thoại này đã được đăng ký trước đó rồi.',
            session('errors')->getBag('default')->first('SoDienThoai'),
        );
    }

    public function test_edit_form_shows_the_customer_email(): void
    {
        $customerId = DB::table('KhachHang')->insertGetId([
            'HoTen' => 'Tên khách',
            'Email' => 'customer@example.com',
        ], 'KhachHangID');

        $response = $this->withViewErrors([])
            ->withoutMiddleware()
            ->get(route('customers.edit', $customerId));

        $response->assertOk()
            ->assertSee('value="customer@example.com"', false);
    }

    private function makeAuthenticatedUser(): User
    {
        return (new User)->forceFill([
            'TaiKhoanID' => 1,
            'TrangThai' => 'Hoạt động',
        ]);
    }

    private function submitCustomerCreation(array $attributes): TestResponse
    {
        return $this->actingAs($this->makeAuthenticatedUser())->withoutMiddleware([
            RejectCustomerRole::class,
            EnsureUserHasPermission::class,
        ])->from(route('customers.create'))
            ->post(route('customers.store'), $attributes);
    }
}
