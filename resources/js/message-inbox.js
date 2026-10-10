// Inbox metadata stays behind Laravel session permissions; no message content here.
const inbox = document.querySelector('[data-message-inbox]');
if (inbox) {
    let timer, controller, pending = false, stopped = false, disposed = false, retry = 3000;
    let signature;
    function schedule() {
        clearTimeout(timer);
        if (!stopped && !disposed && !document.hidden && navigator.onLine) timer = setTimeout(sync, retry);
    }
    function render(orders) {
        const next = JSON.stringify(orders);
        if (signature === next) return;
        const fragment = document.createDocumentFragment();
        for (const order of orders) {
            const row = document.createElement('a');
            const url = new URL(inbox.dataset.messageLink, location.origin);
            url.searchParams.set('order_id', order.id);
            row.href = url.href;
            row.dataset.inboxOrder = String(order.id);
            row.className = 'list-group-item list-group-item-action';
            if (String(order.id) === inbox.dataset.selectedOrder) row.classList.add('active');
            const heading = document.createElement('div');
            heading.className = 'd-flex justify-content-between gap-2';
            const title = document.createElement('span');
            title.className = 'fw-semibold';
            title.textContent = order.number;
            if (order.unread) {
                const dot = document.createElement('span');
                dot.className = 'd-inline-block rounded-circle bg-danger ms-2';
                dot.style.cssText = 'width:10px;height:10px';
                dot.dataset.unreadDot = '';
                dot.setAttribute('role', 'img');
                dot.setAttribute('aria-label', 'Tin nhắn chưa đọc');
                title.append(dot);
            }
            const date = document.createElement('small');
            date.textContent = order.date ?? '';
            heading.append(title, date);
            const customer = document.createElement('small');
            customer.textContent = order.customer;
            row.append(heading, customer);
            fragment.append(row);
        }
        if (!orders.length) {
            const empty = document.createElement('div');
            empty.className = 'text-center text-muted p-4';
            empty.textContent = 'Chưa có đơn hàng.';
            fragment.append(empty);
        }
        inbox.replaceChildren(fragment);
        signature = next;
    }
    async function sync() {
        if (pending || stopped || disposed || document.hidden || !navigator.onLine) return;
        pending = true;
        clearTimeout(timer);
        controller = new AbortController();
        const deadline = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch(inbox.dataset.messageInbox, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            if ([401, 403, 404].includes(response.status)) {
                stopped = true;
                inbox.replaceChildren();
                return;
            }
            if (!response.ok) throw new Error('Inbox sync failed');
            const data = await response.json();
            if (!Array.isArray(data.orders) || data.orders.length > 20 || data.orders.some(order =>
                !Number.isSafeInteger(order.id) || order.id < 1 || typeof order.number !== 'string'
                || typeof order.customer !== 'string' || typeof order.unread !== 'boolean'
                || (order.date !== null && typeof order.date !== 'string'))) throw new Error('Invalid inbox response');
            if (document.hidden || disposed) return;
            render(data.orders);
            retry = 3000;
        } catch {
            retry = Math.min(retry * 2, 120000);
        } finally {
            clearTimeout(deadline);
            pending = false;
            controller = null;
            schedule();
        }
    }
    function resume() {
        clearTimeout(timer);
        if (document.hidden || !navigator.onLine) controller?.abort();
        else { retry = 3000; void sync(); }
    }
    document.addEventListener('visibilitychange', resume);
    window.addEventListener('online', resume);
    window.addEventListener('offline', resume);
    window.addEventListener('message-inbox-changed', () => { void sync(); });
    window.addEventListener('pagehide', () => { disposed = true; clearTimeout(timer); controller?.abort(); });
    window.addEventListener('pageshow', event => { if (event.persisted) { disposed = false; resume(); } });
    void sync();
}
