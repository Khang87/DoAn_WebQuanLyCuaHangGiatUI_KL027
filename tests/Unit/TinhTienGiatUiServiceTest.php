<?php

namespace Tests\Unit;

use App\Services\TinhTienGiatUiService;
use Tests\TestCase;

class TinhTienGiatUiServiceTest extends TestCase
{
    public function test_kg_items_use_minimum_weight_without_multiplying_quantity(): void
    {
        $service = new TinhTienGiatUiService;

        $amount = $service->tinhThanhTienChiTiet([
            'SoLuong' => 4,
            'KhoiLuong' => 2,
            'DonGia' => 10000,
            'TenDonViTinh' => 'kg',
        ]);

        $this->assertSame(30000.0, $amount);
    }

    public function test_kg_units_are_matched_case_insensitively_and_use_actual_weight_above_minimum(): void
    {
        $service = new TinhTienGiatUiService;

        $amount = $service->tinhThanhTienChiTiet([
            'SoLuong' => 3,
            'KhoiLuong' => 4,
            'DonGia' => 10000,
            'TenDonViTinh' => ' KG ',
        ]);

        $this->assertSame(40000.0, $amount);
    }

    public function test_non_weight_units_use_quantity_instead_of_weight(): void
    {
        $service = new TinhTienGiatUiService;

        $amount = $service->tinhThanhTienChiTiet([
            'SoLuong' => 2,
            'KhoiLuong' => 8,
            'DonGia' => 12500,
            'TenDonViTinh' => 'Cái',
        ]);

        $this->assertSame(25000.0, $amount);
    }

    public function test_non_weight_units_preserve_fractional_quantity_from_numeric_schema_column(): void
    {
        $service = new TinhTienGiatUiService;

        $amount = $service->tinhThanhTienChiTiet([
            'SoLuong' => 1.5,
            'DonGia' => 12500,
            'TenDonViTinh' => 'Mét',
        ]);

        $this->assertSame(18750.0, $amount);
    }

    public function test_explicit_minimum_weight_overrides_the_configured_default(): void
    {
        $service = new TinhTienGiatUiService;

        $amount = $service->tinhThanhTienChiTiet([
            'KhoiLuong' => 4,
            'DonGia' => 1000.5,
            'TenDonViTinh' => 'kg',
            'MucToiThieu' => 5,
        ]);

        $this->assertSame(5003.0, $amount);
    }

    public function test_invoice_total_sums_the_rounded_line_amounts(): void
    {
        $service = new TinhTienGiatUiService;

        $total = $service->tinhTongTienHoaDon([
            ['KhoiLuong' => 1, 'DonGia' => 1000, 'TenDonViTinh' => 'kg'],
            ['SoLuong' => 2, 'DonGia' => 12500, 'TenDonViTinh' => 'Cái'],
        ]);

        $this->assertSame(28000.0, $total);
    }
}
