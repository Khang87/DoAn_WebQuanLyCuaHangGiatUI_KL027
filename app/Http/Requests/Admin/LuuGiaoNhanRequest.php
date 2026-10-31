<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeliveryStatus;
use App\Models\GiaoNhan;
use App\Models\NhanVien;
use App\Services\DeliveryRules;
use App\Services\EmployeeAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class LuuGiaoNhanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || $user->isManager()) {
            return true;
        }

        return $user->nhanVien?->TrangThai === 'Hoạt động'
            && ($this->currentDelivery() === null
                || (int) $this->currentDelivery()->NhanVienID === (int) $user->NhanVienID);
    }

    protected function prepareForValidation(): void
    {
        $delivery = $this->currentDelivery();

        if ($delivery) {
            $this->merge([
                'order_id' => $delivery->DonHangID,
                'method' => strtolower($delivery->LoaiGiaoNhan),
                'fulfillment' => $delivery->HinhThuc,
            ]);
        } else {
            $this->merge(['fulfillment' => $this->input('fulfillment', 'Tại nhà')]);
        }

        if ($this->user() && ! $this->user()->isManager() && ! $this->has('employee_id')) {
            $this->merge(['employee_id' => $this->user()->NhanVienID]);
        }
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:DonHang,DonHangID'],
            'employee_id' => [
                'required', 'integer', EmployeeAssignment::rule($this->currentDelivery()?->NhanVienID),
                ...($this->user() && ! $this->user()->isManager() ? [Rule::in([(int) $this->user()->NhanVienID])] : []),
            ],
            'employee_search' => ['sometimes', 'string', 'max:255'],
            'method' => ['required', 'in:nhan_do,giao_do'],
            'fulfillment' => ['sometimes', 'in:Tại cửa hàng,Tại nhà'],
            'address' => ['nullable', 'required_if:fulfillment,Tại nhà', 'string', 'max:255'],
            'pickup_date' => ['nullable', Rule::requiredIf(
                $this->input('method') === 'nhan_do'
                || ($this->input('fulfillment') === 'Tại nhà' && in_array($this->input('status'), ['delivering', 'completed'], true))
            ), 'required_with:pickup_time', 'date_format:Y-m-d'],
            'pickup_time' => ['nullable', Rule::requiredIf(
                $this->input('method') === 'nhan_do'
                || ($this->input('fulfillment') === 'Tại nhà' && in_array($this->input('status'), ['delivering', 'completed'], true))
            ), 'required_with:pickup_date', 'date_format:H:i'],
            'status' => ['nullable', 'in:'.implode(',', DeliveryStatus::values())],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $employeeId = $this->input('employee_id');
            if (is_numeric($employeeId) && ! $validator->errors()->has('employee_id')) {
                $employee = NhanVien::query()->whereKey((int) $employeeId)->first();
                $isAssignableEmployee = EmployeeAssignment::deliveryCandidates()
                    ->whereKey((int) $employeeId)->exists();

                if ($this->user()?->isManager() && ! $isAssignableEmployee) {
                    $validator->errors()->add('employee_id', 'Chỉ được phân công nhân viên có tài khoản nội bộ được cấp quyền xử lý giao nhận.');
                }

                if ($this->exists('employee_search')
                    && trim((string) $this->input('employee_search')) !== trim((string) $employee?->HoTen)) {
                    $validator->errors()->add('employee_id', 'Vui lòng chọn nhân viên từ danh sách gợi ý; không thể chỉ nhập tên.');
                }
            }

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
            'employee_id.required' => 'Vui lòng chọn nhân viên phụ trách.',
            'employee_id.integer' => 'Nhân viên phụ trách không hợp lệ.',
            'employee_id.exists' => 'Vui lòng chọn nhân viên đang hoạt động.',
            'employee_id.in' => 'Bạn chỉ được phân công chính mình.',
            'method.required' => 'Loại giao nhận là bắt buộc.',
            'method.in' => 'Loại giao nhận không hợp lệ.',
            'address.required_if' => 'Địa chỉ giao nhận là bắt buộc khi thực hiện tại nhà.',
            'fulfillment.in' => 'Hình thức phải là Tại cửa hàng hoặc Tại nhà.',
            'address.max' => 'Địa chỉ không được vượt quá 255 ký tự.',
            'pickup_date.required' => 'Vui lòng chọn ngày giao nhận trước khi tiếp tục.',
            'pickup_date.required_if' => 'Vui lòng chọn ngày giao nhận trước khi tiếp tục.',
            'pickup_date.required_with' => 'Vui lòng chọn ngày giao nhận.',
            'pickup_date.date_format' => 'Ngày giao nhận không hợp lệ.',
            'pickup_time.required' => 'Vui lòng chọn giờ giao nhận trước khi tiếp tục.',
            'pickup_time.required_if' => 'Vui lòng chọn giờ giao nhận trước khi tiếp tục.',
            'pickup_time.required_with' => 'Vui lòng chọn giờ giao nhận.',
            'pickup_time.date_format' => 'Định dạng giờ phải là HH:MM.',
            'pickup_time.after' => 'Thời gian giao nhận phải sau thời điểm hiện tại.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'notes.max' => 'Ghi chú không được vượt quá 500 ký tự.',
        ];
    }
}
