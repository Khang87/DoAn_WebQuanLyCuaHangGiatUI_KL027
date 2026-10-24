const inbox = document.querySelector('[data-message-inbox]');
if (inbox) {
    const rows = inbox.querySelector('[data-inbox-rows]');
    const more = inbox.querySelector('[data-inbox-more]');
    const status = inbox.querySelector('[data-inbox-status]');
    const basePage = Number(inbox.dataset.inboxPage);
    let nextPage = inbox.dataset.nextPage ? Number(inbox.dataset.nextPage) : null;
    let timer, controller, signature, pending = false, stopped = false, disposed = false;
    let hasAppended = false, loadError = false, refreshError = false, retry = 3000;

    function schedule() {
        clearTimeout(timer);
        if (!stopped && !disposed && !document.hidden && navigator.onLine) timer = setTimeout(sync, retry);
    }
    function updateControls() {
        inbox.setAttribute('aria-busy', String(pending));
        more.disabled = pending;
        more.hidden = stopped || (nextPage === null && !refreshError);
        more.textContent = loadError || refreshError ? 'Thử lại' : 'Tải thêm hội thoại';
        status.textContent = stopped ? 'Không thể truy cập danh sách hội thoại.'
            : pending ? 'Đang tải hội thoại…'
                : loadError ? 'Chưa tải được thêm hội thoại. Vui lòng thử lại.'
                    : refreshError ? 'Chưa cập nhật được hội thoại. Vui lòng thử lại.'
                        : nextPage === null && rows.querySelector('[data-inbox-id]') ? 'Đã tải hết hội thoại.' : '';
    }
    function createRow(conversation) {
        const row = document.createElement('a');
        const url = new URL(inbox.dataset.messageLink, location.origin);
        url.searchParams.set(inbox.dataset.inboxTab === 'support' ? 'customer_id' : 'order_id', conversation.id);
        row.href = url.href;
        row.dataset.inboxId = String(conversation.id);
        row.className = 'list-group-item list-group-item-action';
        if (row.dataset.inboxId === inbox.dataset.selectedId) row.classList.add('active');
        const layout = document.createElement('div');
        layout.className = 'd-flex align-items-center gap-2';
        const avatar = document.createElement('span');
        avatar.className = 'rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0 overflow-hidden fw-bold';
        avatar.style.cssText = 'width:40px;height:40px';
        avatar.setAttribute('role', 'img');
        avatar.setAttribute('aria-label', `Ảnh đại diện của ${conversation.customer.trim()}`);
        const initial = document.createElement('span');
        initial.setAttribute('aria-hidden', 'true');
        initial.textContent = [...conversation.customer.trim()][0]?.toLocaleUpperCase('vi-VN') ?? '';
        avatar.append(initial);
        if (conversation.avatar_url) {
            const image = document.createElement('img');
            image.alt = '';
            image.className = 'w-100 h-100 avatar-cover';
            image.loading = 'lazy';
            initial.hidden = true;
            image.addEventListener('error', () => { initial.hidden = false; image.remove(); }, {once: true});
            image.src = conversation.avatar_url;
            avatar.append(image);
        }
        const body = document.createElement('div');
        body.className = 'flex-grow-1 min-w-0';
        const heading = document.createElement('div');
        heading.className = 'd-flex justify-content-between gap-2';
        const title = document.createElement('span');
        title.className = 'fw-semibold text-truncate';
        title.textContent = conversation.customer;
        if (conversation.unread) {
            const dot = document.createElement('span');
            dot.className = 'd-inline-block rounded-circle bg-danger ms-2';
            dot.style.cssText = 'width:10px;height:10px';
            dot.dataset.unreadDot = '';
            dot.setAttribute('role', 'img');
            dot.setAttribute('aria-label', 'Tin nhắn chưa đọc');
            title.append(dot);
        }
        const date = document.createElement('small');
        date.className = 'flex-shrink-0';
        date.textContent = conversation.date ?? '';
        heading.append(title, date);
        const number = document.createElement('small');
        number.className = 'd-block text-truncate';
        number.textContent = conversation.number;
        const preview = document.createElement('small');
        preview.className = 'd-block message-inbox-preview';
        preview.textContent = conversation.preview || 'Chưa có tin nhắn';
        body.append(heading, number, preview);
        layout.append(avatar, body);
        row.append(layout);
        return row;
    }
    function render(conversations, append) {
        const nextSignature = JSON.stringify(conversations);
        if (!append && signature === nextSignature) return;
        const oldRows = [...rows.querySelectorAll('[data-inbox-id]')];
        const existing = new Map(oldRows.map(row => [row.dataset.inboxId, row]));
        const anchor = inbox.scrollTop > 0 ? oldRows.find(row => row.getBoundingClientRect().bottom > inbox.getBoundingClientRect().top) : null;
        const anchorTop = anchor?.getBoundingClientRect().top;
        const focusedId = document.activeElement?.closest('[data-inbox-id]')?.dataset.inboxId;
        const fragment = document.createDocumentFragment();
        for (const conversation of conversations) {
            const row = createRow(conversation);
            const previous = existing.get(row.dataset.inboxId);
            if (append && previous) previous.replaceWith(row);
            else fragment.append(row);
            existing.delete(row.dataset.inboxId);
        }
        if (append) rows.append(fragment);
        else {
            if (hasAppended) for (const row of existing.values()) fragment.append(row);
            rows.replaceChildren(fragment);
            signature = nextSignature;
        }
        if (!rows.querySelector('[data-inbox-id]')) {
            const empty = document.createElement('div');
            empty.className = 'text-center text-muted p-4';
            empty.textContent = inbox.dataset.inboxTab === 'support' ? 'Không tìm thấy khách hàng tư vấn.' : 'Không tìm thấy hội thoại đơn hàng.';
            rows.replaceChildren(empty);
        }
        if (anchor) {
            const current = rows.querySelector(`[data-inbox-id="${anchor.dataset.inboxId}"]`);
            if (current) inbox.scrollTop += current.getBoundingClientRect().top - anchorTop;
        }
        if (focusedId) rows.querySelector(`[data-inbox-id="${focusedId}"]`)?.focus({preventScroll: true});
    }
    async function requestPage(page, append) {
        if (pending || stopped || disposed || document.hidden || !navigator.onLine) return;
        pending = true;
        clearTimeout(timer);
        controller = new AbortController();
        const deadline = setTimeout(() => controller.abort(), 30000);
        updateControls();
        try {
            const url = new URL(inbox.dataset.messageInbox, location.origin);
            url.searchParams.set('page', page);
            const response = await fetch(url.href, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            if (disposed || document.hidden || !navigator.onLine || controller.signal.aborted) return;
            if ([401, 403, 404].includes(response.status)) {
                stopped = true;
                rows.replaceChildren();
                return;
            }
            if (!response.ok) throw new Error('Inbox request failed');
            const data = await response.json();
            if (data.tab !== inbox.dataset.inboxTab || data.page !== page
                || (data.next_page !== null && data.next_page !== page + 1)
                || !Array.isArray(data.conversations) || data.conversations.length > 20
                || new Set(data.conversations.map(item => item.id)).size !== data.conversations.length
                || data.conversations.some(item => !Number.isSafeInteger(item.id) || item.id < 1
                    || typeof item.number !== 'string' || typeof item.customer !== 'string' || typeof item.unread !== 'boolean'
                    || (item.avatar_url !== null && typeof item.avatar_url !== 'string')
                    || (item.preview !== null && typeof item.preview !== 'string')
                    || (item.date !== null && typeof item.date !== 'string'))) throw new Error('Invalid inbox response');
            if (disposed || document.hidden || !navigator.onLine || controller.signal.aborted) return;
            render(data.conversations, append);
            if (append || !hasAppended) nextPage = data.next_page;
            if (append) { hasAppended = true; loadError = false; }
            refreshError = false;
            retry = 3000;
        } catch {
            if (!disposed && !document.hidden && navigator.onLine) {
                if (append) loadError = true;
                else refreshError = true;
                retry = Math.min(retry * 2, 120000);
            }
        } finally {
            clearTimeout(deadline);
            pending = false;
            controller = null;
            updateControls();
            schedule();
            if (!loadError && !refreshError) loadIfNearEnd();
        }
    }
    function sync() { return requestPage(basePage, false); }
    function loadIfNearEnd() {
        if (!loadError && !refreshError && nextPage !== null && inbox.clientHeight > 0
            && inbox.scrollHeight - inbox.scrollTop - inbox.clientHeight < 100) void requestPage(nextPage, true);
    }
    function resume() {
        clearTimeout(timer);
        if (document.hidden || !navigator.onLine) controller?.abort();
        else { retry = 3000; void sync(); }
    }
    inbox.addEventListener('scroll', loadIfNearEnd, {passive: true});
    more.addEventListener('click', () => {
        if (loadError && nextPage !== null) void requestPage(nextPage, true);
        else if (refreshError) void sync();
        else if (nextPage !== null) void requestPage(nextPage, true);
    });
    document.addEventListener('visibilitychange', resume);
    window.addEventListener('online', resume);
    window.addEventListener('offline', resume);
    window.addEventListener('resize', loadIfNearEnd);
    window.addEventListener('message-inbox-changed', () => { void sync(); });
    window.addEventListener('pagehide', () => { disposed = true; clearTimeout(timer); controller?.abort(); });
    window.addEventListener('pageshow', event => { if (event.persisted) { disposed = false; resume(); } });
    updateControls();
    void sync();
}

const messageLink = document.querySelector('[data-message-unread]');
if (messageLink) {
    const badge = messageLink.querySelector('[data-message-unread-count]');
    let timer, controller, pending = false, stopped = false, disposed = false, delay = 15000;

    async function sync() {
        if (pending || stopped || disposed || document.hidden || !navigator.onLine) return;
        clearTimeout(timer);
        pending = true;
        controller = new AbortController();
        const deadline = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch(messageLink.dataset.messageUnread, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            if ([401, 403, 404].includes(response.status)) {
                stopped = true;
                badge.hidden = true;
                messageLink.hidden = true;
                return;
            }
            if (!response.ok) throw new Error('Message count sync failed');
            const data = await response.json();
            if (!Number.isSafeInteger(data.unread_count) || data.unread_count < 0) throw new Error('Invalid message count');
            if (document.hidden || disposed) return;
            badge.hidden = data.unread_count === 0;
            badge.textContent = data.unread_count > 99 ? '99+' : String(data.unread_count);
            const label = `Tin nhắn khách hàng, ${data.unread_count} tin chưa đọc`;
            messageLink.setAttribute('aria-label', label);
            messageLink.setAttribute('title', label);
            delay = 15000;
        } catch {
            delay = Math.min(delay * 2, 120000);
        } finally {
            clearTimeout(deadline);
            pending = false;
            controller = null;
            if (!stopped && !disposed && !document.hidden && navigator.onLine) timer = setTimeout(sync, delay);
        }
    }

    function resume() {
        clearTimeout(timer);
        if (document.hidden || !navigator.onLine) controller?.abort();
        else { delay = 15000; void sync(); }
    }

    document.addEventListener('visibilitychange', resume);
    window.addEventListener('online', resume);
    window.addEventListener('offline', resume);
    window.addEventListener('message-inbox-changed', () => { void sync(); });
    window.addEventListener('pagehide', () => { disposed = true; clearTimeout(timer); controller?.abort(); });
    window.addEventListener('pageshow', event => { if (event.persisted) { disposed = false; resume(); } });
    void sync();
}
