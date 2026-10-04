<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LuuGiaoNhanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:DonHang,DonHangID'],
            'employee_id' => ['nullable', 'integer', 'exists:NhanVien,NhanVienID'],
            'method' => ['required', 'in:nhan_do,giao_do'],
            'address' => ['required', 'string', 'max:255'],
            'pickup_date' => ['required', 'date'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'status' => ['nullable', 'in:'.implode(',', DeliveryStatus::values())],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('pickup_date') || $validator->errors()->has('pickup_time')) {
                return;
            }

            $scheduledAt = Carbon::createFromFormat(
                'Y-m-d H:i',
                $validator->getData()['pickup_date'].' '.$validator->getData()['pickup_time'],
                config('app.timezone'),
            );

            if ($scheduledAt->lessThanOrEqualTo(now())) {
                $validator->errors()->add('pickup_time', 'Thời gian giao nhận phải sau thời điểm hiện tại.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Đơn hàng là bắt buộc để tạo giao nhận.',
            'order_id.exists' => 'Đơn hàng không tồn tại.',
            'employee_id.exists' => 'Nhân viên không tồn tại.',
            'method.required' => 'Loại giao nhận là bắt buộc.',
            'method.in' => 'Loại giao nhận không hợp lệ.',
            'address.required' => 'Địa chỉ giao nhận là bắt buộc.',
            'address.max' => 'Địa chỉ không được vượt quá 255 ký tự.',
            'pickup_date.required' => 'Ngày giao nhận là bắt buộc.',
            'pickup_date.date' => 'Ngày giao nhận không hợp lệ.',
            'pickup_time.required' => 'Giờ giao nhận là bắt buộc.',
            'pickup_time.date_format' => 'Định dạng giờ phải là HH:MM.',
            'pickup_time.after' => 'Thời gian giao nhận phải sau thời điểm hiện tại.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'notes.max' => 'Ghi chú không được vượt quá 500 ký tự.',
        ];
    }
}
