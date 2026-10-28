<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\RejectCustomerRole;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_customer_creation_requires_both_contact_methods(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => 'Khách thiếu liên hệ',
        ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['Email', 'SoDienThoai']);
        $this->assertSame(
            'Vui lòng nhập địa chỉ email.',
            session('errors')->getBag('default')->first('Email'),
        );
        $this->assertSame(
            'Vui lòng nhập số điện thoại.',
            session('errors')->getBag('default')->first('SoDienThoai'),
        );
        $this->assertSame(0, DB::table('KhachHang')->count());
    }

    public function test_customer_creation_rejects_a_missing_email(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => 'Khách thiếu email',
            'SoDienThoai' => '0901234567',
        ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['Email']);
        $this->assertSame(0, DB::table('KhachHang')->count());
    }

    public function test_customer_creation_rejects_a_missing_phone(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => 'Khách thiếu số điện thoại',
            'Email' => 'missing-phone@example.com',
        ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['SoDienThoai']);
        $this->assertSame(0, DB::table('KhachHang')->count());
    }

    public function test_customer_creation_rejects_an_invalid_email_and_phone(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => 'Khách liên hệ sai',
            'Email' => 'not-an-email',
            'SoDienThoai' => '123',
        ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['Email', 'SoDienThoai']);
        $this->assertSame('Email không đúng định dạng.', session('errors')->getBag('default')->first('Email'));
        $this->assertSame('Số điện thoại không hợp lệ.', session('errors')->getBag('default')->first('SoDienThoai'));
    }

    public function test_customer_creation_preserves_submitted_values_after_validation_errors(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => 'Khách đang nhập',
            'Email' => 'invalid-email',
            'SoDienThoai' => '0901234567',
        ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasInput('HoTen', 'Khách đang nhập')
            ->assertSessionHasInput('Email', 'invalid-email')
            ->assertSessionHasInput('SoDienThoai', '0901234567');
    }

    public function test_customer_name_containing_only_whitespace_is_rejected(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => '   ',
            'Email' => 'name-required@example.com',
            'SoDienThoai' => '0901234567',
        ]);

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['HoTen']);
        $this->assertSame('Vui lòng nhập họ tên khách hàng.', session('errors')->getBag('default')->first('HoTen'));
    }

    public function test_customer_can_be_created_with_both_contact_methods(): void
    {
        $response = $this->submitCustomerCreation([
            'HoTen' => ' Khách có đủ liên hệ ',
            'Email' => ' both@example.com ',
            'SoDienThoai' => ' 0901234568 ',
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

    public function test_create_form_marks_all_required_fields_and_shows_validation_errors(): void
    {
        $response = $this->withViewErrors([])->withoutMiddleware()->get(route('customers.create'));

        $response->assertOk()
            ->assertSee('name="HoTen"', false)
            ->assertSee('name="Email"', false)
            ->assertSee('name="SoDienThoai"', false)
            ->assertSee('name="Email" value="" placeholder="Nhập email" required', false)
            ->assertSee('name="SoDienThoai" value="" placeholder="Nhập số điện thoại" pattern="0[0-9]{9,10}" maxlength="30" required', false)
            ->assertDontSee('setCustomValidity', false);
    }

    public static function invalidUpdateEmails(): array
    {
        return [
            'missing' => [[], 'Vui lòng nhập địa chỉ email.'],
            'null' => [['Email' => null], 'Vui lòng nhập địa chỉ email.'],
            'empty' => [['Email' => ''], 'Vui lòng nhập địa chỉ email.'],
            'whitespace' => [['Email' => '   '], 'Vui lòng nhập địa chỉ email.'],
            'invalid format' => [['Email' => 'invalid-email'], 'Email không đúng định dạng.'],
        ];
    }

    #[DataProvider('invalidUpdateEmails')]
    public function test_customer_update_requires_a_valid_email(array $contact, string $message): void
    {
        $customerId = DB::table('KhachHang')->insertGetId([
            'HoTen' => 'Khách hiện tại',
            'Email' => 'current@example.com',
        ], 'KhachHangID');

        $response = $this->actingAs($this->makeAuthenticatedUser())->withoutMiddleware([
            RejectCustomerRole::class,
            EnsureUserHasPermission::class,
        ])->from(route('customers.edit', $customerId))
            ->put(route('customers.update', $customerId), ['HoTen' => 'Khách đã cập nhật', ...$contact]);

        $response->assertRedirect(route('customers.edit', $customerId))
            ->assertSessionHasErrors(['Email' => $message])
            ->assertSessionDoesntHaveErrors(['SoDienThoai'])
            ->assertSessionHasInput('HoTen', 'Khách đã cập nhật');
        $this->assertDatabaseHas('KhachHang', [
            'KhachHangID' => $customerId,
            'HoTen' => 'Khách hiện tại',
            'Email' => 'current@example.com',
        ]);
    }

    public function test_existing_customer_can_be_updated_with_email_without_a_phone(): void
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
                'Email' => ' updated@example.com ',
            ]);

        $response->assertRedirect(route('customers.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('KhachHang', [
            'KhachHangID' => $customerId,
            'HoTen' => 'Khách đã cập nhật',
            'Email' => 'updated@example.com',
            'SoDienThoai' => null,
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
            ->assertSee('Email <span class="text-danger ms-1">*</span>', false)
            ->assertSee('name="Email" value="customer@example.com" required', false)
            ->assertSee('name="SoDienThoai" value=""', false)
            ->assertSee('Quay lại')
            ->assertSee('Cập nhật')
            ->assertDontSee('type="reset"', false)
            ->assertDontSee('Làm mới');
    }

    public function test_edit_form_preserves_invalid_email_and_displays_its_error(): void
    {
        $customerId = DB::table('KhachHang')->insertGetId([
            'HoTen' => 'Tên khách',
            'Email' => 'customer@example.com',
        ], 'KhachHangID');

        $this->withViewErrors(['Email' => 'Email không đúng định dạng.'])
            ->withSession(['_old_input' => ['Email' => 'invalid-email']])
            ->withoutMiddleware([
                Authenticate::class,
                RejectCustomerRole::class,
                EnsureUserHasPermission::class,
                ShareErrorsFromSession::class,
            ])->get(route('customers.edit', $customerId))
            ->assertOk()
            ->assertSee('name="Email" value="invalid-email" required', false)
            ->assertSee('Email không đúng định dạng.')
            ->assertSee('class="form-control  is-invalid " name="Email"', false);
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
