<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\ThanhToan;
use App\Models\User;
use App\Models\VaiTro;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BeePayWebhookTest extends TestCase
{
    private User $actor;

    private DonHang $order;

    private HoaDon $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('TaiKhoan', function (Blueprint $t) {
            $t->increments('TaiKhoanID');
            $t->string('TenDangNhap');
            $t->string('MatKhau')->nullable();
            $t->integer('NhanVienID')->nullable();
            $t->integer('KhachHangID')->nullable();
            $t->string('TrangThai');
        });
        Schema::create('VaiTro', function (Blueprint $t) {
            $t->increments('VaiTroID');
            $t->string('TenVaiTro');
            $t->string('TrangThai');
        });
        Schema::create('TaiKhoan_VaiTro', function (Blueprint $t) {
            $t->integer('TaiKhoanID');
            $t->integer('VaiTroID');
        });
        Schema::create('Quyen', function (Blueprint $t) {
            $t->increments('QuyenID');
            $t->string('MaQuyen');
            $t->string('TrangThai');
        });
        Schema::create('VaiTro_Quyen', function (Blueprint $t) {
            $t->integer('VaiTroID');
            $t->integer('QuyenID');
        });
        Schema::create('DonHang', function (Blueprint $t) {
            $t->increments('DonHangID');
            $t->string('MaDonHang');
            $t->string('TrangThai');
            $t->integer('BookingID')->nullable();
            $t->decimal('ThanhTien', 12, 2);
            $t->dateTime('NgayCapNhat')->nullable();
        });
        Schema::create('HoaDon', function (Blueprint $t) {
            $t->increments('HoaDonID');
            $t->integer('DonHangID')->unique();
            $t->string('MaHoaDon');
            $t->string('TrangThai');
            $t->decimal('ThanhTien', 12, 2);
            $t->dateTime('NgayLap')->nullable();
        });
        Schema::create('ThanhToan', function (Blueprint $t) {
            $t->increments('ThanhToanID');
            $t->integer('DonHangID');
            $t->decimal('SoTien', 12, 2);
            $t->string('MaGiaoDich', 100);
            $t->string('PhuongThuc');
            $t->string('TrangThai');
            $t->dateTime('ThoiGian');
            $t->string('GhiChu')->nullable();
        });
        Schema::create('LichSuThayDoiHoaDon', function (Blueprint $t) {
            $t->increments('LichSuID');
            $t->integer('HoaDonID');
            $t->integer('TaiKhoanID');
            $t->dateTime('ThoiGian');
            $t->string('TruongThayDoi');
            $t->text('GiaTriCu')->nullable();
            $t->text('GiaTriMoi')->nullable();
        });
        Schema::create('NhatKyHeThong', function (Blueprint $t) {
            $t->increments('NhatKyID');
            $t->integer('TaiKhoanID')->nullable();
            $t->string('HanhDong');
            $t->string('BangDuLieu');
            $t->integer('BanGhiID');
            $t->text('DuLieuCu')->nullable();
            $t->text('DuLieuMoi')->nullable();
            $t->dateTime('ThoiGian');
            $t->string('IPAddress')->nullable();
            $t->string('UserAgent')->nullable();
        });
        $role = VaiTro::create(['TenVaiTro' => VaiTro::OWNER, 'TrangThai' => 'Hoạt động']);
        $this->actor = User::create(['TenDangNhap' => 'beepay-audit', 'NhanVienID' => 1, 'TrangThai' => 'Hoạt động']);
        $this->actor->vaiTros()->attach($role);
        config(['services.beepay.webhook_secret' => 'fixture-secret', 'services.beepay.audit_account_id' => $this->actor->getKey(), 'services.vietqr.account_number' => '123456789']);
        $this->order = DonHang::create(['MaDonHang' => 'DH0001', 'TrangThai' => OrderStatus::Received->value, 'ThanhTien' => 50000]);
        $this->invoice = HoaDon::create(['DonHangID' => $this->order->getKey(), 'MaHoaDon' => 'HD1', 'TrangThai' => InvoiceStatus::Unpaid->value, 'ThanhTien' => 50000]);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['order_id' => 'DH00000001', 'transaction_type' => 'credit', 'transaction_ref' => 'BANK-TX-1', 'amount' => 50000, 'account_number' => '123456789'], $changes);
    }

    private function webhook(array $payload, bool $validSignature = true)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/api/v1/webhooks/beepay', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_SIGNATURE' => $validSignature ? hash_hmac('sha256', $body, 'fixture-secret') : 'invalid'], content: $body);
    }

    public function test_signed_credit_settles_direct_web_order_and_attributes_invoice_without_login(): void
    {
        $this->webhook($this->payload())->assertOk()->assertJson(['ok' => true]);
        $this->assertGuest();
        $this->assertSame(InvoiceStatus::Paid->value, $this->invoice->fresh()->TrangThai);
        $this->assertSame(OrderStatus::Received->value, $this->order->fresh()->TrangThai);
        $this->assertDatabaseHas('LichSuThayDoiHoaDon', ['HoaDonID' => $this->invoice->getKey(), 'TaiKhoanID' => $this->actor->getKey(), 'GiaTriMoi' => InvoiceStatus::Paid->value]);
        $this->assertDatabaseHas('ThanhToan', ['MaGiaoDich' => 'BANK-TX-1', 'SoTien' => 50000, 'TrangThai' => PaymentStatus::Paid->value]);
    }

    public function test_exact_retry_is_idempotent_but_a_second_transfer_is_not_silently_accepted(): void
    {
        $this->webhook($this->payload())->assertOk();
        $this->webhook($this->payload())->assertOk();
        $this->webhook($this->payload(['transaction_ref' => 'BANK-TX-2']))->assertStatus(422);
        $this->assertSame(1, ThanhToan::count());
        $this->assertSame(1, DB::table('LichSuThayDoiHoaDon')->count());
    }

    public function test_pending_record_with_same_reference_is_confirmed_instead_of_ignored(): void
    {
        ThanhToan::create(['DonHangID' => $this->order->getKey(), 'MaGiaoDich' => 'BANK-TX-1', 'SoTien' => 50000, 'PhuongThuc' => 'Chuyển khoản', 'TrangThai' => PaymentStatus::Pending->value, 'ThoiGian' => now()]);
        $this->webhook($this->payload())->assertOk();
        $this->assertSame(1, ThanhToan::count());
        $this->assertSame(PaymentStatus::Paid->value, ThanhToan::first()->TrangThai);
        $this->assertSame(InvoiceStatus::Paid->value, $this->invoice->fresh()->TrangThai);
    }

    public function test_signature_account_amount_length_and_cancelled_guards_do_not_write_money(): void
    {
        $this->webhook($this->payload(), false)->assertStatus(401);
        $this->webhook($this->payload(['account_number' => '987654321']))->assertStatus(422);
        $this->webhook($this->payload(['amount' => 49999]))->assertStatus(422);
        $this->webhook($this->payload(['transaction_ref' => str_repeat('x', 101)]))->assertStatus(422);
        $this->webhook($this->payload(['order_id' => 'DH9999999999']))->assertStatus(422);
        $this->webhook($this->payload(['order_id' => 'DH00000099']))->assertStatus(422);
        $this->order->updateQuietly(['TrangThai' => OrderStatus::Cancelled->value]);
        $this->webhook($this->payload())->assertStatus(422);
        $this->assertSame(0, ThanhToan::count());
        $this->assertSame(0, DB::table('LichSuThayDoiHoaDon')->count());
    }

    public function test_missing_or_disabled_audit_account_blocks_collection(): void
    {
        foreach ([null, '', 'invalid', 0, 2147483648] as $id) {
            config(['services.beepay.audit_account_id' => $id]);
            $this->webhook($this->payload())->assertStatus(503);
        }
        config(['services.beepay.audit_account_id' => $this->actor->getKey()]);
        $this->actor->update(['TrangThai' => 'Khóa']);
        $this->webhook($this->payload())->assertStatus(503);
        $this->assertSame(0, ThanhToan::count());
    }

    public function test_same_bank_reference_cannot_settle_another_order(): void
    {
        $this->webhook($this->payload())->assertOk();
        $other = DonHang::create(['MaDonHang' => 'DH0002', 'TrangThai' => OrderStatus::Received->value, 'ThanhTien' => 50000]);
        HoaDon::create(['DonHangID' => $other->getKey(), 'MaHoaDon' => 'HD2', 'TrangThai' => InvoiceStatus::Unpaid->value, 'ThanhTien' => 50000]);
        $this->webhook($this->payload(['order_id' => 'DH00000002']))->assertStatus(422);
        $this->assertSame(1, ThanhToan::count());
    }
}
