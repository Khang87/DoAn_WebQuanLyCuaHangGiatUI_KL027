<?php

namespace App\Services;

use App\Models\BangGia;

class TinhTienGiatUiService
{
    /**
     * Tính thành tiền cho một dòng dịch vụ giặt ủi.
     *
     * @param  array<string, mixed>  $item
     */
    public function tinhThanhTienChiTiet(array $item): float
    {
        $donViTinh = $item['TenDonViTinh'] ?? $item['KyHieu'] ?? '';
        $donGia = (float) ($item['DonGia'] ?? 0);

        if (BangGia::isWeightUnit($donViTinh)) {
            $khoiLuong = (float) ($item['KhoiLuong'] ?? 0);
            $mucToiThieu = (float) ($item['MucToiThieu'] ?? config('giatui.khoi_luong_toi_thieu', 3.0));

            return round(max($khoiLuong, $mucToiThieu) * $donGia, 0);
        }

        $soLuong = (float) ($item['SoLuong'] ?? 0);

        return round($soLuong * $donGia, 0);
    }

    /**
     * Tính tổng tiền từ danh sách các dòng chi tiết.
     *
     * @param  array<int, array<string, mixed>>  $danhSachChiTiet
     */
    public function tinhTongTienHoaDon(array $danhSachChiTiet): float
    {
        $tongTien = 0.0;

        foreach ($danhSachChiTiet as $item) {
            $tongTien += $this->tinhThanhTienChiTiet($item);
        }

        return round($tongTien, 0);
    }
}
