<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    // Payment method Vietnamese translations
    private const PAYMENT_METHOD_LABELS = [
        'cash' => 'Tiền mặt',
        'bank_transfer' => 'Chuyển khoản',
        'momo' => 'MoMo',
        'credit_card' => 'Thẻ tín dụng',
        'e_wallet' => 'Ví điện tử',
    ];

    public function __construct(
        private ReportsService $reportsService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'range' => $request->input('range', 'this_month'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $kpi = $this->reportsService->getKpiMetrics($filters);
        $chartData = $this->reportsService->getRevenueChartData($filters);
        $compositionData = $this->reportsService->getServiceComposition($filters);
        $topServices = $this->reportsService->getTopServices($filters, 5);
        $paymentMethods = $this->reportsService->getRevenueByPaymentMethod($filters);
        $recentOrders = $this->reportsService->getRecentOrders($filters);

        // Chart colors for consistent UI
        $chartColors = [
            '#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6',
            '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'
        ];

        // Payment method badge colors
        $paymentMethodColors = [
            'cash' => 'bg-success-subtle text-success-emphasis border border-success',
            'bank_transfer' => 'bg-primary-subtle text-primary-emphasis border border-primary',
            'momo' => 'bg-pink-subtle text-pink-emphasis border border-pink',
            'credit_card' => 'bg-indigo-subtle text-indigo-emphasis border border-indigo',
            'e_wallet' => 'bg-teal-subtle text-teal-emphasis border border-teal',
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
}