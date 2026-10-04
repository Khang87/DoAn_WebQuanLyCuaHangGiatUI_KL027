@props([
    'name' => 'role',
    'id' => null,
    'selected' => null,
    'label' => 'Vai trò',
    'required' => true,
])

{{--
    Dropdown chọn vai trò, lấy danh sách trực tiếp từ bảng `VaiTro` nên không
    thể lệch với ma trận phân quyền. Cột `role` của users lưu theo slug.
--}}
@php
    $roles = \App\Models\VaiTro::query()
        ->where('TrangThai', 'Hoạt động')
        ->orderBy('VaiTroID')
        ->get();
    $current = old($name, $selected);
    $current = $current === 'admin' ? 'chu-cua-hang' : $current;
@endphp

<label class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
<select @if($id) id="{{ $id }}" @endif class="form-select @error($name) is-invalid @enderror" name="{{ $name }}" @if($required) required @endif>
    @foreach($roles as $role)
        <option value="{{ $role->slug }}" @selected($current === $role->slug)>{{ $role->TenVaiTro }}</option>
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
