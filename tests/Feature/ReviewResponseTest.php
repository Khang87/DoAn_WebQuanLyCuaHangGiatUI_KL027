<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\RejectCustomerRole;
use App\Models\DanhGia;
use App\Services\ReviewService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReviewResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('KhachHang', function (Blueprint $table): void {
            $table->unsignedInteger('KhachHangID')->primary();
            $table->string('HoTen')->nullable();
        });
        Schema::create('DonHang', function (Blueprint $table): void {
            $table->unsignedInteger('DonHangID')->primary();
            $table->string('MaDonHang')->nullable();
        });
        Schema::create('DanhGia', function (Blueprint $table): void {
            $table->unsignedInteger('DanhGiaID')->primary();
            $table->unsignedInteger('DonHangID');
            $table->unsignedInteger('KhachHangID');
            $table->unsignedInteger('SoSao');
            $table->string('BinhLuan', 1000)->nullable();
            $table->dateTime('NgayDanhGia');
            $table->string('TrangThai', 30);
            $table->text('shop_response')->nullable();
        });

        DB::table('KhachHang')->insert([
            'KhachHangID' => 7,
            'HoTen' => 'Khách hàng kiểm thử',
        ]);
        DB::table('DonHang')->insert([
            'DonHangID' => 24,
            'MaDonHang' => 'DH0024',
        ]);
        DB::table('DanhGia')->insert([
            'DanhGiaID' => 3,
            'DonHangID' => 24,
            'KhachHangID' => 7,
            'SoSao' => 5,
            'BinhLuan' => 'Dịch vụ tốt.',
            'NgayDanhGia' => '2026-10-06 14:50:00',
            'TrangThai' => 'Hiển thị',
            'shop_response' => null,
        ]);

        $this->withoutMiddleware([
            Authenticate::class,
            EnsureUserHasPermission::class,
            RejectCustomerRole::class,
        ]);
    }

    public function test_review_list_eager_loads_the_customer_account_avatar(): void
    {
        Schema::create('TaiKhoan', function (Blueprint $table): void {
            $table->increments('TaiKhoanID');
            $table->unsignedInteger('KhachHangID');
            $table->string('AvatarURL')->nullable();
        });
        DB::table('TaiKhoan')->insert([
            'KhachHangID' => 7,
            'AvatarURL' => 'https://example.test/account.jpg',
        ]);

        $review = app(ReviewService::class)->getAll()->first();

        $this->assertTrue($review->khachHang->relationLoaded('taiKhoan'));
        $this->assertSame('https://example.test/account.jpg', $review->khachHang->taiKhoan->AvatarURL);
    }

    public function test_response_is_saved_and_remains_available_after_a_fresh_query(): void
    {
        $this->from(route('reviews.show', 3))
            ->patch(route('reviews.respond', 3), ['shop_response' => 'Cảm ơn bạn đã góp ý.'])
            ->assertRedirect(route('reviews.show', ['review' => 3, 'respond' => 1]))
            ->assertSessionHas('success', 'Phản hồi đã được gửi.');

        $reloadedReview = DanhGia::query()->findOrFail(3);

        $this->assertSame('Cảm ơn bạn đã góp ý.', $reloadedReview->getAttribute('shop_response'));
        $this->assertSame('2026-10-06 14:50:00', $reloadedReview->NgayDanhGia->format('Y-m-d H:i:s'));
    }

    public function test_existing_response_can_be_updated_without_changing_reviewed_at(): void
    {
        DB::table('DanhGia')->where('DanhGiaID', 3)->update([
            'shop_response' => 'Nội dung cũ.',
        ]);

        $this->from(route('reviews.show', 3))
            ->patch(route('reviews.respond', 3), ['shop_response' => 'Nội dung đã chỉnh sửa.'])
            ->assertRedirect(route('reviews.show', ['review' => 3, 'respond' => 1]))
            ->assertSessionHas('success');

        $reloadedReview = app(ReviewService::class)->find(3);

        $this->assertSame('Nội dung đã chỉnh sửa.', $reloadedReview?->getAttribute('shop_response'));
        $this->assertSame('2026-10-06 14:50:00', $reloadedReview?->NgayDanhGia->format('Y-m-d H:i:s'));
    }

    public function test_empty_or_whitespace_only_response_is_rejected_without_writing(): void
    {
        foreach (['', " \t\n "] as $response) {
            $this->from(route('reviews.show', 3))
                ->patch(route('reviews.respond', 3), ['shop_response' => $response])
                ->assertSessionHasErrors('shop_response');
        }

        $this->assertNull(DanhGia::query()->findOrFail(3)->getAttribute('shop_response'));
    }

    public function test_response_longer_than_2000_characters_is_rejected_without_writing(): void
    {
        $this->from(route('reviews.show', 3))
            ->patch(route('reviews.respond', 3), ['shop_response' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('shop_response');

        $this->assertNull(DanhGia::query()->findOrFail(3)->getAttribute('shop_response'));
    }

    public function test_controller_does_not_report_success_when_model_save_does_not_persist(): void
    {
        DanhGia::saving(static fn (): bool => false);

        $this->from(route('reviews.show', 3))
            ->patch(route('reviews.respond', 3), ['shop_response' => 'Không được ghi.'])
            ->assertRedirect(route('reviews.show', 3))
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        $this->assertNull(DanhGia::query()->findOrFail(3)->getAttribute('shop_response'));
    }
}
