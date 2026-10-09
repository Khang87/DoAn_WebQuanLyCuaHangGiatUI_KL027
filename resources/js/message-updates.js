// Read through Laravel's session/ACL; no Supabase credential is needed here.
const conversation = document.querySelector('[data-message-updates]');

if (conversation) {
    const status = document.querySelector('[data-message-status]');
    const form = document.querySelector('[data-message-form]');
    const orderId = conversation.dataset.orderId ? Number(conversation.dataset.orderId) : null;
    const customerId = conversation.dataset.customerAccountId ? Number(conversation.dataset.customerAccountId) : null;
    const interval = 15000;
    let timer, controller, signature, pending = false, stopped = false, disposed = false, retry = interval;

    function showStatus(text) {
        status.textContent = text;
    }

    function render(messages) {
        const nextSignature = JSON.stringify(messages);
        if (signature === nextSignature) return;
        const atBottom = conversation.scrollHeight - conversation.scrollTop - conversation.clientHeight < 48;
        const anchor = [...conversation.children].find(row => row.offsetTop >= conversation.scrollTop);
        const anchorId = anchor?.dataset.messageId;
        const anchorOffset = anchor ? anchor.offsetTop - conversation.scrollTop : 0;
        const previousTop = conversation.scrollTop;
        const fragment = document.createDocumentFragment();
        for (const message of messages) {
            const row = document.createElement('div');
            row.dataset.messageId = String(message.id);
            row.className = `d-flex ${message.is_mine ? 'justify-content-end' : 'justify-content-start'} mb-3`;
            const bubble = document.createElement('div');
            bubble.className = `rounded-3 px-3 py-2 ${message.is_mine ? 'bg-primary text-white' : 'bg-white border'}`;
            bubble.style.maxWidth = '82%';
            for (const [text, className] of [
                [message.sender_name, `small ${message.is_mine ? 'text-white-50' : 'text-muted'}`],
                [message.content, 'text-break'],
                [message.sent_at ?? '', `small text-end ${message.is_mine ? 'text-white-50' : 'text-muted'}`],
            ]) {
                const element = document.createElement('div');
                element.className = className;
                element.textContent = text;
                bubble.append(element);
            }
            row.append(bubble);
            fragment.append(row);
        }
        if (messages.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'h-100 d-flex align-items-center justify-content-center text-muted';
            empty.textContent = 'Chưa có tin nhắn. Hãy gửi lời chào tới khách hàng.';
            fragment.append(empty);
        }
        conversation.replaceChildren(fragment);
        if (atBottom) {
            conversation.scrollTop = conversation.scrollHeight;
        } else {
            const restoredAnchor = [...conversation.children].find(row => row.dataset.messageId === anchorId);
            conversation.scrollTop = restoredAnchor ? restoredAnchor.offsetTop - anchorOffset : previousTop;
        }
        signature = nextSignature;
    }

    function schedule() {
        clearTimeout(timer);
        if (!stopped && !disposed && !document.hidden && navigator.onLine) timer = setTimeout(sync, retry);
    }

    async function sync() {
        if (pending || stopped || disposed || document.hidden || !navigator.onLine) return;
        pending = true;
        clearTimeout(timer);
        controller = new AbortController();
        const deadline = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch(conversation.dataset.messageUpdates, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin',
                cache: 'no-store', signal: controller.signal,
            });
            if ([401, 403, 404].includes(response.status)) {
                stopped = true;
                conversation.replaceChildren();
                if (form) form.dataset.accessLost = 'true';
                form?.querySelectorAll('textarea, button').forEach(input => { input.disabled = true; });
                showStatus(response.status === 401 ? 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.' : 'Cuộc trò chuyện không còn khả dụng.');
                return;
            }
            if (!response.ok) throw new Error('Message sync failed');
            const data = await response.json();
            if (data.order_id !== orderId || (customerId !== null && data.customer_account_id !== customerId) || !Array.isArray(data.messages) || data.messages.length > 100
                || data.messages.some(message => !Number.isSafeInteger(message.id) || message.id < 1
                    || typeof message.content !== 'string' || typeof message.sender_name !== 'string'
                    || typeof message.is_mine !== 'boolean' || (message.sent_at !== null && typeof message.sent_at !== 'string'))) {
                throw new Error('Invalid conversation response');
            }
            render(data.messages);
            retry = interval;
            showStatus('Tự động cập nhật tin nhắn');
        } catch {
            if (!document.hidden && !stopped) showStatus('Kết nối gián đoạn. Đang thử lại…');
            retry = Math.min(retry * 2, 120000);
        } finally {
            clearTimeout(deadline);
            controller = null;
            pending = false;
            schedule();
        }
    }

    function resume() {
        if (stopped) return;
        clearTimeout(timer);
        if (document.hidden || !navigator.onLine) {
            controller?.abort();
            showStatus(document.hidden ? 'Tạm dừng cập nhật' : 'Mất kết nối. Đang chờ kết nối lại…');
        } else {
            retry = interval;
            void sync();
        }
    }

    document.addEventListener('visibilitychange', resume);
    window.addEventListener('online', resume);
    window.addEventListener('offline', resume);
    window.addEventListener('pagehide', () => {
        disposed = true;
        clearTimeout(timer);
        controller?.abort();
    });
    // Browser back/forward cache restores this document without rerunning scripts.
    window.addEventListener('pageshow', event => { if (event.persisted) { disposed = false; resume(); } });
    void sync();
}
