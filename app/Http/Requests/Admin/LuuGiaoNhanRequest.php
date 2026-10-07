<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryStatus;
use App\Models\GiaoNhan;
use App\Services\DeliveryRules;
use App\Services\EmployeeAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class LuuGiaoNhanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['fulfillment' => $this->input('fulfillment', 'Tại nhà')]);
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:DonHang,DonHangID'],
            'employee_id' => ['nullable', 'integer', EmployeeAssignment::rule($this->currentDelivery()?->NhanVienID)],
            'method' => ['required', 'in:nhan_do,giao_do'],
            'fulfillment' => ['sometimes', 'in:Tại cửa hàng,Tại nhà'],
            'address' => ['nullable', 'required_if:fulfillment,Tại nhà', 'string', 'max:255'],
            'pickup_date' => ['nullable', 'required_if:method,nhan_do', 'required_if:status,picking,delivering,completed', 'required_with:pickup_time', 'date_format:Y-m-d'],
            'pickup_time' => ['nullable', 'required_if:method,nhan_do', 'required_if:status,picking,delivering,completed', 'required_with:pickup_date', 'date_format:H:i'],
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

            try {
                DeliveryRules::validateSchedule($validator->getData(), $this->currentDelivery());
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $validator->errors()->add($field, $messages[0]);
                }
            }
        });
    }

    private function currentDelivery(): ?GiaoNhan
    {
        if ($this->isMethod('POST')) {
            return null;
        }
        $delivery = $this->route('delivery');

        return $delivery instanceof GiaoNhan ? $delivery : ($delivery ? GiaoNhan::find($delivery) : null);
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Đơn hàng là bắt buộc để tạo giao nhận.',
            'order_id.exists' => 'Đơn hàng không tồn tại.',
            'employee_id.exists' => 'Vui lòng chọn nhân viên đang hoạt động.',
            'method.required' => 'Loại giao nhận là bắt buộc.',
            'method.in' => 'Loại giao nhận không hợp lệ.',
            'address.required_if' => 'Địa chỉ giao nhận là bắt buộc khi thực hiện tại nhà.',
            'fulfillment.in' => 'Hình thức phải là Tại cửa hàng hoặc Tại nhà.',
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
