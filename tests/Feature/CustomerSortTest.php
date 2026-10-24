<?php

namespace Tests\Feature;

use App\Services\CustomerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerSortTest extends TestCase
{
    private bool $testSchemaCreated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->fail('Customer sort tests require isolated SQLite in-memory storage.');
        }

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
            $table->string('DiaChi')->nullable();
            $table->string('AvatarUrl')->nullable();
            $table->dateTime('NgayTao')->nullable();
            $table->string('TrangThai')->nullable();
        });
        $this->testSchemaCreated = true;

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->unsignedInteger('KhachHangID')->unique();
            $table->string('AvatarURL')->nullable();
            $table->string('MatKhau'); // Test-only column to detect accidental password loading.
        });

        Schema::create('DiemTichLuy', function (Blueprint $table): void {
            $table->increments('DiemTichLuyID');
            $table->unsignedInteger('KhachHangID')->unique();
            $table->integer('DiemHienTai')->default(0);
            $table->dateTime('NgayCapNhat')->nullable();
        });

        DB::table('KhachHang')->insert([
            ['KhachHangID' => 1, 'HoTen' => 'Không có điểm'],
            ['KhachHangID' => 2, 'HoTen' => 'Có điểm cao'],
            ['KhachHangID' => 3, 'HoTen' => 'Điểm bằng 0'],
            ['KhachHangID' => 4, 'HoTen' => 'Có điểm thấp'],
        ]);

        DB::table('TaiKhoan')->insert([
            [
                'TaiKhoanID' => 11,
                'KhachHangID' => 1,
                'AvatarURL' => 'https://example.test/account.jpg',
                'MatKhau' => 'dummy-test-password-1',
            ],
            [
                'TaiKhoanID' => 12,
                'KhachHangID' => 2,
                'AvatarURL' => 'https://example.test/account.jpg',
                'MatKhau' => 'dummy-test-password-2',
            ],
        ]);

        DB::table('DiemTichLuy')->insert([
            ['DiemTichLuyID' => 1, 'KhachHangID' => 2, 'DiemHienTai' => 40],
            ['DiemTichLuyID' => 2, 'KhachHangID' => 3, 'DiemHienTai' => 0],
            ['DiemTichLuyID' => 3, 'KhachHangID' => 4, 'DiemHienTai' => 10],
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->testSchemaCreated) {
            Schema::dropIfExists('TaiKhoan');
            Schema::dropIfExists('DiemTichLuy');
            Schema::dropIfExists('KhachHang');
        }

        parent::tearDown();
    }

    public function test_points_sort_orders_customers_with_missing_points_as_zero(): void
    {
        $customers = app(CustomerService::class)->getAll(['sort' => 'points_desc']);

        $this->assertSame([2, 4, 1, 3], $customers->getCollection()->pluck('KhachHangID')->all());
        $this->assertSame(0, (int) ($customers->getCollection()->firstWhere('KhachHangID', 1)->diemTichLuy?->DiemHienTai ?? 0));
    }

    public function test_points_ascending_sort_is_stable_for_equal_point_totals(): void
    {
        $customers = app(CustomerService::class)->getAll(['sort' => 'points_asc']);

        $this->assertSame([1, 3, 4, 2], $customers->getCollection()->pluck('KhachHangID')->all());
    }

    public function test_customers_can_be_sorted_by_id_in_ascending_order(): void
    {
        DB::table('KhachHang')->where('KhachHangID', 1)->update(['NgayTao' => '2026-01-01']);
        DB::table('KhachHang')->where('KhachHangID', 4)->update(['NgayTao' => '2026-12-01']);

        $customers = app(CustomerService::class)->getAll(['sort' => 'id_asc']);

        $this->assertSame([1, 2, 3, 4], $customers->getCollection()->pluck('KhachHangID')->all());
    }

    public function test_get_all_eager_loads_accounts_without_loading_passwords(): void
    {
        $customers = app(CustomerService::class)->getAll(['sort' => 'id_asc'])->getCollection();
        $accountIds = [1 => 11, 2 => 12];

        $this->assertSame([1, 2, 3, 4], $customers->pluck('KhachHangID')->all());

        foreach ($customers as $customer) {
            $this->assertTrue($customer->relationLoaded('diemTichLuy'));
            $this->assertTrue($customer->relationLoaded('taiKhoan'));

            $account = $customer->taiKhoan;

            if (! isset($accountIds[$customer->KhachHangID])) {
                $this->assertNull($account);

                continue;
            }

            $this->assertNotNull($account);
            $this->assertSame($accountIds[$customer->KhachHangID], $account->TaiKhoanID);
            $this->assertSame($customer->KhachHangID, $account->KhachHangID);
            $this->assertSame('https://example.test/account.jpg', $account->AvatarURL);
            $this->assertArrayNotHasKey('MatKhau', $account->getAttributes());
        }
    }

    public function test_reading_all_customer_avatars_does_not_query_after_get_all(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $customers = app(CustomerService::class)->getAll(['sort' => 'id_asc'])->getCollection();
            $queryCount = count(DB::getQueryLog());

            $this->assertCount(4, $customers);

            foreach ($customers as $customer) {
                $expectedAvatar = in_array($customer->KhachHangID, [1, 2], true)
                    ? 'https://example.test/account.jpg'
                    : asset('assets/images/user_1.jpg');

                $this->assertSame($expectedAvatar, $customer->avatar_url);
            }

            $this->assertCount($queryCount, DB::getQueryLog(), 'Reading avatars must not trigger lazy-loading queries.');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_get_all_query_count_does_not_grow_with_customer_count(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $service = app(CustomerService::class);
            $singleCustomer = $service->getAll(['search' => '1', 'sort' => 'id_asc']);
            $singleCustomerQueryCount = count(DB::getQueryLog());

            $this->assertCount(1, $singleCustomer->getCollection());

            DB::flushQueryLog();

            $multipleCustomers = $service->getAll(['sort' => 'id_asc']);
            $multipleCustomerQueryCount = count(DB::getQueryLog());

            $this->assertCount(4, $multipleCustomers->getCollection());
            $this->assertSame(4, $singleCustomerQueryCount, 'Expected pagination count, KhachHang, DiemTichLuy and TaiKhoan queries.');
            $this->assertSame($singleCustomerQueryCount, $multipleCustomerQueryCount, 'Loading more customers must not add queries.');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
