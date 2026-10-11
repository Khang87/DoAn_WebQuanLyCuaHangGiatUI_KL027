<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentHistoryController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'method' => ['nullable', Rule::in(['cash', 'bank_transfer'])],
            'status' => ['nullable', Rule::in(PaymentStatus::values())],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'collector_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'order_id' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'sort' => ['nullable', Rule::in(['latest', 'oldest', 'amount_desc', 'amount_asc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ], ['date_to.after_or_equal' => 'Đến ngày phải bằng hoặc sau Từ ngày.']);

        $payments = $this->payments->getAll($filters);
        $payments->getCollection()->load(['collectionAudit.taiKhoan.nhanVien', 'collectionAudit.taiKhoan.khachHang']);

        return view('admin.payment-history.index', [
            'payments' => $payments, 'totals' => $this->payments->historyTotals($filters),
            'collectors' => $this->payments->historyCollectors(),
        ]);
    }

    public function show(int $payment): View
    {
        $record = $this->payments->find($payment);
        abort_if($record === null, 404);

        $record->load(['collectionAudit.taiKhoan.nhanVien', 'collectionAudit.taiKhoan.khachHang']);

        return view('admin.payment-history.show', ['payment' => $record]);
    }
}
