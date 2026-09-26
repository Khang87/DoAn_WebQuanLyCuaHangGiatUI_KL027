<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('review') ?? $this->route('id') ?? null;

        return [
            'order_id' => ['required', 'exists:orders,id', 'unique:reviews,order_id,' . ($id ?? '')],
            'customer_id' => ['required', 'exists:customers,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['nullable', 'string', 'max:2000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:500'],
            'shop_response' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:visible,hidden'],
            'reviewed_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Đơn hàng là bắt buộc.',
            'order_id.exists' => 'Đơn hàng không tồn tại.',
            'order_id.unique' => 'Đơn hàng này đã được đánh giá.',
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'rating.required' => 'Đánh giá sao là bắt buộc.',
            'rating.integer' => 'Đánh giá sao phải là số nguyên.',
            'rating.between' => 'Đánh giá sao phải từ 1 đến 5.',
            'content.max' => 'Nội dung không quá 2000 ký tự.',
            'images.array' => 'Hình ảnh phải là mảng.',
            'images.*.string' => 'Mỗi hình ảnh phải là chuỗi.',
            'images.*.max' => 'Đường dẫn hình ảnh không quá 500 ký tự.',
            'shop_response.max' => 'Phản hồi cửa hàng không quá 2000 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}