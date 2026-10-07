<?php

namespace Tests\Feature;

use App\Models\BangGia;
use App\Services\PricingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 7)->startOfDay());
        Schema::create('BangGia', function (Blueprint $table): void {
            $table->increments('BangGiaID');
            $table->integer('DichVuID');
            $table->integer('LoaiDoGiatID');
            $table->integer('DonViTinhID');
            $table->decimal('DonGia', 18, 2);
            $table->date('NgayApDung')->nullable();
            $table->date('NgayKetThuc')->nullable();
            $table->string('TrangThai');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('BangGia');
        $this->travelBack();
        parent::tearDown();
    }

    private function price(int $id, array $overrides = []): void
    {
        DB::table('BangGia')->insert(array_merge([
            'BangGiaID' => $id, 'DichVuID' => 1, 'LoaiDoGiatID' => 1,
            'DonViTinhID' => 1, 'DonGia' => $id * 1000,
            'NgayApDung' => '2026-10-01', 'NgayKetThuc' => null,
            'TrangThai' => 'Hoạt động',
        ], $overrides));
    }

    public function test_effective_dates_are_inclusive_and_future_expired_inactive_prices_are_excluded(): void
    {
        $this->price(1, ['NgayApDung' => '2026-10-07', 'NgayKetThuc' => '2026-10-07']);
        $this->price(2, ['NgayApDung' => '2026-10-08']);
        $this->price(3, ['NgayKetThuc' => '2026-10-06']);
        $this->price(4, ['TrangThai' => 'Ngừng hoạt động']);
        $this->assertSame(1, app(PricingService::class)->getLatestPricing(1, 1, 1)->BangGiaID);
        $this->assertSame(1000.0, BangGia::getLatestPrice(1, 1, 1));
    }

    public function test_newest_start_then_highest_id_wins_and_null_start_is_only_a_fallback(): void
    {
        $this->price(1, ['NgayApDung' => '2026-10-06']);
        $this->price(2, ['NgayApDung' => '2026-10-07']);
        $this->price(3, ['NgayApDung' => '2026-10-07']);
        $this->price(4, ['NgayApDung' => null]);
        $this->assertSame(3, app(PricingService::class)->getLatestPricing(1, 1, 1)->BangGiaID);
    }

    public function test_batch_lookup_preserves_unit_and_tuple_boundaries_in_one_query(): void
    {
        $this->price(1);
        $this->price(2, ['DonViTinhID' => 2]);
        $this->price(3, ['LoaiDoGiatID' => 2]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $prices = app(PricingService::class)->getLatestPricingForTuples([
            ['DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 1],
            ['DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 2],
        ]);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(2, $prices);
        $this->assertSame(1, $prices['1:1:1']->BangGiaID);
        $this->assertSame(2, $prices['1:1:2']->BangGiaID);
    }

    public function test_expired_price_with_newer_start_does_not_override_valid_price(): void
    {
        $this->price(1, ['NgayApDung' => '2026-10-01']);
        $this->price(2, ['NgayApDung' => '2026-10-05', 'NgayKetThuc' => '2026-10-06']);

        $pricing = app(PricingService::class);
        $this->assertSame(1, $pricing->getLatestPricing(1, 1, 1)->BangGiaID);
        $batch = $pricing->getLatestPricingForTuples([
            ['DichVuID' => 1, 'LoaiDoGiatID' => 1, 'DonViTinhID' => 1],
        ]);
        $this->assertSame(1, $batch['1:1:1']->BangGiaID);
    }

    public function test_price_with_future_end_date_remains_effective(): void
    {
        $this->price(1, ['NgayKetThuc' => '2026-10-08']);
        $this->assertSame(1, app(PricingService::class)->getLatestPricing(1, 1, 1)->BangGiaID);
    }

    public function test_no_effective_price_returns_null_and_empty_batch_does_not_query(): void
    {
        $this->price(1, ['NgayApDung' => '2026-10-08']);
        $this->assertNull(app(PricingService::class)->getLatestPricing(1, 1, 1));
        $this->assertNull(app(PricingService::class)->getLatestPrice(1, 1, 1));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame([], app(PricingService::class)->getLatestPricingForTuples([]));
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
