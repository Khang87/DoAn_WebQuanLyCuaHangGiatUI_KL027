<?php

namespace App\Http\Requests\Admin;

use App\Services\PricingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LuuBangGiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
}
