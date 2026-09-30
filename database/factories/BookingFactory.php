<?php

namespace Database\Factories;

use App\Enums\BookingMethod;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\KhachHang;
use App\Models\NhanVien;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'KhachHangID' => $this->existingOrNewCustomerId(),
            'NhanVienID' => NhanVien::query()->inRandomOrder()->value('NhanVienID'),
            'HinhThucNhanDo' => fake()->randomElement(BookingMethod::values()),
            'NgayHen' => fake()->dateTimeBetween('+1 day', '+7 days'),
            'GioHen' => fake()->numberBetween(8, 18).':00:00',
            'DiaChiNhan' => fake()->address(),
            'GhiChu' => fake()->optional()->sentence(),
            'TrangThai' => fake()->randomElement([
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
                BookingStatus::Cancelled->value,
            ]),
        ];
    }

    /**
     * Bảng `KhachHang` không có factory riêng nên lấy khách hàng đang có; nếu
     * bảng trống (ví dụ database test) thì tạo một khách hàng tối thiểu.
     */
    private function existingOrNewCustomerId(): int
    {
        $existing = KhachHang::query()->inRandomOrder()->value('KhachHangID');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) KhachHang::query()->create([
            'HoTen' => fake()->name(),
            'SoDienThoai' => fake()->numerify('09########'),
            'Email' => fake()->unique()->safeEmail(),
            'DiaChi' => fake()->address(),
            'NgayTao' => Carbon::now(),
            'TrangThai' => 'Hoạt động',
        ])->KhachHangID;
    }

    public function pending(): static
    {
        return $this->state(fn () => ['TrangThai' => BookingStatus::Pending->value]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['TrangThai' => BookingStatus::Confirmed->value]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['TrangThai' => BookingStatus::Cancelled->value]);
    }
}
