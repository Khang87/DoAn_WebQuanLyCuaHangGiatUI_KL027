@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const discountType = document.getElementById('LoaiKhuyenMai');
        const maximumDiscount = document.getElementById('MucGiamToiDa');
        const clearMaximumDiscount = document.querySelector('[data-clear-max-discount]');

        if (!discountType || !maximumDiscount || !clearMaximumDiscount) {
            return;
        }

        const updateMaximumDiscountState = function () {
            const isFixedDiscount = discountType.value === @json(\App\Models\KhuyenMai::DISCOUNT_FIXED);

            maximumDiscount.disabled = isFixedDiscount;
            maximumDiscount.classList.toggle('bg-light', isFixedDiscount);
            clearMaximumDiscount.disabled = !isFixedDiscount;
        };

        discountType.addEventListener('change', updateMaximumDiscountState);
        updateMaximumDiscountState();
    });
</script>
@endpush
