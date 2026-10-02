<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\SystemLogController;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\NhatKyHeThong;
use App\Models\TinNhan;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminCommunicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->string('TenDangNhap');
            $table->string('MatKhau')->nullable();
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen');
        });

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang');
            $table->unsignedInteger('KhachHangID');
            $table->string('TrangThai');
            $table->decimal('TongTien', 18, 2)->default(0);
            $table->dateTime('NgayTao')->useCurrent();
        });

        Schema::create('TinNhan', function (Blueprint $table): void {
            $table->increments('TinNhanID');
            $table->unsignedInteger('NguoiGuiID');
            $table->unsignedInteger('NguoiNhanID');
            $table->unsignedInteger('DonHangID')->nullable();
            $table->string('NoiDung', 1000);
            $table->dateTime('ThoiGianGui')->useCurrent();
            $table->string('TrangThai')->default('Đã gửi');
        });

        Schema::create('NhatKyHeThong', function (Blueprint $table): void {
            $table->increments('NhatKyID');
            $table->unsignedInteger('TaiKhoanID')->nullable();
            $table->string('HanhDong');
            $table->string('BangDuLieu');
            $table->unsignedBigInteger('BanGhiID')->nullable();
            $table->json('DuLieuCu')->nullable();
            $table->json('DuLieuMoi')->nullable();
            $table->string('LyDo')->nullable();
            $table->dateTime('ThoiGian')->useCurrent();
            $table->string('IPAddress')->nullable();
            $table->text('UserAgent')->nullable();
        });
    }

    public function test_store_message_uses_linked_customer_account_and_keeps_chat_out_of_audit_log(): void
    {
        $staff = $this->createAccount('store-staff');
        $customerAccount = $this->createAccount('laundry-customer', 18);
        $customer = KhachHang::query()->create([
            'KhachHangID' => 18,
            'HoTen' => 'Khách hàng thử nghiệm',
        ]);
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH001',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Chờ tiếp nhận',
        ]);
        $auditCountBeforeMessage = NhatKyHeThong::query()->count();

        $message = app(MessageService::class)->sendFromStore($order, $staff, ' Xin chào khách hàng! ');

        $this->assertSame($staff->TaiKhoanID, $message->NguoiGuiID);
        $this->assertSame($customerAccount->TaiKhoanID, $message->NguoiNhanID);
        $this->assertSame($order->DonHangID, $message->DonHangID);
        $this->assertSame('Xin chào khách hàng!', $message->NoiDung);
        $this->assertSame('Đã gửi', $message->TrangThai);
        $this->assertNotNull($message->ThoiGianGui);
        $incoming = TinNhan::query()->create([
            'NguoiGuiID' => $customerAccount->TaiKhoanID,
            'NguoiNhanID' => $staff->TaiKhoanID,
            'DonHangID' => $order->DonHangID,
            'NoiDung' => 'Tôi sẽ ghé lấy đồ.',
            'ThoiGianGui' => now()->subMinute(),
            'TrangThai' => 'Đã gửi',
        ]);
        $this->assertSame(2, TinNhan::query()->count());
        $this->assertSame($auditCountBeforeMessage, NhatKyHeThong::query()->count());
        $this->assertSame([$incoming->TinNhanID, $message->TinNhanID], app(MessageService::class)
            ->getMessages($order)
            ->pluck('TinNhanID')
            ->all());
    }

    public function test_message_is_rejected_when_customer_has_no_linked_account(): void
    {
        $staff = $this->createAccount('store-staff');
        $customer = KhachHang::query()->create([
            'KhachHangID' => 18,
            'HoTen' => 'Khách hàng chưa đăng ký tài khoản',
        ]);
        $order = DonHang::query()->create([
            'MaDonHang' => 'DH001',
            'KhachHangID' => $customer->KhachHangID,
            'TrangThai' => 'Chờ tiếp nhận',
        ]);

        try {
            app(MessageService::class)->sendFromStore($order, $staff, 'Nội dung');
            $this->fail('Messages must not be stored without a customer account recipient.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('message', $exception->errors());
        }

        $this->assertSame(0, TinNhan::query()->count());
    }

    public function test_system_log_screen_applies_table_action_account_and_date_filters(): void
    {
        $account = $this->createAccount('log-owner');
        NhatKyHeThong::query()->create([
            'TaiKhoanID' => $account->TaiKhoanID,
            'HanhDong' => 'Chuyển trạng thái đơn hàng',
            'BangDuLieu' => 'DonHang',
            'BanGhiID' => 7,
            'DuLieuCu' => ['TrangThai' => 'Chờ tiếp nhận'],
            'DuLieuMoi' => ['TrangThai' => 'Đã tiếp nhận'],
            'ThoiGian' => '2026-10-02 12:00:00',
        ]);
        NhatKyHeThong::query()->create([
            'TaiKhoanID' => $account->TaiKhoanID,
            'HanhDong' => 'Tạo Booking',
            'BangDuLieu' => 'Booking',
            'BanGhiID' => 8,
            'ThoiGian' => '2026-10-01 12:00:00',
        ]);

        $view = app(SystemLogController::class)->index(Request::create('/admin/system-logs', 'GET', [
            'table' => 'DonHang',
            'action' => 'trạng thái',
            'account_id' => $account->TaiKhoanID,
            'from' => '2026-10-02',
            'to' => '2026-10-02',
        ]));
        $logs = $view->getData()['logs'];

        $this->assertSame('admin.system_logs.index', $view->name());
        $this->assertSame(1, $logs->total());
        $this->assertSame('DonHang', $logs->items()[0]->BangDuLieu);
        $this->assertSame('Chuyển trạng thái đơn hàng', $logs->items()[0]->HanhDong);
    }

    public function test_staff_chat_and_manager_only_system_log_routes_have_expected_role_boundaries(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertContains(
            'role:manager|admin|staff|employee',
            $routes->getByName('admin.messages.index')->getAction('middleware'),
        );
        $this->assertContains(
            'role:manager|admin',
            $routes->getByName('admin.system-logs.index')->getAction('middleware'),
        );
    }

    private function createAccount(string $username, ?int $customerId = null): User
    {
        return User::query()->create([
            'TenDangNhap' => $username,
            'MatKhau' => 'test-password',
            'KhachHangID' => $customerId,
            'TrangThai' => 'Hoạt động',
        ]);
    }
}
