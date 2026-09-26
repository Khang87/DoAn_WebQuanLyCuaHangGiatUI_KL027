@props([
    'name' => 'role',
    'selected' => null,
    'label' => 'Vai trò',
    'required' => true,
])

{{--
    Dropdown chọn vai trò, lấy danh sách trực tiếp từ bảng `roles` nên không
    thể lệch với ma trận phân quyền. Cột `role` của users lưu theo slug.
--}}
@php
    $roles = \App\Models\Role::query()->orderByRaw("CASE slug WHEN 'owner' THEN 0 WHEN 'manager' THEN 1 WHEN 'staff' THEN 2 ELSE 3 END")->get();
    $current = old($name, $selected);
    $current = $current === 'admin' ? \App\Models\Role::OWNER_SLUG : $current;
@endphp

<label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
<select class="form-select @error($name) is-invalid @enderror" name="{{ $name }}" @if($required) required @endif>
    @foreach($roles as $role)
        <option value="{{ $role->slug }}" @selected($current === $role->slug)>{{ $role->name }}</option>
    @endforeach
</select>
@error($name)
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
@if($selected !== null || old($name))
    @php $role = $roles->firstWhere('slug', $current); @endphp
    @if($role?->description)
        <div class="form-text">{{ $role->description }}</div>
    @endif
@endif
