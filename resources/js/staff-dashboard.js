const orderTable = document.querySelector('[data-staff-order-status-url]');

if (orderTable) {
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            window.location.reload();
        }
    });

    document.querySelectorAll('.status-select').forEach(select => {
        select.addEventListener('change', async function () {
            const orderId = this.dataset.orderId;
            const newStatus = this.value;

            if (!orderId || !newStatus || this.disabled) {
                return;
            }

            this.disabled = true;

            try {
                const response = await fetch(orderTable.dataset.staffOrderStatusUrl.replace('__ID__', orderId), {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ status: newStatus }),
                });
                const data = await response.json().catch(() => null);

                if (!response.ok || data?.success !== true) {
                    throw new Error(data?.message || 'Có lỗi xảy ra. Vui lòng thử lại.');
                }

                await Swal.fire({
                    icon: 'success',
                    title: 'Đã cập nhật!',
                    text: 'Đơn hàng đã chuyển sang: ' + data.status_label,
                    timer: 1500,
                    showConfirmButton: false,
                });

                window.location.reload();
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Lỗi!',
                    text: error instanceof Error ? error.message : 'Có lỗi xảy ra. Vui lòng thử lại.',
                    timer: 2000,
                    showConfirmButton: false,
                });

                this.selectedIndex = 0;
                this.disabled = false;
            }
        });
    });
}
