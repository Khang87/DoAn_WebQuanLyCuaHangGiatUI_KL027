<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\Admin\LuuThanhToanRequest;
use App\Models\ThanhToan;
use App\Services\PaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentOrderRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            $this->fail('Payment order redirect tests require isolated SQLite in-memory storage.');
        }

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
        });

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai');
            $table->dateTime('NgayTao')->nullable();
            $table->decimal('ThanhTien', 12, 2)->default(0);
        });

        Schema::create('ChiTietDonHang', function (Blueprint $table): void {
            $table->increments('ChiTietDonHangID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('DichVuID')->nullable();
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->string('MaHoaDon')->nullable();
            $table->string('TrangThai')->nullable();
            $table->dateTime('NgayLap')->nullable();
            $table->decimal('ThanhTien', 12, 2)->nullable();
        });

        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->decimal('SoTien', 12, 2)->default(0);
            $table->string('PhuongThuc')->nullable();
            $table->string('MaGiaoDich')->nullable();
            $table->dateTime('ThoiGian')->nullable();
            $table->string('TrangThai')->nullable();
            $table->string('GhiChu')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach (['ThanhToan', 'HoaDon', 'ChiTietDonHang', 'DonHang', 'KhachHang'] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        parent::tearDown();
    }

    public function test_payment_form_receives_the_selected_order_and_remaining_amount(): void
    {
        DB::table('KhachHang')->insert([
            'KhachHangID' => 3,
            'HoTen' => 'Khách thử nghiệm',
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 37,
            'MaDonHang' => 'DH0037',
            'KhachHangID' => 3,
            'TrangThai' => OrderStatus::Delivered->value,
            'ThanhTien' => 50000,
        ]);
        DB::table('ThanhToan')->insert([
            'DonHangID' => 37,
            'TrangThai' => PaymentStatus::Paid->value,
            'SoTien' => 10000,
        ]);
        DB::table('HoaDon')->insert([
            'HoaDonID' => 19,
            'DonHangID' => 37,
            'MaHoaDon' => 'HD0037',
            'TrangThai' => 'Chưa thanh toán',
            'ThanhTien' => 50000,
        ]);

        $response = $this->withoutMiddleware()->get(route('payments.create', ['order_id' => 37]));

        $response->assertOk()
            ->assertSee('DH0037')
            ->assertSee('Khách thử nghiệm')
            ->assertSee('Số tiền còn phải thu: 40,000 đ')
            ->assertSee('name="amount" value="40000"', false)
            ->assertSee('name="order_id" disabled', false)
            ->assertSee('name="order_id" value="37"', false)
            ->assertSee('name="invoice_id" disabled', false)
            ->assertSee('name="invoice_id" value="19"', false)
            ->assertSee('value="40000" min="0" step="1000" disabled', false)
            ->assertSee('name="amount" value="40000"', false)
            ->assertSee('name="method" required', false)
            ->assertSee('name="status" value="Thành công"', false)
            ->assertSee('id="transaction_code"', false)
            ->assertSee('maxlength="100" readonly', false)
            ->assertSee('label class="form-label">Trạng thái</label>', false)
            ->assertDontSee('Trạng thái <span class="text-danger', false)
            ->assertSee('type="datetime-local" class="form-control" value="', false)
            ->assertSee('type="datetime-local" class="form-control" value="'.now()->format('Y-m-d\TH:i').'" disabled', false)
            ->assertDontSee('Số tiền thanh toán <span class="text-danger', false);
    }

    public function test_invoice_field_is_hidden_when_the_selected_order_has_no_invoice(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 38,
            'MaDonHang' => 'DH0038',
            'TrangThai' => OrderStatus::Delivered->value,
            'ThanhTien' => 25000,
        ]);

        $response = $this->withoutMiddleware()->get(route('payments.create', ['order_id' => 38]));

        $response->assertOk()
            ->assertSee('DH0038')
            ->assertDontSee('<select class="form-select" name="invoice_id"', false)
            ->assertDontSee('Đơn hàng chưa có hóa đơn');
    }

    public function test_invalid_order_id_redirects_to_orders_with_an_error_message(): void
    {
        $response = $this->withoutMiddleware()->get(route('payments.create', ['order_id' => 999]));

        $response->assertRedirect(route('orders.index'))
            ->assertSessionHas('error', 'Không tìm thấy đơn hàng cần thanh toán.');
    }

    public function test_paid_or_cancelled_orders_cannot_be_opened_for_payment(): void
    {
        DB::table('DonHang')->insert([
            [
                'DonHangID' => 1,
                'MaDonHang' => 'DH0001',
                'TrangThai' => OrderStatus::Paid->value,
                'ThanhTien' => 10000,
            ],
            [
                'DonHangID' => 2,
                'MaDonHang' => 'DH0002',
                'TrangThai' => OrderStatus::Cancelled->value,
                'ThanhTien' => 10000,
            ],
        ]);

        $paidResponse = $this->withoutMiddleware()->get(route('payments.create', ['order_id' => 1]));
        $paidResponse->assertRedirect(route('orders.show', 1))
            ->assertSessionHas('error', 'Đơn hàng này đã được thanh toán.');

        $cancelledResponse = $this->withoutMiddleware()->get(route('payments.create', ['order_id' => 2]));
        $cancelledResponse->assertRedirect(route('orders.index'))
            ->assertSessionHas('error', 'Không thể thanh toán đơn hàng đã hủy.');
    }

    public function test_payment_creation_route_requires_the_payment_permission(): void
    {
        $middleware = $this->app['router']->getRoutes()
            ->getByName('payments.create')
            ->gatherMiddleware();

        $this->assertContains('permission:payments.create', $middleware);
    }

    public function test_payment_amount_cannot_exceed_the_remaining_order_balance(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 50,
            'MaDonHang' => 'DH0050',
            'TrangThai' => OrderStatus::Delivered->value,
            'ThanhTien' => 50000,
        ]);
        DB::table('ThanhToan')->insert([
            'DonHangID' => 50,
            'TrangThai' => PaymentStatus::Paid->value,
            'SoTien' => 20000,
        ]);

        $request = new LuuThanhToanRequest;
        $request->replace([
            'order_id' => 50,
            'amount' => 30001,
        ]);
        $validator = validator($request->all(), $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Số tiền thanh toán không được vượt quá số tiền còn phải thu (30,000 đ).',
            $validator->errors()->first('amount'),
        );
    }

    public function test_transaction_code_must_be_unique_when_provided(): void
    {
        DB::table('ThanhToan')->insert([
            'MaGiaoDich' => 'TM_DH0050_20261004090000',
            'TrangThai' => PaymentStatus::Paid->value,
            'SoTien' => 100,
        ]);

        $request = new LuuThanhToanRequest;
        $request->replace([
            'transaction_code' => 'TM_DH0050_20261004090000',
        ]);
        $validator = validator(
            $request->all(),
            ['transaction_code' => $request->rules()['transaction_code']],
            $request->messages(),
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Mã giao dịch đã được sử dụng.',
            $validator->errors()->first('transaction_code'),
        );
    }

    public function test_transaction_code_can_be_kept_when_updating_its_own_payment(): void
    {
        DB::table('ThanhToan')->insert([
            'ThanhToanID' => 55,
            'MaGiaoDich' => 'TM_DH0055_20261004090000',
            'TrangThai' => PaymentStatus::Paid->value,
            'SoTien' => 100,
        ]);

        $request = new LuuThanhToanRequest;
        $request->replace([
            'transaction_code' => 'TM_DH0055_20261004090000',
        ]);
        $request->setRouteResolver(fn () => new class
        {
            public function parameter(string $key): ?string
            {
                return $key === 'payment' ? '55' : null;
            }
        });
        $validator = validator(
            $request->all(),
            ['transaction_code' => $request->rules()['transaction_code']],
            $request->messages(),
        );

        $this->assertFalse($validator->fails());
    }

    public function test_server_generates_a_unique_cash_transaction_code_when_code_is_blank(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 51,
            'MaDonHang' => 'DH0051',
            'TrangThai' => OrderStatus::Delivered->value,
            'ThanhTien' => 50000,
        ]);

        $payment = app(PaymentService::class)->create([
            'order_id' => 51,
            'amount' => 10000,
            'method' => 'cash',
            'status' => PaymentStatus::Pending->value,
        ]);

        $this->assertMatchesRegularExpression(
            '/^TM_DH0051_\d{14}(?:_\d+)?$/',
            $payment->MaGiaoDich,
        );
        $this->assertSame(1, DB::table('ThanhToan')->where('MaGiaoDich', $payment->MaGiaoDich)->count());
    }

    public function test_payment_for_received_order_is_saved_without_skipping_order_lifecycle(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 52,
            'MaDonHang' => 'DH0052',
            'TrangThai' => OrderStatus::Received->value,
            'ThanhTien' => 25000,
        ]);

        $payment = app(PaymentService::class)->create([
            'order_id' => 52,
            'amount' => 25000,
            'method' => 'cash',
            'status' => PaymentStatus::Paid->value,
        ]);

        $this->assertSame(52, $payment->DonHangID);
        $this->assertSame(OrderStatus::Received->value, DB::table('DonHang')->where('DonHangID', 52)->value('TrangThai'));
        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 52)->count());
    }

    public function test_invoice_only_payment_cannot_exceed_its_remaining_balance(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 53,
            'MaDonHang' => 'DH0053',
            'TrangThai' => OrderStatus::Delivered->value,
            'ThanhTien' => 25000,
        ]);
        DB::table('HoaDon')->insert([
            'HoaDonID' => 20,
            'DonHangID' => 53,
            'MaHoaDon' => 'HD0053',
            'TrangThai' => 'Chưa thanh toán',
            'ThanhTien' => 20000,
        ]);

        $request = new LuuThanhToanRequest;
        $request->replace([
            'invoice_id' => 20,
            'amount' => 20001,
        ]);
        $validator = validator($request->all(), $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Số tiền thanh toán không được vượt quá số tiền còn phải thu (20,000 đ).',
            $validator->errors()->first('amount'),
        );
    }

    public function test_service_rejects_overpayment_and_leaves_existing_collected_payments_unchanged(): void
    {
        DB::table('DonHang')->insert(['DonHangID' => 1, 'MaDonHang' => 'TEST', 'TrangThai' => OrderStatus::Received->value, 'ThanhTien' => 10000]);
        DB::table('ThanhToan')->insert(['DonHangID' => 1, 'SoTien' => 6000, 'TrangThai' => PaymentStatus::Paid->value]);
        try {
            app(PaymentService::class)->create(['order_id' => 1, 'amount' => 5000, 'method' => 'cash']);
            $this->fail('Overpayment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertSame(1, DB::table('ThanhToan')->count());
        $this->assertEquals(6000, DB::table('ThanhToan')->sum('SoTien'));
    }

    public function test_service_cannot_collect_money_for_a_canceled_order(): void
    {
        DB::table('DonHang')->insert(['DonHangID' => 1, 'MaDonHang' => 'TEST', 'TrangThai' => OrderStatus::Cancelled->value, 'ThanhTien' => 10000]);
        $this->expectException(ValidationException::class);
        app(PaymentService::class)->create(['order_id' => 1, 'amount' => 5000, 'method' => 'cash']);
    }

    public function test_confirmation_validates_current_balance_after_another_payment_was_collected(): void
    {
        DB::table('DonHang')->insert(['DonHangID' => 1, 'MaDonHang' => 'TEST', 'TrangThai' => OrderStatus::Received->value, 'ThanhTien' => 10000]);
        $pending = ThanhToan::create(['DonHangID' => 1, 'SoTien' => 10000, 'TrangThai' => PaymentStatus::Pending->value]);
        DB::table('ThanhToan')->insert(['DonHangID' => 1, 'SoTien' => 6000, 'TrangThai' => PaymentStatus::Paid->value]);
        try {
            app(PaymentService::class)->update($pending, ['status' => PaymentStatus::Paid->value]);
            $this->fail('Stale payment amount must not be confirmed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertSame(PaymentStatus::Pending->value, $pending->fresh()->TrangThai);
    }
}
