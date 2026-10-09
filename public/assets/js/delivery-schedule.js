(function () {
    const method = document.getElementById('method');
    const status = document.getElementById('status');
    const date = document.getElementById('pickup_date');
    const time = document.getElementById('pickup_time');
    const fulfillment = document.getElementById('fulfillment');
    const address = document.getElementById('address');
    if (!method || !status || !date || !time) return;
    function update() {
        const executing = ['picking', 'delivering', 'completed'].includes(status.value);
        const required = method.value === 'nhan_do' || executing;
        date.required = required || Boolean(time.value);
        time.required = required || Boolean(date.value);
        address.required = fulfillment.value === 'Tại nhà';
        address.disabled = !address.required;
        address.classList.toggle('bg-light', address.disabled);
        address.classList.toggle('text-muted', address.disabled);
        document.getElementById('schedule-help').textContent = required
            ? 'Chặng nhận đồ hoặc chặng đang thực hiện cần đủ ngày và giờ.'
            : 'Phiếu trả đang chờ có thể chưa đặt lịch. Khi đặt lịch, hãy nhập đủ ngày và giờ.';
    }
    [method, status, date, time, fulfillment].forEach(control => control.addEventListener('change', update));
    fulfillment.form.addEventListener('reset', () => setTimeout(update, 0));
    update();
})();
