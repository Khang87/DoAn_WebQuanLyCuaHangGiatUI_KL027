<?php

namespace App\Http\Requests\Admin;

use App\Models\DonViTinh;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class HoanTatTiepNhanRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        if (! is_array($items)) {
            return;
        }

        foreach ($items as &$item) {
            if (is_array($item) && isset($item['KhoiLuong']) && is_numeric($item['KhoiLuong'])) {
                $item['KhoiLuong'] = round((float) $item['KhoiLuong'], 2);
            }
        }
        unset($item);

        $this->merge(['items' => $items]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.ChiTietDonHangID' => ['nullable', 'integer'],
            'items.*.DichVuID' => ['required', 'integer', 'exists:DichVu,DichVuID'],
            'items.*.LoaiDoGiatID' => ['required', 'integer', 'exists:LoaiDoGiat,LoaiDoGiatID'],
            'items.*.DonViTinhID' => ['required', 'integer', 'exists:DonViTinh,DonViTinhID'],
            'items.*.SoLuong' => ['nullable', 'numeric', 'min:1'],
            'items.*.KhoiLuong' => ['nullable', 'numeric', 'gt:0'],
            'items.*.GhiChu' => ['required', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                if (! is_array($item) || $validator->errors()->has("items.{$index}.DonViTinhID")) {
                    continue;
                }

                $unit = DonViTinh::query()->find($item['DonViTinhID'] ?? null);
                if (! $unit) {
                    continue;
                }

                $hasQuantity = isset($item['SoLuong']) && $item['SoLuong'] !== '';
                $hasWeight = isset($item['KhoiLuong']) && $item['KhoiLuong'] !== '';
                $validInput = $unit->isWeightUnit()
                    ? $hasWeight && is_numeric($item['KhoiLuong']) && (float) $item['KhoiLuong'] > 0 && ! $hasQuantity
                    : $hasQuantity
                        && is_numeric($item['SoLuong'])
                        && (float) $item['SoLuong'] >= 1
                        && floor((float) $item['SoLuong']) === (float) $item['SoLuong']
                        && (! $hasWeight || (is_numeric($item['KhoiLuong']) && (float) $item['KhoiLuong'] === 0.0));

                if (! $validInput) {
                    $validator->errors()->add(
                        "items.{$index}.".($unit->isWeightUnit() ? 'KhoiLuong' : 'SoLuong'),
                        $unit->isWeightUnit()
                            ? 'Đơn vị tính theo kg cần khối lượng lớn hơn 0 và không nhập số lượng món.'
                            : 'Đơn vị tính theo món cần số lượng nguyên dương và không nhập khối lượng.',
                    );
                }
            }
        });
    }
}
