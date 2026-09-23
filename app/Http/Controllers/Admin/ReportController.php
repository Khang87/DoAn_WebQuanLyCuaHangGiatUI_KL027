<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.index');
    }

    public function revenue()
    {
        return view('admin.reports.revenue');
    }

    public function orders()
    {
        return view('admin.reports.orders');
    }

    public function customers()
    {
        return view('admin.reports.customers');
    }
}
