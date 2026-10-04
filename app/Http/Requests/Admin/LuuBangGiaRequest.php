<?php

namespace App\Http\Requests\Admin;

use App\Services\PricingService;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuBangGiaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $dateInputs = [];

        foreach (['NgayApDung', 'NgayKetThuc'] as $field) {
            $displayField = "{$field}_display";

            if ($this->exists($displayField)) {
                $dateInputs[$field] = $this->normalizeDate($this->input($displayField));
            }
        }

        if ($dateInputs !== []) {
            $this->merge($dateInputs);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    private function normalizeDate(mixed $value): mixed
    {
        if (! is_string($value) || ! preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
            return $value;
        }

        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('d-m-Y') !== $value
        ) {
            return $value;
        }

        return $date->format('Y-m-d');
    }

    public function rules(): array
    {
        return [
            'DichVuID' => ['required', 'integer', 'exists:DichVu,DichVuID'],
            'LoaiDoGiatID' => ['required', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'DonViTinhID' => ['required', 'integer', 'exists:DonViTinh,DonViTinhID'],
            'DonGia' => ['required', 'numeric', 'min:0'],
            'NgayApDung' => [
                'required',
                'date',
                'after_or_equal:today',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $endDate = $this->input('NgayKetThuc');

                    if (
                        ! is_string($value)
                        || strtotime($value) === false
                        || ($endDate !== null && $endDate !== '' && (! is_string($endDate) || strtotime($endDate) === false))
                    ) {
                        return;
                    }

                    $serviceId = $this->input('DichVuID');
                    $garmentId = $this->input('LoaiDoGiatID');
                    $unitId = $this->input('DonViTinhID');

                    if (! is_numeric($serviceId) || ! is_numeric($garmentId) || ! is_numeric($unitId)) {
                        return;
                    }

                    $pricingId = $this->route('pricing') ?? $this->route('id');

                    if (app(PricingService::class)->hasOverlappingPeriod(
                        (int) $serviceId,
                        (int) $garmentId,
                        (int) $unitId,
                        $value,
                        $endDate ?: null,
                        is_numeric($pricingId) ? (int) $pricingId : null,
                    )) {
                        $fail('Khoảng thời gian bảng giá bị chồng lấn với một bản giá khác của cùng dịch vụ, loại đồ giặt và đơn vị tính.');
                    }
                },
            ],
            'NgayKetThuc' => ['nullable', 'date', 'after_or_equal:NgayApDung'],
            'TrangThai' => [
                'required',
                Rule::in(['Hoạt động', 'Hết hiệu lực', 'Tạm ngưng']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'NgayApDung.after_or_equal' => 'Ngày áp dụng không được trước ngày hiện tại.',
            'NgayKetThuc.after_or_equal' => 'Ngày kết thúc phải bằng hoặc sau ngày áp dụng.',
        ];
    }
}
