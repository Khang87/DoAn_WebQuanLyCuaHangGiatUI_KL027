<?php

namespace App\Exports;

use App\Enums\InvoiceStatus;
use App\Models\HoaDon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceDetailExport
{
    protected int|string $invoiceId;

    public function __construct(int|string $invoiceId)
    {
        $this->invoiceId = $invoiceId;
    }

    public function export(): StreamedResponse
    {
        $invoice = HoaDon::with([
            'donHang.khachHang',
            'donHang.chiTietDonHangs.dichVu',
            'donHang.chiTietDonHangs.loaiDoGiat',
            'donHang.chiTietDonHangs.donViTinh',
            'donHang.thanhToans',
        ])
            ->where('HoaDonID', $this->invoiceId)
            ->orWhere('MaHoaDon', $this->invoiceId)
            ->firstOrFail();

        $order = $invoice->donHang;
        $customer = $order?->khachHang;
        $items = $order?->chiTietDonHangs ?? collect();
        $payment = $order?->thanhToans?->last();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Chi tiết hóa đơn');

        $row = 1;

        $sheet->getCell('A'.$row)->setCellValue('CỬA HÀNG GIẶT ỦI SKY LAUNDRY');
        $sheet->mergeCells('A'.$row.':H'.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        $sheet->getCell('A'.$row)->setCellValue('Địa chỉ: Số 123 Đường Lê Lợi, Quận 1, TP. HCM | Hotline: 0909.123.456');
        $sheet->mergeCells('A'.$row.':H'.$row);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A'.$row)->getFont()->setItalic(true)->setSize(10);
        $row++;
        $row++;

        $sheet->getCell('A'.$row)->setCellValue('HÓA ĐƠN DỊCH VỤ GIẶT ỦI');
        $sheet->mergeCells('A'.$row.':H'.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        $sheet->getCell('A'.$row)->setCellValue('Mã hóa đơn:');
        $sheet->getCell('B'.$row)->setCellValue($invoice->MaHoaDon ?? '-');
        $sheet->getCell('D'.$row)->setCellValue('Ngày lập:');
        $sheet->getCell('E'.$row)->setCellValue($invoice->NgayLap?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'));
        $sheet->getCell('G'.$row)->setCellValue('Trạng thái:');
        $sheet->getCell('H'.$row)->setCellValue(InvoiceStatus::labelFor($invoice->TrangThai));
        $row++;

        $row++;
        $sheet->getCell('A'.$row)->setCellValue('THÔNG TIN KHÁCH HÀNG');
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->mergeCells('A'.$row.':H'.$row);
        $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
        $row++;

        $sheet->getCell('A'.$row)->setCellValue('Họ tên:');
        $sheet->getCell('B'.$row)->setCellValue($customer?->HoTen ?? '-');
        $sheet->getCell('D'.$row)->setCellValue('SĐT:');
        $sheet->getCell('E'.$row)->setCellValue($customer?->SoDienThoai ?? '-');
        $row++;
        $sheet->getCell('A'.$row)->setCellValue('Địa chỉ:');
        $sheet->getCell('B'.$row)->setCellValue($customer?->DiaChi ?? '-');
        $sheet->getCell('D'.$row)->setCellValue('Mã đơn:');
        $sheet->getCell('E'.$row)->setCellValue($order?->MaDonHang ?? '-');
        $row++;

        if ($order?->GhiChu) {
            $sheet->getCell('A'.($row + 1))->setCellValue('Ghi chú:');
            $sheet->getCell('B'.($row + 1))->setCellValue($order->GhiChu);
            $row += 2;
        } else {
            $row++;
        }

        $sheet->getCell('A'.$row)->setCellValue('PHƯƠNG THỨC THANH TOÁN: '.match ($payment?->PhuongThuc ?? 'cash') {
            'cash' => 'Tiền mặt',
            'bank_transfer' => 'Chuyển khoản ngân hàng (QR Code)',
            'momo' => 'Ví MoMo',
            'credit_card' => 'Thẻ ATM/Credit',
            'e_wallet' => 'Ví điện tử',
            default => 'Chưa thanh toán',
        });
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->mergeCells('A'.$row.':H'.$row);
        $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
        $row++;

        $row++;
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3056D3']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $headers = ['STT', 'Tên dịch vụ / Loại đồ', 'Đơn vị tính', 'Số lượng', 'Đơn giá (VNĐ)', 'Thành tiền (VNĐ)'];
        $col = 1;
        foreach ($headers as $header) {
            $coordinate = Coordinate::stringFromColumnIndex($col).$row;
            $sheet->getCell($coordinate)->setValue($header);
            $sheet->getStyle($coordinate)->applyFromArray($headerStyle);
            $col++;
        }
        $sheet->getStyle('A'.$row.':'.Coordinate::stringFromColumnIndex($col - 1).$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3056D3');
        $sheet->freezePane('A'.($row + 1));
        $row++;

        $totalAmount = 0;
        $itemRow = $row;
        foreach ($items as $item) {
            $sheet->getCell('A'.$itemRow)->setCellValue($row - $row + 1);
            $sheet->getCell('B'.$itemRow)->setCellValue($item->item_name);
            $sheet->getCell('C'.$itemRow)->setCellValue($item->unit ?? '-');
            $sheet->getCell('D'.$itemRow)->setCellValue($item->SoLuong ?? 0);
            $sheet->getCell('E'.$itemRow)->setCellValue((float) ($item->DonGia ?? 0));
            $sheet->getCell('F'.$itemRow)->setCellValue((float) ($item->ThanhTien ?? 0));
            $sheet->getStyle('E'.$itemRow.':F'.$itemRow)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('E'.$itemRow.':F'.$itemRow.':A'.$itemRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A'.$itemRow.':'.Coordinate::stringFromColumnIndex($col - 1).$itemRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $totalAmount += (float) ($item->ThanhTien ?? 0);
            $itemRow++;
        }

        if (empty($items->count())) {
            $sheet->getCell('A'.$itemRow)->setCellValue('Chưa có dữ liệu dịch vụ');
            $sheet->mergeCells('A'.$itemRow.':'.Coordinate::stringFromColumnIndex($col - 1).$itemRow);
            $sheet->getStyle('A'.$itemRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A'.$itemRow)->getFont()->setItalic(true)->getColor()->setRGB('999999');
            $itemRow++;
            $totalAmount = (float) $invoice->TongTien;
        } else {
            $totalAmount = $totalAmount > 0 ? $totalAmount : (float) $invoice->TongTien;
        }

        $sheet->getStyle('D'.$row.':F'.($itemRow - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('E'.$row.':F'.($itemRow - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $itemRow++;
        $sheet->getCell('A'.$itemRow)->setCellValue('TẠM TÍNH:');
        $sheet->mergeCells('A'.$itemRow.':E'.$itemRow);
        $sheet->getStyle('A'.$itemRow)->getFont()->setBold(true);
        $sheet->getCell('F'.$itemRow)->setCellValue($totalAmount);
        $sheet->getStyle('F'.$itemRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('F'.$itemRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$itemRow.':F'.$itemRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $itemRow++;

        $discountAmount = (float) $invoice->GiamGia;
        $sheet->getCell('A'.$itemRow)->setCellValue('GIẢM GIÁ:');
        $sheet->mergeCells('A'.$itemRow.':E'.$itemRow);
        $sheet->getStyle('A'.$itemRow)->getFont()->setBold(true);
        $sheet->getCell('F'.$itemRow)->setCellValue($discountAmount);
        $sheet->getStyle('F'.$itemRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('F'.$itemRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$itemRow.':F'.$itemRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $itemRow++;

        $finalTotal = (float) $invoice->ThanhTien;
        $itemRow++;
        $itemRow++;
        $sheet->getCell('A'.$itemRow)->setCellValue('TỔNG CỘNG THÀNH TIỀN:');
        $sheet->mergeCells('A'.$itemRow.':E'.$itemRow);
        $sheet->getStyle('A'.$itemRow)->getFont()->setBold(true)->setSize(14);
        $sheet->getCell('F'.$itemRow)->setCellValue($finalTotal);
        $sheet->getStyle('F'.$itemRow)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('F'.$itemRow)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.$itemRow.':F'.$itemRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A'.$itemRow.':F'.$itemRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3056D3');
        $sheet->getStyle('A'.$itemRow.':F'.$itemRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A'.$itemRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('F'.$itemRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $itemRow++;
        $itemRow++;
        $sheet->getCell('A'.$itemRow)->setCellValue('Cảm ơn quý khách đã tin tưởng sử dụng dịch vụ!');
        $sheet->mergeCells('A'.$itemRow.':F'.$itemRow);
        $sheet->getStyle('A'.$itemRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A'.$itemRow)->getFont()->setItalic(true)->setSize(11);

        foreach (range('A', 'F') as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setWidth(20);
        }
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(20);

        $filename = 'ChiTiet_'.($invoice->MaHoaDon ?? 'invoice_'.$invoice->HoaDonID).'_'.now()->format('Y_m_d').'.xlsx';

        $response = new StreamedResponse;
        $response->setCallback(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
