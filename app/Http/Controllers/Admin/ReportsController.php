<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Services\ReportsService;
use App\Support\TimePeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    // Payment method Vietnamese translations
    private const PAYMENT_METHOD_LABELS = [
        'Tiền mặt' => 'Tiền mặt',
        'Chuyển khoản' => 'Chuyển khoản / QR',
    ];

    public function __construct(
        private ReportsService $reportsService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('reports.view');

        $filter = TimePeriod::normalize($request->query('filter'), TimePeriod::ALL_TIME);
        $filters = ['range' => $filter];

        $kpi = $this->reportsService->getKpiMetrics($filters);
        $chartData = $this->reportsService->getRevenueChartData($filters);
        $compositionData = $this->reportsService->getServiceComposition($filters);
        $topServices = $this->reportsService->getTopServices($filters, 5);
        $paymentMethods = $this->reportsService->getRevenueByPaymentMethod($filters);
        $recentOrders = $this->reportsService->getRecentOrders($filters);

        // Chart colors for consistent UI
        $chartColors = [
            '#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6',
            '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1',
        ];

        // Payment method badge colors
        $paymentMethodColors = [
            'Tiền mặt' => 'bg-success-subtle text-success-emphasis border border-success',
            'Chuyển khoản' => 'bg-primary-subtle text-primary-emphasis border border-primary',
        ];

        // Payment method Vietnamese labels
        $paymentMethodLabels = self::PAYMENT_METHOD_LABELS;

        return view('admin.reports.index', compact(
            'filters',
            'kpi',
            'chartData',
            'compositionData',
            'topServices',
            'paymentMethods',
            'recentOrders',
            'chartColors',
            'paymentMethodColors',
            'paymentMethodLabels',
        ) + [
            'reportFilter' => $filter,
            'periodLabel' => TimePeriod::label($filter),
            'periodDescription' => TimePeriod::description($filter),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('reports.view');

        $filter = TimePeriod::normalize($request->query('filter'), TimePeriod::ALL_TIME);
        $filters = ['range' => $filter];
        $kpi = $this->reportsService->getKpiMetrics($filters);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Tong_Quan_Thong_Ke');
        $this->setText($summary, 'A1', 'Tên cửa hàng');
        $this->setText($summary, 'B1', 'Sky Laundry');
        $this->setText($summary, 'A2', 'Tên báo cáo');
        $this->setText($summary, 'B2', 'BÁO CÁO & THỐNG KÊ KINH DOANH GIẶT ỦI');
        $this->setText($summary, 'A3', 'Thời gian áp dụng');
        $this->setText($summary, 'B3', $this->exportPeriodLabel($filter));
        $summary->getStyle('A1:A3')->getFont()->setBold(true)->getColor()->setRGB('475569');
        $summary->getStyle('A1:A3')->getFill()->setFillType('solid')->getStartColor()->setRGB('F1F5F9');
        $summary->getStyle('B1:B3')->getAlignment()->setHorizontal('left');
        $summary->getStyle('A1:B3')->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setRGB('D7E0EA');
        $summary->getStyle('B2')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('1D4ED8');
        $summary->getRowDimension(2)->setRowHeight(26);
        $summary->getRowDimension(4)->setRowHeight(10);

        $summary->fromArray([
            ['Chỉ số tổng quan', 'Giá trị'],
            ['Tổng doanh thu (VNĐ)', $kpi['total_revenue']],
            ['Tổng đơn hàng', $kpi['total_orders']],
            ['Hoàn thành', $kpi['completed_orders']],
            ['Đang xử lý', $kpi['processing_orders']],
            ['Đã hủy', $kpi['cancelled_orders']],
            ['Đơn đặt lịch mới', $kpi['new_bookings']],
            ['Giá trị đơn trung bình - AOV (VNĐ)', $kpi['avg_order_value']],
        ], null, 'A5');
        $this->styleReportTable($summary, 'A5:B12', 'A5:B5');
        $summary->getStyle('A5:B5')->getAlignment()->setHorizontal('center');
        $summary->getStyle('A6:A12')->getAlignment()->setHorizontal('left');
        $summary->getStyle('B6:B12')->getAlignment()->setHorizontal('right');
        $summary->getStyle('A6:B6')->getFill()->setFillType('solid')->getStartColor()->setRGB('EFF6FF');
        $summary->getStyle('B6')->getNumberFormat()->setFormatCode('#,##0');
        $summary->getStyle('B12')->getNumberFormat()->setFormatCode('#,##0');

        $summary->mergeCells('A14:E14');
        $this->setText($summary, 'A14', 'Top 5 Dịch vụ bán chạy');
        $summary->fromArray([['STT', 'Dịch vụ', 'Đơn vị tính (ĐVT)', 'Số lượng bán', 'Doanh thu (VNĐ)']], null, 'A15');

        $topServices = $this->reportsService->getTopServices($filters, 5);
        $row = 16;
        $totalQuantity = 0.0;
        $totalServiceRevenue = 0.0;
        foreach ($topServices as $index => $service) {
            $summary->setCellValue('A'.$row, $index + 1);
            $this->setText($summary, 'B'.$row, (string) $service->name);
            $this->setText($summary, 'C'.$row, (string) ($service->unit ?? ''));
            $summary->setCellValue('D'.$row, $service->total_qty);
            $summary->setCellValue('E'.$row, $service->total_revenue);
            $totalQuantity += $service->total_qty;
            $totalServiceRevenue += $service->total_revenue;
            $row++;
        }

        $serviceTotalRow = $row;
        $this->setText($summary, 'B'.$serviceTotalRow, 'Tổng cộng');
        $summary->setCellValue('D'.$serviceTotalRow, $totalQuantity);
        $summary->setCellValue('E'.$serviceTotalRow, $totalServiceRevenue);
        $this->styleReportSection($summary, 'A14:E14');
        $this->styleReportTable($summary, 'A15:E'.$serviceTotalRow, 'A15:E15', $serviceTotalRow);
        $summary->getStyle('A15:A'.$serviceTotalRow)->getAlignment()->setHorizontal('center');
        $summary->getStyle('B16:C'.$serviceTotalRow)->getAlignment()->setHorizontal('left');
        $summary->getStyle('D16:D'.$serviceTotalRow)->getAlignment()->setHorizontal('right');
        $summary->getStyle('E16:E'.$serviceTotalRow)->getAlignment()->setHorizontal('right');
        $summary->getStyle('E16:E'.$serviceTotalRow)->getNumberFormat()->setFormatCode('#,##0');

        $paymentTitleRow = $serviceTotalRow + 3;
        $paymentHeaderRow = $paymentTitleRow + 1;
        $summary->mergeCells('A'.$paymentTitleRow.':E'.$paymentTitleRow);
        $this->setText($summary, 'A'.$paymentTitleRow, 'Doanh thu theo Phương thức thanh toán');
        $summary->fromArray([['STT', 'Phương thức thanh toán', 'Số giao dịch', 'Tổng tiền (VNĐ)', 'Tỷ lệ (%)']], null, 'A'.$paymentHeaderRow);

        $payments = $this->reportsService->getRevenueByPaymentMethod($filters);
        $totalPaymentAmount = (float) $payments->sum('total_amount');
        $totalTransactions = 0;
        $row = $paymentHeaderRow + 1;
        foreach ($payments as $index => $payment) {
            $summary->setCellValue('A'.$row, $index + 1);
            $this->setText($summary, 'B'.$row, self::PAYMENT_METHOD_LABELS[$payment->method] ?? (string) $payment->method);
            $summary->setCellValue('C'.$row, $payment->transaction_count);
            $summary->setCellValue('D'.$row, $payment->total_amount);
            $summary->setCellValue('E'.$row, $totalPaymentAmount > 0 ? $payment->total_amount / $totalPaymentAmount : 0);
            $totalTransactions += $payment->transaction_count;
            $row++;
        }

        $paymentTotalRow = $row;
        $this->setText($summary, 'B'.$paymentTotalRow, 'Tổng cộng');
        $summary->setCellValue('C'.$paymentTotalRow, $totalTransactions);
        $summary->setCellValue('D'.$paymentTotalRow, $totalPaymentAmount);
        $summary->setCellValue('E'.$paymentTotalRow, 1);
        $this->styleReportSection($summary, 'A'.$paymentTitleRow.':E'.$paymentTitleRow);
        $this->styleReportTable($summary, 'A'.$paymentHeaderRow.':E'.$paymentTotalRow, 'A'.$paymentHeaderRow.':E'.$paymentHeaderRow, $paymentTotalRow);
        $summary->getStyle('A'.$paymentHeaderRow.':A'.$paymentTotalRow)->getAlignment()->setHorizontal('center');
        $summary->getStyle('B'.($paymentHeaderRow + 1).':B'.$paymentTotalRow)->getAlignment()->setHorizontal('left');
        $summary->getStyle('C'.($paymentHeaderRow + 1).':C'.$paymentTotalRow)->getAlignment()->setHorizontal('center');
        $summary->getStyle('D'.($paymentHeaderRow + 1).':D'.$paymentTotalRow)->getAlignment()->setHorizontal('right');
        $summary->getStyle('E'.($paymentHeaderRow + 1).':E'.$paymentTotalRow)->getAlignment()->setHorizontal('right');
        $summary->getStyle('D'.$paymentHeaderRow.':D'.$paymentTotalRow)->getNumberFormat()->setFormatCode('#,##0');
        $summary->getStyle('E'.$paymentHeaderRow.':E'.$paymentTotalRow)->getNumberFormat()->setFormatCode('0.0%');
        $summary->getRowDimension($paymentHeaderRow)->setRowHeight(30);
        $summary->freezePane('A5');

        $ordersSheet = $spreadsheet->createSheet();
        $ordersSheet->setTitle('Danh_Sach_Don_Hang');
        $ordersSheet->fromArray([[
            'STT',
            'Mã đơn',
            'Khách hàng',
            'Dịch vụ sử dụng',
            'Trạng thái',
            'Tổng tiền (VNĐ)',
            'Ngày tạo',
        ]], null, 'A1');

        $orders = $this->reportsService->getOrdersForExport($filters);
        $row = 2;
        foreach ($orders as $index => $order) {
            $services = $order->chiTietDonHangs
                ->map(fn ($item) => $item->dichVu?->TenDichVu)
                ->filter()
                ->unique()
                ->join(', ');

            $ordersSheet->setCellValue('A'.$row, $index + 1);
            $this->setText($ordersSheet, 'B'.$row, (string) $order->MaDonHang);
            $this->setText($ordersSheet, 'C'.$row, (string) ($order->khachHang?->HoTen ?? '—'));
            $this->setText($ordersSheet, 'D'.$row, $services !== '' ? $services : '—');
            $this->setText($ordersSheet, 'E'.$row, OrderStatus::tryFrom((string) $order->TrangThai)?->label() ?? (string) $order->TrangThai);
            $ordersSheet->setCellValue('F'.$row, (float) ($order->ThanhTien ?? 0));
            $createdAt = $order->getRawOriginal('NgayTao');
            if ($createdAt !== null) {
                $ordersSheet->setCellValue(
                    'G'.$row,
                    Date::dateTimeToExcel(Carbon::parse($createdAt, 'UTC')->setTimezone('Asia/Ho_Chi_Minh')),
                );
            }
            $row++;
        }

        $lastOrderRow = $row - 1;
        $this->styleReportTable($ordersSheet, 'A1:G'.max($lastOrderRow, 1), 'A1:G1');
        if ($lastOrderRow >= 2) {
            $ordersSheet->getStyle('A1:A'.$lastOrderRow)->getAlignment()->setHorizontal('center');
            $ordersSheet->getStyle('B2:E'.$lastOrderRow)->getAlignment()->setHorizontal('left');
            $ordersSheet->getStyle('F2:F'.$lastOrderRow)->getAlignment()->setHorizontal('right');
            $ordersSheet->getStyle('G2:G'.$lastOrderRow)->getAlignment()->setHorizontal('center');
            $ordersSheet->getStyle('F2:F'.$lastOrderRow)->getNumberFormat()->setFormatCode('#,##0');
            $ordersSheet->getStyle('G2:G'.$lastOrderRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        }
        $ordersSheet->freezePane('A2');
        $ordersSheet->setAutoFilter('A1:G1');

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
            for ($column = 1; $column <= $highestColumn; $column++) {
                $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
            }
        }

        $fileName = 'Bao_Cao_'.str_replace('_', '-', $filter).'_'.now()->format('Y_m_d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function setText(Worksheet $sheet, string $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }

    private function styleReportSection(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFill()->setFillType('solid')->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getAlignment()->setVertical('center');
        $sheet->getRowDimension((int) preg_replace('/\D/', '', explode(':', $range)[0]))->setRowHeight(23);
    }

    private function styleReportTable(Worksheet $sheet, string $range, string $headerRange, ?int $totalRow = null): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setRGB('D7E0EA');
        $sheet->getStyle($range)->getAlignment()->setVertical('center');
        $sheet->getStyle($headerRange)->getFill()->setFillType('solid')->getStartColor()->setRGB('DBEAFE');
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('1E3A8A');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal('center')->setWrapText(true);
        $sheet->getRowDimension((int) preg_replace('/\D/', '', explode(':', $headerRange)[0]))->setRowHeight(24);

        if ($totalRow !== null) {
            $sheet->getStyle('A'.$totalRow.':'.$sheet->getHighestColumn($totalRow).$totalRow)
                ->getFill()->setFillType('solid')->getStartColor()->setRGB('E2E8F0');
            $sheet->getStyle('A'.$totalRow.':'.$sheet->getHighestColumn($totalRow).$totalRow)->getFont()->setBold(true);
        }
    }

    private function exportPeriodLabel(string $filter): string
    {
        $dates = $this->reportsService->getDateRange(['range' => $filter]);

        if ($dates['local_from'] === null || $dates['local_to'] === null) {
            return TimePeriod::label($filter);
        }

        return TimePeriod::label($filter).' ('.$dates['local_from']->format('d/m/Y').' – '.$dates['local_to']->format('d/m/Y').')';
    }
}
