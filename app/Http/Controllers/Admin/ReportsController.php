<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportsService;
use App\Support\TimePeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Tổng quan');
        $summary->fromArray([
            ['BÁO CÁO THỐNG KÊ KINH DOANH'],
            ['Khoảng thời gian', TimePeriod::label($filter)],
            ['Mô tả', TimePeriod::description($filter)],
            [],
            ['Chỉ số', 'Giá trị'],
            ['Tổng doanh thu (VNĐ)', $kpi['total_revenue']],
            ['Tổng đơn hàng', $kpi['total_orders']],
            ['Đơn hàng hoàn tất', $kpi['completed_orders']],
            ['Đơn hàng đang xử lý', $kpi['processing_orders']],
            ['Đơn hàng đã hủy', $kpi['cancelled_orders']],
            ['Đặt lịch mới', $kpi['new_bookings']],
            ['Giá trị đơn trung bình (VNĐ)', $kpi['avg_order_value']],
        ], null, 'A1');
        $summary->getStyle('A1:B1')->getFont()->setBold(true)->setSize(14);
        $summary->getStyle('A5:B5')->getFont()->setBold(true);
        $summary->getStyle('B6:B12')->getNumberFormat()->setFormatCode('#,##0.##');

        $chart = $this->reportsService->getRevenueChartData($filters);
        $revenueSheet = $spreadsheet->createSheet();
        $revenueSheet->setTitle('Doanh thu');
        $revenueSheet->fromArray([['Kỳ', 'Doanh thu (VNĐ)', 'Số đơn']], null, 'A1');
        foreach ($chart['labels'] as $index => $label) {
            $row = $index + 2;
            $revenueSheet->setCellValueExplicit('A'.$row, (string) $label, DataType::TYPE_STRING);
            $revenueSheet->setCellValue('B'.$row, $chart['revenue'][$index] ?? 0);
            $revenueSheet->setCellValue('C'.$row, $chart['orders'][$index] ?? 0);
        }

        $composition = $this->reportsService->getServiceComposition($filters);
        $servicesSheet = $spreadsheet->createSheet();
        $servicesSheet->setTitle('Dịch vụ');
        $servicesSheet->fromArray([['Nhóm dịch vụ', 'Doanh thu (VNĐ)', 'Số dòng dịch vụ', 'Tỷ trọng (%)']], null, 'A1');
        foreach ($composition['labels'] as $index => $label) {
            $row = $index + 2;
            $servicesSheet->setCellValueExplicit('A'.$row, (string) $label, DataType::TYPE_STRING);
            $servicesSheet->setCellValue('B'.$row, $composition['revenue'][$index] ?? 0);
            $servicesSheet->setCellValue('C'.$row, $composition['counts'][$index] ?? 0);
            $servicesSheet->setCellValue('D'.$row, $composition['percentages'][$index] ?? 0);
        }

        $topServicesSheet = $spreadsheet->createSheet();
        $topServicesSheet->setTitle('Top dịch vụ');
        $topServicesSheet->fromArray([['Dịch vụ', 'Đơn vị', 'Số lượng', 'Doanh thu (VNĐ)']], null, 'A1');
        foreach ($this->reportsService->getTopServices($filters, 5) as $index => $service) {
            $row = $index + 2;
            $topServicesSheet->setCellValueExplicit('A'.$row, (string) $service->name, DataType::TYPE_STRING);
            $topServicesSheet->setCellValueExplicit('B'.$row, (string) $service->unit, DataType::TYPE_STRING);
            $topServicesSheet->setCellValue('C'.$row, $service->total_qty);
            $topServicesSheet->setCellValue('D'.$row, $service->total_revenue);
        }

        $paymentsSheet = $spreadsheet->createSheet();
        $paymentsSheet->setTitle('Phương thức thanh toán');
        $paymentsSheet->fromArray([['Phương thức', 'Doanh thu (VNĐ)', 'Số giao dịch']], null, 'A1');
        foreach ($this->reportsService->getRevenueByPaymentMethod($filters) as $index => $payment) {
            $row = $index + 2;
            $paymentsSheet->setCellValueExplicit('A'.$row, (string) $payment->method, DataType::TYPE_STRING);
            $paymentsSheet->setCellValue('B'.$row, $payment->total_amount);
            $paymentsSheet->setCellValue('C'.$row, $payment->transaction_count);
        }

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheet->getStyle('A1:Z1')->getFont()->setBold(true);
            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheet->freezePane('A2');
        }

        $fileName = 'Bao_Cao_'.str_replace('_', '-', $filter).'_'.now()->format('Y_m_d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
