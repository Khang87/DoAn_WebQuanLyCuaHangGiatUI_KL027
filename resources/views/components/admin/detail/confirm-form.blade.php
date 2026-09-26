@props([
    'action',
    'method' => 'DELETE',
    'title',
    'text' => 'Hành động này không thể hoàn tác.',
    'label' => 'Xác nhận',
    'icon' => 'bi-trash',
    'variant' => 'btn-outline-danger',
    'size' => 'btn-sm',
    'block' => false,
    'color' => '#dc2626',
    'iconName' => 'warning',
])

{{--
    Nút hành động có hộp thoại xác nhận, dùng chung cho cả nút trong bảng lẫn
    nút ở trang chi tiết. Trước đây mỗi view tự chép một đoạn script SweetAlert
    riêng nên dễ lệch nhau; giờ toàn bộ logic nằm ở đúng một chỗ.

    :block=true  -> nút chiếm hết bề ngang (dùng trong cột phụ "Thao tác").
--}}
<form action="{{ $action }}" method="POST" data-confirm-form
      data-confirm-title="{{ $title }}"
      data-confirm-text="{{ $text }}"
      data-confirm-label="{{ $label }}"
      data-confirm-color="{{ $color }}"
      data-confirm-icon="{{ $iconName }}"
      class="{{ $block ? 'd-grid' : 'd-inline' }}">
    @csrf
    @if(strtoupper($method) !== 'POST')
        @method(strtoupper($method))
    @endif
    {{ $hidden ?? '' }}
    <button type="submit" class="btn {{ $variant }} {{ $size }} {{ $block ? 'w-100' : '' }}">
        <i class="{{ $icon }} me-1"></i>{{ $slot->isEmpty() ? $label : $slot }}
    </button>
</form>

@once
    @push('scripts')
    <script>
        // Một listener duy nhất cho mọi nút/nhóm nút có data-confirm-form trên
        // trang chi tiết. Dùng event delegation nên không phụ thuộc thứ tự
        // render và vẫn hoạt động với nút thêm sau bằng AJAX.
        document.addEventListener('submit', function (event) {
            var form = event.target.closest('[data-confirm-form]');

            if (!form) return;

            // Bỏ qua lần submit tự nhiên (đã qua confirm rồi).
            if (form.dataset.confirmed === '1') {
                delete form.dataset.confirmed;
                return;
            }

            event.preventDefault();

            var title = form.dataset.confirmTitle || 'Xác nhận?';
            var text = form.dataset.confirmText || '';
            var label = form.dataset.confirmLabel || 'Xác nhận';
            var color = form.dataset.confirmColor || '#dc2626';
            var icon = form.dataset.confirmIcon || 'warning';

            function submitNow() {
                form.dataset.confirmed = '1';
                form.submit();
            }

            if (typeof Swal === 'undefined') {
                if (window.confirm(title + (text ? '\n' + text : ''))) submitNow();
                return;
            }

            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: color,
                cancelButtonColor: '#64748b',
                confirmButtonText: label,
                cancelButtonText: 'Hủy'
            }).then(function (result) {
                if (result.isConfirmed) submitNow();
            });
        });
    </script>
    @endpush
@endonce
