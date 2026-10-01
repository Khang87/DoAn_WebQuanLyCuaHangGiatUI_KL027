<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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

        $filters = $this->validatedFilters($request);

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
            'paymentMethodLabels'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('reports.view');

        $filters = $this->validatedFilters($request);
        $filename = 'bao-cao-doanh-thu-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($filters): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Bao cao doanh thu');
            $sheet->fromArray(
                ['Mã đơn hàng', 'Khách hàng', 'Trạng thái', 'Ngày lập hóa đơn', 'Doanh thu (VNĐ)'],
                null,
                'A1',
            );
            $sheet->getStyle('A1:E1')->getFont()->setBold(true);
            $sheet->getStyle('A1:E1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE8F0FE');

            $rowNumber = 2;
            $this->reportsService->getExportOrdersQuery($filters)->chunk(500, function ($orders) use ($sheet, &$rowNumber): void {
                foreach ($orders as $order) {
                    $sheet->setCellValueExplicit('A'.$rowNumber, (string) $order->order_code, DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('B'.$rowNumber, (string) ($order->customer_name ?? ''), DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('C'.$rowNumber, (string) $order->order_status, DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit('D'.$rowNumber, (string) $order->invoice_date, DataType::TYPE_STRING);
                    $sheet->setCellValue('E'.$rowNumber, (float) ($order->revenue ?? 0));
                    $rowNumber++;
                }
            });

            $sheet->getStyle('E2:E'.max(2, $rowNumber - 1))
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getAutoFilter()->setRange('A1:E'.max(1, $rowNumber - 1));
            foreach (range('A', 'E') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{range: string, date_from?: string, date_to?: string}
     */
    private function validatedFilters(Request $request): array
    {
        $rangeAliases = [
            'Hôm nay' => 'today',
            'Tháng này' => 'this_month',
            'Tháng trước' => 'last_month',
            'Toàn thời gian' => 'all_time',
            'Từ ngày - Đến ngày' => 'custom',
        ];
        $requestedRange = $request->input('filter_type', $request->input('range', 'all_time'));
        $range = is_string($requestedRange)
            ? ($rangeAliases[$requestedRange] ?? $requestedRange)
            : $requestedRange;
        $request->merge(['range' => $range]);

        return $request->validate([
            'range' => ['sometimes', 'string', 'in:today,7_days,this_month,last_month,all_time,custom'],
            'date_from' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
    }
}
