<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentStatus;
use App\Models\DonHang;
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
        return [
            'order_id' => ['nullable', 'required_without:invoice_id', 'exists:DonHang,DonHangID'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer'],
            'invoice_id' => ['nullable', 'required_without:order_id', 'integer', 'exists:HoaDon,HoaDonID'],
            'paid_at' => ['nullable', 'date'],
            'status' => ['required', 'in:'.implode(',', PaymentStatus::values())],
            'transaction_code' => ['nullable', 'string', 'max:100', Rule::unique('ThanhToan', 'MaGiaoDich')],
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
            $orderId = $this->input('order_id');
            $amount = $this->input('amount');

            if (! is_numeric($orderId) || ! is_numeric($amount)) {
                return;
            }

            $order = DonHang::with('hoaDons')->find($orderId);
            if (! $order) {
                return;
            }

            $grandTotal = (float) ($order->hoaDons->first()?->ThanhTien ?? $order->ThanhTien);
            $paidTotal = (float) $order->thanhToans()
                ->where('TrangThai', PaymentStatus::Paid->value)
                ->sum('SoTien');
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
