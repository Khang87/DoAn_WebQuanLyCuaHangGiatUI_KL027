<?php

namespace Tests\Support;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Http\Requests\Admin\LuuBookingRequest;
use App\Http\Requests\Admin\LuuDonHangRequest;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BookingInspectionFixture
{
    private function createInspectionCatalog(): void
    {
        DB::table('DonViTinh')->insert(['DonViTinhID' => 3, 'TenDonViTinh' => 'Cái', 'KyHieu' => 'Cái', 'TrangThai' => 'Hoạt động']);
        DB::table('BangGia')->insert(['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'DonGia' => 15000, 'NgayApDung' => '2026-01-01', 'TrangThai' => 'Hoạt động']);
    }

    private function actualItems(): array
    {
        return [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 3, 'SoLuong' => 2, 'TinhTrangTruocKhiGiat' => 'Ố nhẹ ở cổ áo']];
    }

    private function createBooking(array $overrides = []): Booking
    {
        DB::table('KhachHang')->insertOrIgnore(['KhachHangID' => 9]);
        DB::table('DichVu')->insertOrIgnore(['DichVuID' => 1]);
        DB::table('LoaiDoGiat')->insertOrIgnore(['LoaiDoGiatID' => 2, 'TenLoaiDoGiat' => 'Áo sơ mi']);

        return Booking::query()->create(array_merge([
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'HinhThucNhanDo' => 'Tại nhà',
            'DiaChiNhan' => '12 Nguyễn Huệ',
            'HinhThucTraDo' => 'Tại cửa hàng',
            'NgayHen' => '2026-10-05',
            'GioHen' => '14:30:00',
            'TrangThai' => BookingStatus::Pending->value,
        ], $overrides));
    }

    private function inspectionItems(Booking $booking): array
    {
        return $booking->chiTietBookings()->get()->map(fn ($item): array => array_merge(
            $item->only(['DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'SoLuong', 'KhoiLuong']),
            ['TinhTrangTruocKhiGiat' => 'Ố nhẹ ở cổ áo'],
        ))->all();
    }

    private function inspectionPayload(Booking $booking, int $employeeId): array
    {
        return [
            'customer_id' => $booking->KhachHangID,
            'staff_id' => $employeeId,
            'method' => $booking->HinhThucNhanDo,
            'address' => $booking->DiaChiNhan,
            'return_method' => $booking->HinhThucTraDo,
            'return_address' => $booking->DiaChiTra,
            'scheduled_date' => $booking->NgayHen->format('Y-m-d'),
            'scheduled_time' => $booking->GioHen->format('H:i'),
            'items' => $this->inspectionItems($booking),
        ];
    }

    private function createRequestCatalog(): void
    {
        DB::table('KhachHang')->insertOrIgnore(['KhachHangID' => 9]);
        DB::table('DichVu')->insert(['DichVuID' => 1]);
        DB::table('DanhMucLoaiDoGiat')->insert([
            'DanhMucID' => 1,
            'TenDanhMuc' => 'Quần áo',
        ]);
        DB::table('LoaiDoGiat')->insert([
            'LoaiDoGiatID' => 2,
            'TenLoaiDoGiat' => 'Áo sơ mi',
            'DanhMucID' => 1,
        ]);
        DB::table('DonViTinh')->insert([
            ['DonViTinhID' => 1, 'TenDonViTinh' => 'Kilogram', 'KyHieu' => 'KG', 'TrangThai' => 'Hoạt động'],
            ['DonViTinhID' => 2, 'TenDonViTinh' => 'Cái', 'KyHieu' => 'Cái', 'TrangThai' => 'Hoạt động'],
        ]);
    }

    private function validateBookingRequest(array $snapshot): array
    {
        $request = LuuBookingRequest::create('/admin/bookings/1', 'PUT', array_merge([
            'customer_id' => 9,
            'staff_id' => null,
            'method' => 'Tại nhà',
            'address' => '12 Nguyễn Huệ',
            'return_method' => 'Tại cửa hàng',
            'scheduled_date' => '2026-10-05',
            'scheduled_time' => '14:30',
            'notes' => null,
            'status' => BookingStatus::Pending->value,
        ], $snapshot));
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request->validated();
    }

    private function validateOrderRequest(array $snapshot, string $method = 'POST'): array
    {
        if ($method === 'POST') {
            $snapshot['items'] = array_map(
                fn (array $item): array => array_merge(['TinhTrangTruocKhiGiat' => 'Bình thường'], $item),
                $snapshot['items'] ?? [['DichVuID' => 1, 'LoaiDoGiatID' => 2, 'DonViTinhID' => 2, 'SoLuong' => 1]],
            );
        }
        $request = LuuDonHangRequest::create('/admin/orders/1', $method, array_merge([
            'KhachHangID' => 9,
            'NhanVienID' => 1,
            'TrangThai' => $method === 'POST' ? OrderStatus::Received->value : OrderStatus::Pending->value,
        ], $snapshot));
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request->validated();
    }

    private function actingAsBookingEmployee(): void
    {
        $user = new User;
        $user->forceFill([
            'TaiKhoanID' => 1,
            'NhanVienID' => 1,
            'TrangThai' => 'Hoạt động',
        ]);
        $this->actingAs($user);
    }

    private function createSchema(): void
    {
        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->increments('KhachHangID');
            $table->string('HoTen')->nullable();
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
        });

        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->unsignedInteger('KhachHangID')->nullable();
            $table->string('TenDangNhap');
            $table->string('SoDienThoai')->nullable();
            $table->string('Email')->nullable();
        });

        Schema::create('DichVu', function (Blueprint $table): void {
            $table->increments('DichVuID');
            $table->string('TenDichVu')->default('Giặt');
            $table->unsignedInteger('LoaiDichVuID')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
        });

        Schema::create('LoaiDichVu', function (Blueprint $table): void {
            $table->increments('LoaiDichVuID');
            $table->string('TenLoaiDichVu');
        });

        Schema::create('DanhMucLoaiDoGiat', function (Blueprint $table): void {
            $table->bigIncrements('DanhMucID');
            $table->string('TenDanhMuc');
            $table->text('MoTa')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
            $table->dateTime('NgayTao')->useCurrent();
        });

        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
            $table->string('TenLoaiDoGiat')->default('Áo sơ mi');
            $table->string('MoTa')->nullable();
            $table->string('TrangThai')->default('Hoạt động');
            $table->unsignedBigInteger('DanhMucID')->default(1);
        });

        Schema::create('NhanVien', function (Blueprint $table): void {
            $table->increments('NhanVienID');
            $table->string('TrangThai')->default('Hoạt động');
            $table->string('HoTen')->nullable();
        });
        DB::table('NhanVien')->insert([
            'NhanVienID' => 1,
            'HoTen' => 'Nhân viên kiểm thử',
        ]);

        Schema::create('Booking', function (Blueprint $table): void {
            $table->increments('BookingID');
            $table->string('MaBooking')->unique();
            $table->unsignedInteger('KhachHangID');
            $table->string('HinhThucNhanDo');
            $table->string('DiaChiNhan')->nullable();
            $table->string('HinhThucTraDo')->nullable();
            $table->string('DiaChiTra')->nullable();
            $table->decimal('PickupDeliveryFee', 12, 2)->default(0);
            $table->decimal('DeliveryFee', 12, 2)->default(0);
            $table->date('NgayHen');
            $table->time('GioHen');
            $table->string('GhiChu', 500)->nullable();
            $table->string('TrangThai');
            $table->dateTime('NgayTao')->useCurrent();
            $table->dateTime('NgayCapNhat')->nullable();
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->unsignedInteger('NhanVienXacNhanID')->nullable();
            $table->dateTime('ThoiGianXacNhan')->nullable();
        });

        Schema::create('ChiTietBooking', function (Blueprint $table): void {
            $table->increments('ChiTietBookingID');
            $table->unsignedInteger('BookingID');
            $table->unsignedInteger('DichVuID');
            $table->unsignedInteger('LoaiDoGiatID');
            $table->unsignedInteger('DonViTinhID');
            $table->decimal('SoLuong', 10, 2)->nullable();
            $table->decimal('KhoiLuong', 10, 2)->nullable();
            $table->decimal('DonGia', 18, 2)->default(0);
            $table->decimal('ThanhTien', 18, 2)->default(0);
            $table->string('GhiChu', 500)->nullable();
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

        Schema::create('DonHang', function (Blueprint $table): void {
            $table->increments('DonHangID');
            $table->string('MaDonHang')->unique();
            $table->unsignedInteger('BookingID')->nullable()->unique();
            $table->unsignedInteger('KhachHangID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('TrangThai');
            $table->decimal('TongTien', 18, 2)->default(0);
            $table->integer('DiemSuDung')->default(0);
            $table->decimal('TienGiamDoDiem', 18, 2)->default(0);
            $table->unsignedInteger('KhuyenMaiID')->nullable();
            $table->decimal('TienGiamKhuyenMai', 18, 2)->default(0);
            $table->decimal('PhiGiaoHang', 18, 2)->default(0);
            $table->decimal('ThanhTien', 18, 2)->default(0);
            $table->string('GhiChu', 500)->nullable();
            $table->dateTime('NgayTao')->useCurrent();
            $table->dateTime('NgayCapNhat')->nullable();
        });

        Schema::create('ChiTietDonHang', function (Blueprint $table): void {
            $table->increments('ChiTietDonHangID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('DichVuID');
            $table->unsignedInteger('LoaiDoGiatID');
            $table->unsignedInteger('DonViTinhID');
            $table->decimal('SoLuong', 10, 2)->nullable();
            $table->decimal('KhoiLuong', 10, 2)->nullable();
            $table->decimal('DonGia', 18, 2);
            $table->decimal('ThanhTien', 18, 2);
            $table->string('GhiChu', 500)->nullable();
            $table->text('TinhTrangTruocKhiGiat')->nullable();
        });

        Schema::create('GiaoNhan', function (Blueprint $table): void {
            $table->increments('GiaoNhanID');
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('NhanVienID')->nullable();
            $table->string('LoaiGiaoNhan');
            $table->string('HinhThuc');
            $table->string('DiaChi', 255)->nullable();
            $table->dateTime('ThoiGianDuKien')->nullable();
            $table->dateTime('ThoiGianThucTe')->nullable();
            $table->decimal('PhiGiaoNhan', 18, 2)->default(0);
            $table->string('TrangThai');
            $table->string('GhiChu', 500)->nullable();
        });

        Schema::create('HoaDon', function (Blueprint $table): void {
            $table->increments('HoaDonID');
            $table->string('TrangThai')->nullable();
            $table->unsignedInteger('DonHangID');
        });

        Schema::create('ThanhToan', function (Blueprint $table): void {
            $table->increments('ThanhToanID');
            $table->unsignedInteger('DonHangID');
        });

        Schema::create('DiemTichLuy', function (Blueprint $table): void {
            $table->increments('DiemTichLuyID');
            $table->unsignedInteger('KhachHangID')->unique();
            $table->integer('DiemHienTai')->default(0);
            $table->dateTime('NgayCapNhat')->nullable();
        });

        Schema::create('DonViTinh', function (Blueprint $table): void {
            $table->increments('DonViTinhID');
            $table->string('TenDonViTinh');
            $table->string('KyHieu')->nullable();
            $table->string('TrangThai');
        });

        Schema::create('BangGia', function (Blueprint $table): void {
            $table->increments('BangGiaID');
            $table->unsignedInteger('DichVuID');
            $table->unsignedInteger('LoaiDoGiatID');
            $table->unsignedInteger('DonViTinhID');
            $table->decimal('DonGia', 18, 2);
            $table->date('NgayApDung')->nullable();
            $table->date('NgayKetThuc')->nullable();
            $table->string('TrangThai');
        });
    }
}
