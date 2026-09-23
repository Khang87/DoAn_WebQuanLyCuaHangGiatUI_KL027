<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $reportService,
    ) {}

    public function index()
    {
        $summary = $this->reportService->getRevenueSummary();
        $monthlyRevenue = $this->reportService->getMonthlyRevenue();
        $orderStatusCounts = $this->reportService->getOrderStatusCounts();
        $topCustomers = $this->reportService->getTopCustomers(10);

        return view('admin.reports.index', compact('summary', 'monthlyRevenue', 'orderStatusCounts', 'topCustomers'));
    }

    public function revenue()
    {
        $revenueData = $this->reportService->getRevenueData('month');
        $summary = $this->reportService->getRevenueSummary();

        return view('admin.reports.revenue', compact('revenueData', 'summary'));
    }

    public function orders()
    {
        $orderStatusCounts = $this->reportService->getOrderStatusCounts();
        $serviceCounts = $this->reportService->getOrderCountsByService();
        $services = \App\Models\Service::orderBy('name')->get();

        return view('admin.reports.orders', compact('orderStatusCounts', 'serviceCounts', 'services'));
    }

    public function customers()
    {
        $topCustomers = $this->reportService->getTopCustomers(20);

        return view('admin.reports.customers', compact('topCustomers'));
    }
}
