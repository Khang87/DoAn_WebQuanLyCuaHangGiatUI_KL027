<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportsService;
use App\Support\TimePeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

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
}
