<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentStatus;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\ThanhToan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LuuThanhToanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $payment = $this->route('payment');
        $paymentId = $payment instanceof ThanhToan
            ? $payment->getKey()
            : (is_numeric($payment) ? (int) $payment : null);
        $transactionCodeRule = Rule::unique('ThanhToan', 'MaGiaoDich');

        if ($paymentId !== null) {
            $transactionCodeRule->ignore($paymentId, 'ThanhToanID');
        }

        return [
            'order_id' => ['nullable', 'required_without:invoice_id', 'exists:DonHang,DonHangID'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer'],
            'invoice_id' => ['nullable', 'required_without:order_id', 'integer', 'exists:HoaDon,HoaDonID'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['required', 'in:'.implode(',', PaymentStatus::values())],
            'transaction_code' => ['nullable', 'string', 'max:100', $transactionCodeRule],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_code.unique' => 'Mã giao dịch đã được sử dụng.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $amount = $this->input('amount');

            if (! is_numeric($amount)) {
                return;
            }

            $invoice = is_numeric($this->input('invoice_id'))
                ? HoaDon::find($this->input('invoice_id'))
                : null;
            $orderId = $this->input('order_id') ?: $invoice?->DonHangID;
            $order = is_numeric($orderId) ? DonHang::with('hoaDons')->find($orderId) : null;

            if (! $order) {
                return;
            }

            $grandTotal = (float) ($invoice?->ThanhTien ?? $order->hoaDons->first()?->ThanhTien ?? $order->ThanhTien);
            $payment = $this->route('payment');
            $paymentId = $payment instanceof ThanhToan
                ? $payment->getKey()
                : (is_numeric($payment) ? (int) $payment : null);
            $paidQuery = $order->thanhToans()->where('TrangThai', PaymentStatus::Paid->value);

            if ($paymentId !== null) {
                $paidQuery->where('ThanhToanID', '!=', $paymentId);
            }

            $paidTotal = (float) $paidQuery->sum('SoTien');
            $remaining = max(0, $grandTotal - $paidTotal);

            if ((float) $amount > $remaining) {
                $validator->errors()->add(
                    'amount',
                    'Số tiền thanh toán không được vượt quá số tiền còn phải thu ('.number_format($remaining).' đ).',
                );
            }
        });
    }
}
