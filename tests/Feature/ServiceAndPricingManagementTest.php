<?php

namespace Tests\Feature;

use App\Enums\RecordStatus;
use App\Http\Requests\Admin\LuuBangGiaRequest;
use App\Http\Requests\Admin\LuuDichVuRequest;
use App\Models\LoaiDichVu;
use App\Services\PricingService;
use App\Services\ServiceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceAndPricingManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('LoaiDichVu', function (Blueprint $table): void {
            $table->increments('LoaiDichVuID');
            $table->string('TenLoaiDichVu');
            $table->string('TrangThai');
        });
        Schema::create('DichVu', function (Blueprint $table): void {
            $table->increments('DichVuID');
            $table->unsignedInteger('LoaiDichVuID');
            $table->string('TenDichVu');
            $table->string('MoTa')->nullable();
            $table->integer('ThoiGianDuKien')->nullable();
            $table->string('TrangThai');
            $table->string('Icon')->nullable();
            $table->dateTime('NgayTao')->nullable();
        });
        Schema::create('LoaiDoGiat', function (Blueprint $table): void {
            $table->increments('LoaiDoGiatID');
            $table->string('TenLoaiDoGiat');
        });
        Schema::create('DonViTinh', function (Blueprint $table): void {
            $table->increments('DonViTinhID');
            $table->string('TenDonViTinh');
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

        DB::table('LoaiDichVu')->insert([
            'LoaiDichVuID' => 1,
            'TenLoaiDichVu' => 'Giặt',
            'TrangThai' => 'Hoạt động',
        ]);
        DB::table('LoaiDoGiat')->insert([
            'LoaiDoGiatID' => 1,
            'TenLoaiDoGiat' => 'Áo',
        ]);
        DB::table('DonViTinh')->insert([
            'DonViTinhID' => 1,
            'TenDonViTinh' => 'Cái',
        ]);
        DB::table('DichVu')->insert([
            'DichVuID' => 1,
            'LoaiDichVuID' => 1,
            'TenDichVu' => 'Giặt thường',
            'TrangThai' => 'Hoạt động',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['BangGia', 'DonViTinh', 'LoaiDoGiat', 'DichVu', 'LoaiDichVu'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_service_without_a_name_is_rejected_without_saving_a_record(): void
    {
        $data = $this->serviceData();
        unset($data['TenDichVu']);

        try {
            $this->validateService($data);
            $this->fail('A service without a name should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertSame('Tên dịch vụ là bắt buộc.', $exception->errors()['TenDichVu'][0]);
        }

        $this->assertDatabaseCount('DichVu', 1);
    }

    public function test_valid_service_is_saved_with_its_duration_and_selected_icon(): void
    {
        $data = $this->serviceData();
        $service = app(ServiceService::class)->create($this->validateService($data));

        $this->assertDatabaseHas('DichVu', [
            'DichVuID' => $service->getKey(),
            'TenDichVu' => $data['TenDichVu'],
            'LoaiDichVuID' => $data['LoaiDichVuID'],
            'ThoiGianDuKien' => $data['ThoiGianDuKien'],
            'Icon' => $data['Icon'],
        ]);

        View::share('errors', new ViewErrorBag);
        $html = view('admin.services.index', [
            'services' => app(ServiceService::class)->getAll(),
            'categories' => LoaiDichVu::query()->get(),
            'statuses' => RecordStatus::databaseOptions(),
        ])->render();

        $this->assertStringContainsString($data['TenDichVu'], $html);
        $this->assertStringContainsString('data-icon-glyph', $html);
    }

    public function test_valid_pricing_is_saved_and_used_as_the_effective_price(): void
    {
        $data = $this->pricingData();
        $pricing = app(PricingService::class)->create($this->validatePricing($data));

        $this->assertDatabaseHas('BangGia', [
            'BangGiaID' => $pricing->getKey(),
            'DichVuID' => $data['DichVuID'],
            'LoaiDoGiatID' => $data['LoaiDoGiatID'],
            'DonViTinhID' => $data['DonViTinhID'],
            'DonGia' => $data['DonGia'],
        ]);
        $this->assertSame($data['NgayApDung'], $pricing->fresh()->NgayApDung->toDateString());
        $this->assertSame(
            (float) $data['DonGia'],
            app(PricingService::class)->getLatestPrice(1, 1, 1),
        );
    }

    public function test_negative_pricing_is_rejected_without_saving_a_record(): void
    {
        $data = $this->pricingData();
        $data['DonGia'] = -1;

        try {
            $this->validatePricing($data);
            $this->fail('A negative price should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('DonGia', $exception->errors());
        }

        $this->assertDatabaseCount('BangGia', 0);
    }

    private function serviceData(): array
    {
        return [
            'TenDichVu' => 'Giặt hấp nhanh',
            'LoaiDichVuID' => 1,
            'ThoiGianDuKien' => 60,
            'TrangThai' => 'Hoạt động',
            'Icon' => 'material:cleaning_services_outlined',
        ];
    }

    private function pricingData(): array
    {
        return [
            'DichVuID' => 1,
            'LoaiDoGiatID' => 1,
            'DonViTinhID' => 1,
            'DonGia' => 25000,
            'NgayApDung' => today()->toDateString(),
            'NgayKetThuc' => null,
            'TrangThai' => 'Hoạt động',
        ];
    }

    private function validateService(array $data): array
    {
        $request = LuuDichVuRequest::create('/services', 'POST', $data);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request->validated();
    }

    private function validatePricing(array $data): array
    {
        $request = LuuBangGiaRequest::create('/pricings', 'POST', $data);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request->validated();
    }
}
