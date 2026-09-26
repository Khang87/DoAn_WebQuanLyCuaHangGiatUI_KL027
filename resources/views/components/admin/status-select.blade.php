@props([
    'name' => 'status',
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'id' => null,
    'class' => 'form-select',
    'required' => false,
    'submit' => false,
])

{{--
    Dropdown trạng thái dùng chung cho CẢ 3 vị trí:
      1. Form tạo / chỉnh sửa (truyền $options = Enum::options())
      2. Dropdown lọc ngoài bảng (truyền $placeholder + $submit = true)

    $options luôn lấy từ enum nên không thể lệch với badge trong bảng.
    Thứ tự ưu tiên giá trị đang chọn: query string -> old() -> $selected.
--}}
@php
    $current = request()->filled($name) ? request($name) : old($name, $selected);
@endphp
<select
    name="{{ $name }}"
    @if($id) id="{{ $id }}" @endif
    class="{{ $class }}"
    @if($required) required @endif
    @if($submit) onchange="this.form.submit()" @endif
    {{ $attributes }}
>
    @if($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach($options as $value => $label)
        <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
