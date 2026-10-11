<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Requests\Admin\LuuThanhToanRequest;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\ThanhToan;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
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
            $table->dateTime('NgayCapNhat')->nullable();
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

        Schema::create('LichSuThayDoiHoaDon', function (Blueprint $table): void {
            $table->increments('LichSuID');
            $table->unsignedInteger('HoaDonID');
            $table->unsignedInteger('TaiKhoanID');
            $table->dateTime('ThoiGian');
            $table->string('TruongThayDoi');
            $table->text('GiaTriCu')->nullable();
            $table->text('GiaTriMoi')->nullable();
        });

        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedInteger('BanGhiID');
            $table->text('DuLieuCu')->nullable();
            $table->text('DuLieuMoi')->nullable();
            $table->text('LyDo')->nullable();
            $table->dateTime('ThoiGian')->nullable();
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach (['NhatKyHeThong', 'LichSuThayDoiHoaDon', 'ThanhToan', 'HoaDon', 'ChiTietDonHang', 'DonHang', 'KhachHang'] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        parent::tearDown();
    }

    public function test_payment_form_receives_the_full_amount_and_rejects_legacy_partial_payment(): void
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
            ->assertSee('value="50000" min="0" step="1000" disabled', false)
            ->assertSee('name="order_id" disabled', false)
            ->assertSee('name="order_id" value="37"', false)
            ->assertSee('name="invoice_id" disabled', false)
            ->assertSee('name="invoice_id" value="19"', false)
            ->assertSee('name="amount" value="50000"', false)
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

    public function test_payment_form_rejects_partial_amount_instead_of_using_a_remaining_balance(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 50,
            'MaDonHang' => 'DH0050',
            'TrangThai' => OrderStatus::Delivered->value,
            'ThanhTien' => 50000,
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
            'Số tiền thanh toán phải bằng toàn bộ số tiền phải trả (50,000 đ).',
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
            'amount' => 50000,
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

    public function test_cancelled_invoice_is_not_offered_on_the_payment_form(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 57,
            'MaDonHang' => 'DH0057',
            'TrangThai' => OrderStatus::Received->value,
            'ThanhTien' => 25000,
        ]);
        DB::table('HoaDon')->insert([
            'HoaDonID' => 24,
            'DonHangID' => 57,
            'MaHoaDon' => 'HD-CANCELLED-57',
            'TrangThai' => InvoiceStatus::Cancelled->value,
            'ThanhTien' => 25000,
        ]);

        $this->withoutMiddleware()
            ->get(route('payments.create'))
            ->assertOk()
            ->assertDontSee('HD-CANCELLED-57');
    }

    public function test_cancelled_invoice_rejects_new_payment_and_preserves_its_status(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 58,
            'MaDonHang' => 'DH0058',
            'TrangThai' => OrderStatus::Received->value,
            'ThanhTien' => 25000,
        ]);
        DB::table('HoaDon')->insert([
            'HoaDonID' => 25,
            'DonHangID' => 58,
            'MaHoaDon' => 'HD0058',
            'TrangThai' => InvoiceStatus::Cancelled->value,
            'ThanhTien' => 25000,
        ]);

        try {
            app(PaymentService::class)->create([
                'invoice_id' => 25,
                'amount' => 25000,
                'method' => 'cash',
                'status' => PaymentStatus::Paid->value,
            ]);
            $this->fail('A cancelled invoice must not accept a payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_id', $exception->errors());
        }

        $this->assertSame(0, DB::table('ThanhToan')->where('DonHangID', 58)->count());
        $this->assertSame(InvoiceStatus::Cancelled->value, DB::table('HoaDon')->where('HoaDonID', 25)->value('TrangThai'));
    }

    public function test_pending_payment_cannot_be_confirmed_for_a_cancelled_invoice(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 59,
            'MaDonHang' => 'DH0059',
            'TrangThai' => OrderStatus::Received->value,
            'ThanhTien' => 25000,
        ]);
        DB::table('HoaDon')->insert([
            'HoaDonID' => 26,
            'DonHangID' => 59,
            'MaHoaDon' => 'HD0059',
            'TrangThai' => InvoiceStatus::Cancelled->value,
            'ThanhTien' => 25000,
        ]);
        $payment = ThanhToan::create([
            'DonHangID' => 59,
            'SoTien' => 25000,
            'TrangThai' => PaymentStatus::Pending->value,
        ]);

        try {
            app(PaymentService::class)->update($payment, [
                'invoice_id' => 26,
                'amount' => 25000,
                'status' => PaymentStatus::Paid->value,
            ]);
            $this->fail('A pending payment must not be confirmed for a cancelled invoice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_id', $exception->errors());
        }

        $this->assertSame(PaymentStatus::Pending->value, $payment->fresh()->TrangThai);
        $this->assertSame(InvoiceStatus::Cancelled->value, DB::table('HoaDon')->where('HoaDonID', 26)->value('TrangThai'));
    }

    public function test_staff_can_advance_a_paid_order_through_the_admin_order_route(): void
    {
        $this->createPaidWashingOrder(60);
        $this->bindOrderServiceForRoute();
        $this->actingAs($this->userWithPermissions(false, ['orders.update_status']));

        $this->patch(route('orders.update-status', 60), [
            'TrangThai' => OrderStatus::Washed->value,
        ])->assertSessionHas('success');

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 60)->value('TrangThai'));
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 60)->value('TrangThai'));
    }

    public function test_staff_can_advance_a_paid_order_from_the_staff_dashboard_route(): void
    {
        $this->createPaidWashingOrder(61);
        $this->actingAs($this->userWithPermissions(false, ['orders.update_status']));

        $this->patchJson(route('staff.dashboard.update-order-status', 61), [
            'status' => OrderStatus::Washed->value,
        ])->assertOk()->assertJsonPath('status', OrderStatus::Washed->value);

        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 61)->value('TrangThai'));
    }

    public function test_owner_can_advance_a_paid_order_without_changing_payment_state(): void
    {
        $this->createPaidWashingOrder(62);
        $this->bindOrderServiceForRoute();
        $this->actingAs($this->userWithPermissions(true, []));

        $this->patch(route('orders.update-status', 62), [
            'TrangThai' => OrderStatus::Washed->value,
        ])->assertSessionHas('success');

        $this->assertSame(OrderStatus::Washed->value, DB::table('DonHang')->where('DonHangID', 62)->value('TrangThai'));
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 62)->value('TrangThai'));
    }

    public function test_payment_does_not_allow_skipping_order_progress_to_delivered(): void
    {
        $this->createPaidWashingOrder(68);
        $this->bindOrderServiceForRoute();
        $this->actingAs($this->userWithPermissions(false, ['orders.update_status']));

        $this->patch(route('orders.update-status', 68), [
            'TrangThai' => OrderStatus::Delivered->value,
        ])->assertSessionHasErrors('TrangThai');

        $this->assertSame(OrderStatus::Washing->value, DB::table('DonHang')->where('DonHangID', 68)->value('TrangThai'));
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 68)->value('TrangThai'));
    }

    public function test_staff_cannot_delete_a_paid_order_through_the_route(): void
    {
        $this->createPaidWashingOrder(63);
        $this->bindOrderServiceForRoute();
        $this->actingAs($this->userWithPermissions(false, ['orders.delete']));

        $this->deleteJson(route('orders.destroy', 63))->assertForbidden();

        $this->assertSame(1, DB::table('DonHang')->where('DonHangID', 63)->count());
        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 63)->count());
    }

    public function test_owner_cannot_delete_a_paid_order_and_erase_its_financial_history(): void
    {
        $this->createPaidWashingOrder(64);
        $this->bindOrderServiceForRoute();
        $this->actingAs($this->userWithPermissions(true, []));

        $this->delete(route('orders.destroy', 64))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('DonHang')->where('DonHangID', 64)->count());
        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 64)->count());
    }

    public function test_staff_cannot_delete_a_successful_payment_through_the_route(): void
    {
        $this->createPaidWashingOrder(65);
        $this->bindPaymentServiceForRoute();
        $this->actingAs($this->userWithPermissions(false, ['payments.delete']));

        $this->deleteJson(route('payments.destroy', 1))->assertForbidden();

        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 65)->count());
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 65)->value('TrangThai'));
    }

    public function test_staff_cannot_change_the_status_of_a_successful_payment_through_the_route(): void
    {
        $this->createPaidWashingOrder(67);
        $this->bindPaymentServiceForRoute();
        $this->actingAs($this->userWithPermissions(false, ['payments.edit']));

        $this->putJson(route('payments.update', 1), [
            'order_id' => 67,
            'amount' => 25000,
            'method' => 'cash',
            'status' => PaymentStatus::Failed->value,
        ])->assertForbidden();

        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 67)->value('TrangThai'));
    }

    public function test_owner_cannot_delete_or_rewrite_a_successful_payment(): void
    {
        $this->createPaidWashingOrder(66);
        $this->bindPaymentServiceForRoute();
        $this->actingAs($this->userWithPermissions(true, []));

        $this->deleteJson(route('payments.destroy', 1))->assertStatus(409);

        $payment = ThanhToan::findOrFail(1);
        try {
            app(PaymentService::class)->update($payment, [
                'amount' => 1,
                'status' => PaymentStatus::Failed->value,
            ], true);
            $this->fail('A successful payment must remain immutable, even for the owner override.');
        } catch (SettledOrderException) {
        }

        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 66)->count());
        $this->assertSame(PaymentStatus::Paid->value, DB::table('ThanhToan')->where('DonHangID', 66)->value('TrangThai'));
        $this->assertSame(25000.0, (float) DB::table('ThanhToan')->where('DonHangID', 66)->value('SoTien'));
    }

    public function test_owner_cannot_cancel_or_delete_an_invoice_backed_by_collected_money(): void
    {
        $this->createPaidWashingOrder(70);
        DB::table('HoaDon')->insert([
            'HoaDonID' => 1,
            'DonHangID' => 70,
            'MaHoaDon' => 'HD0070',
            'TrangThai' => InvoiceStatus::Paid->value,
            'ThanhTien' => 25000,
        ]);
        $invoice = HoaDon::findOrFail(1);
        $invoiceService = app(InvoiceService::class);

        foreach ([
            fn () => $invoiceService->updateStatus($invoice, InvoiceStatus::Cancelled->value),
            fn () => $invoiceService->update($invoice, ['ThanhTien' => 20000, 'TrangThai' => InvoiceStatus::Paid->value]),
            fn () => $invoiceService->delete($invoice),
        ] as $operation) {
            try {
                $operation();
                $this->fail('A paid invoice backed by a collected transaction must not be altered or deleted.');
            } catch (SettledOrderException) {
            }
        }

        $this->assertSame(1, DB::table('HoaDon')->where('HoaDonID', 1)->count());
        $this->assertSame(InvoiceStatus::Paid->value, DB::table('HoaDon')->where('HoaDonID', 1)->value('TrangThai'));
        $this->assertSame(25000.0, (float) DB::table('HoaDon')->where('HoaDonID', 1)->value('ThanhTien'));
        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 70)->count());
    }

    public function test_refunded_payment_cannot_be_deleted_or_reopened_as_paid(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 69,
            'MaDonHang' => 'DH0069',
            'TrangThai' => OrderStatus::Washing->value,
            'ThanhTien' => 25000,
        ]);
        $payment = ThanhToan::create([
            'DonHangID' => 69,
            'SoTien' => 25000,
            'TrangThai' => PaymentStatus::Refunded->value,
        ]);
        $paymentService = app(PaymentService::class);

        try {
            $paymentService->delete($payment, true);
            $this->fail('A refunded transaction must remain in the financial history.');
        } catch (SettledOrderException) {
        }

        try {
            $paymentService->update($payment, ['status' => PaymentStatus::Paid->value], true);
            $this->fail('A refunded transaction must not be reopened as collected revenue.');
        } catch (SettledOrderException) {
        }

        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 69)->count());
        $this->assertSame(PaymentStatus::Refunded->value, DB::table('ThanhToan')->where('DonHangID', 69)->value('TrangThai'));
    }

    public function test_invoice_only_payment_must_equal_the_full_invoice_total(): void
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
            'Số tiền thanh toán phải bằng toàn bộ số tiền phải trả (20,000 đ).',
            $validator->errors()->first('amount'),
        );
    }

    public function test_partial_payment_is_rejected_and_a_full_payment_marks_only_the_invoice_paid(): void
    {
        $this->actingAs((new User)->forceFill(['TaiKhoanID' => 1]));

        DB::table('DonHang')->insert([
            'DonHangID' => 54,
            'MaDonHang' => 'DH0054',
            'TrangThai' => OrderStatus::Washing->value,
            'ThanhTien' => 25000,
        ]);
        DB::table('HoaDon')->insert([
            'HoaDonID' => 21,
            'DonHangID' => 54,
            'MaHoaDon' => 'HD0054',
            'TrangThai' => InvoiceStatus::Unpaid->value,
            'ThanhTien' => 25000,
        ]);

        try {
            app(PaymentService::class)->create([
                'invoice_id' => 21,
                'amount' => 10000,
                'method' => 'cash',
                'status' => PaymentStatus::Paid->value,
            ]);
            $this->fail('A partial payment must not be recorded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame(0, DB::table('ThanhToan')->where('DonHangID', 54)->count());
        $this->assertSame(InvoiceStatus::Unpaid->value, DB::table('HoaDon')->where('HoaDonID', 21)->value('TrangThai'));

        app(PaymentService::class)->create([
            'invoice_id' => 21,
            'amount' => 25000,
            'method' => 'cash',
            'status' => PaymentStatus::Paid->value,
        ]);

        $this->assertSame(1, DB::table('ThanhToan')->where('DonHangID', 54)->count());
        $this->assertSame(InvoiceStatus::Paid->value, DB::table('HoaDon')->where('HoaDonID', 21)->value('TrangThai'));
        $this->assertSame(OrderStatus::Washing->value, DB::table('DonHang')->where('DonHangID', 54)->value('TrangThai'));
    }

    public function test_invoice_cannot_be_marked_paid_without_a_full_successful_transaction(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 55,
            'MaDonHang' => 'DH0055',
            'TrangThai' => OrderStatus::Delivered->value,
            'ThanhTien' => 25000,
        ]);
        DB::table('HoaDon')->insert([
            'HoaDonID' => 22,
            'DonHangID' => 55,
            'MaHoaDon' => 'HD0055',
            'TrangThai' => InvoiceStatus::Unpaid->value,
            'ThanhTien' => 25000,
        ]);

        try {
            app(InvoiceService::class)->updateStatus(
                HoaDon::findOrFail(22),
                InvoiceStatus::Paid->value,
            );
            $this->fail('An invoice cannot be marked paid without a successful payment record.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(InvoiceStatus::Unpaid->value, DB::table('HoaDon')->where('HoaDonID', 22)->value('TrangThai'));
    }

    public function test_invoice_update_failure_rolls_back_the_new_payment_and_paid_status(): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => 56,
            'MaDonHang' => 'DH0056',
            'TrangThai' => OrderStatus::Received->value,
            'ThanhTien' => 25000,
        ]);
        DB::table('HoaDon')->insert([
            'HoaDonID' => 23,
            'DonHangID' => 56,
            'MaHoaDon' => 'HD0056',
            'TrangThai' => InvoiceStatus::Unpaid->value,
            'ThanhTien' => 25000,
        ]);

        try {
            app(PaymentService::class)->create([
                'invoice_id' => 23,
                'amount' => 25000,
                'method' => 'cash',
                'status' => PaymentStatus::Paid->value,
            ]);
            $this->fail('The invoice audit failure should abort the transaction.');
        } catch (\LogicException) {
        }

        $this->assertSame(0, DB::table('ThanhToan')->where('DonHangID', 56)->count());
        $this->assertSame(InvoiceStatus::Unpaid->value, DB::table('HoaDon')->where('HoaDonID', 23)->value('TrangThai'));
        $this->assertSame(OrderStatus::Received->value, DB::table('DonHang')->where('DonHangID', 56)->value('TrangThai'));
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
        app(PaymentService::class)->create(['order_id' => 1, 'amount' => 10000, 'method' => 'cash']);
    }

    public function test_confirmation_validates_current_balance_after_another_payment_was_collected(): void
    {
        DB::table('DonHang')->insert(['DonHangID' => 1, 'MaDonHang' => 'TEST', 'TrangThai' => OrderStatus::Received->value, 'ThanhTien' => 10000]);
        $pending = ThanhToan::create(['DonHangID' => 1, 'SoTien' => 10000, 'TrangThai' => PaymentStatus::Pending->value]);
        DB::table('ThanhToan')->insert(['DonHangID' => 1, 'SoTien' => 6000, 'TrangThai' => PaymentStatus::Paid->value]);
        try {
            app(PaymentService::class)->update($pending, ['status' => PaymentStatus::Paid->value]);
            $this->fail('Stale payment amount must not be confirmed.');
        } catch (SettledOrderException) {
        }
        $this->assertSame(PaymentStatus::Pending->value, $pending->fresh()->TrangThai);
    }

    private function createPaidWashingOrder(int $orderId): void
    {
        DB::table('DonHang')->insert([
            'DonHangID' => $orderId,
            'MaDonHang' => 'DH'.str_pad((string) $orderId, 4, '0', STR_PAD_LEFT),
            'TrangThai' => OrderStatus::Washing->value,
            'ThanhTien' => 25000,
        ]);
        DB::table('ThanhToan')->insert([
            'ThanhToanID' => 1,
            'DonHangID' => $orderId,
            'SoTien' => 25000,
            'TrangThai' => PaymentStatus::Paid->value,
        ]);
    }

    private function bindOrderServiceForRoute(): void
    {
        $service = \Mockery::mock(OrderService::class)->makePartial();
        $service->shouldReceive('find')
            ->andReturnUsing(fn (int $id): ?DonHang => DonHang::find($id));
        $this->app->instance(OrderService::class, $service);
    }

    private function bindPaymentServiceForRoute(): void
    {
        $service = \Mockery::mock(PaymentService::class)->makePartial();
        $service->shouldReceive('find')
            ->andReturnUsing(fn (int $id): ?ThanhToan => ThanhToan::find($id));
        $this->app->instance(PaymentService::class, $service);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(bool $isOwner, array $permissions): User&MockInterface
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isActive')->andReturn(true);
        $user->shouldReceive('isCustomer')->andReturn(false);
        $user->shouldReceive('isOwner')->andReturn($isOwner);
        $user->shouldReceive('getKey')->andReturn(1);
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);
        $user->shouldReceive('canPermission')
            ->andReturnUsing(fn (string $permission): bool => $isOwner || in_array($permission, $permissions, true));

        return $user;
    }
}
