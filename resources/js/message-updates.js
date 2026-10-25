// Read message content through Laravel session/ACL; Realtime only signals changes.
const conversation = document.querySelector('[data-message-updates]');

if (conversation) {
    const status = document.querySelector('[data-message-status]');
    const connectionDot = document.querySelector('[data-message-connection-dot]');
    const form = document.querySelector('[data-message-form]');
    const orderId = conversation.dataset.orderId ? Number(conversation.dataset.orderId) : null;
    const customerId = conversation.dataset.customerAccountId ? Number(conversation.dataset.customerAccountId) : null;
    const messageActionsAllowed = conversation.dataset.messageCanManage === '1';
    const interval = 3000;
    let realtime, connected = false, queued = false;
    let timer, controller, signature, pending = false, stopped = false, disposed = false, retry = interval;

    const acknowledged = new Set();

    const connectionViews = {
        realtime: ['text-success', 'Đang nhận tin nhắn trực tiếp'],
        polling: ['bg-warning', 'Chưa kết nối realtime; tự cập nhật mỗi 3 giây'],
        paused: ['text-body-secondary', 'Tạm dừng cập nhật'],
        error: ['bg-danger', 'Kết nối gián đoạn; đang thử lại'],
    };

    function setConnectionState(state) {
        const [color, label] = connectionViews[state];
        connectionDot.className = `message-status-dot ${color}`;
        connectionDot.title = label;
        connectionDot.setAttribute('aria-label', label);
    }

    function showStatus(text, state = null) {
        status.textContent = text;
        if (state === null) return;
        setConnectionState(state);
    }

    async function prepareMessageImage(file) {
        const maxBytes = 3 * 1024 * 1024;
        if (file.size <= maxBytes) return file;
        if (file.size > 20 * 1024 * 1024) throw new Error('Ảnh gốc vượt quá 20 MB.');
        if (typeof createImageBitmap !== 'function') throw new Error('Trình duyệt không hỗ trợ tối ưu ảnh.');
        const bitmap = await createImageBitmap(file);
        try {
            let scale = Math.min(1, 2000 / Math.max(bitmap.width, bitmap.height));
            while (scale >= 0.2) {
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                const context = canvas.getContext('2d');
                if (!context) throw new Error('Không thể xử lý ảnh đã chọn.');
                context.fillStyle = '#fff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                for (const quality of [0.84, 0.72, 0.6, 0.5]) {
                    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
                    if (blob && blob.size <= maxBytes) return new File([blob], 'tin-nhan.jpg', { type: 'image/jpeg' });
                }
                scale *= 0.8;
            }
        } finally {
            bitmap.close();
        }
        throw new Error('Không thể nén ảnh xuống dưới 3 MB.');
    }

    function formatSentAt(timestamp) {
        if (timestamp === null) return '';
        const date = new Date(timestamp);
        if (Number.isNaN(date.getTime())) throw new Error('Invalid message timestamp');
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Ho_Chi_Minh',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(date).reduce((values, part) => {
            values[part.type] = part.value;
            return values;
        }, {});
        return `${parts.day}-${parts.month}-${parts.year} ${parts.hour}:${parts.minute}`;
    }

    function render(messages) {
        // Signed Storage URLs rotate on every poll. Ignore query tokens when deciding
        // whether the message content itself changed, so polling does not blink the chat.
        const nextSignature = JSON.stringify(messages.map(message => ({ ...message,
            image_url: message.image_url ? new URL(message.image_url).pathname : null,
        })));
        if (signature === nextSignature) {
            for (const message of messages) {
                if (!message.image_url) continue;
                const row = [...conversation.children].find(item => item.dataset.messageId === String(message.id));
                const image = row?.querySelector('img');
                if (image && Date.now() - Number(image.dataset.signedUrlUpdatedAt || 0) > 50 * 60 * 1000) {
                    row.querySelector('a').href = message.image_url;
                    image.src = message.image_url;
                    image.dataset.signedUrlUpdatedAt = String(Date.now());
                }
            }
            return false;
        }
        const atBottom = conversation.scrollHeight - conversation.scrollTop - conversation.clientHeight < 48;
        const anchor = [...conversation.children].find(row => row.offsetTop >= conversation.scrollTop);
        const anchorId = anchor?.dataset.messageId;
        const anchorOffset = anchor ? anchor.offsetTop - conversation.scrollTop : 0;
        const previousTop = conversation.scrollTop;
        const fragment = document.createDocumentFragment();
        for (const message of messages) {
            const row = document.createElement('div');
            row.dataset.messageId = String(message.id);
            row.tabIndex = 0;
            row.className = `d-flex ${message.is_mine ? 'justify-content-end' : 'justify-content-start'} mb-3`;
            const bubble = document.createElement('div');
            bubble.className = `rounded-3 px-3 py-2 ${message.recalled ? 'bg-light border text-muted fst-italic' : (message.is_mine ? 'bg-primary text-white' : 'bg-white border')}`;
            bubble.style.maxWidth = '82%';
            const sender = document.createElement('div');
            sender.className = `small ${message.recalled ? 'text-muted' : (message.is_mine ? 'text-white' : 'text-muted')}`;
            sender.textContent = message.sender_name;
            bubble.append(sender);
            if (message.image_url) {
                const link = document.createElement('a');
                link.href = message.image_url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                const image = document.createElement('img');
                image.src = message.image_url;
                image.dataset.signedUrlUpdatedAt = String(Date.now());
                image.alt = 'Ảnh đính kèm';
                image.className = 'img-fluid rounded mb-1';
                image.style.maxHeight = '240px';
                link.append(image);
                bubble.append(link);
            } else {
                const element = document.createElement('div');
                element.className = 'text-break';
                element.textContent = message.content;
                bubble.append(element);
            }
            const time = document.createElement('div');
            time.className = `small text-end ${message.recalled ? 'text-muted' : (message.is_mine ? 'text-white' : 'text-muted')}`;
            time.textContent = formatSentAt(message.sent_at);
            bubble.append(time);
            if (conversation.dataset.messageHide && messageActionsAllowed) {
                const actions = document.createElement('div');
                actions.className = 'message-actions';
                actions.dataset.messageActions = '';
                const toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className = 'message-menu-toggle btn btn-sm';
                toggle.dataset.messageMenuToggle = '';
                toggle.setAttribute('aria-label', 'Tùy chọn tin nhắn');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.textContent = '⋮';
                actions.append(toggle);
                const menu = document.createElement('div');
                menu.className = 'message-actions-menu';
                menu.dataset.messageActionsMenu = '';
                if (!message.recalled && message.is_mine && !message.image_url && conversation.dataset.messageEdit) {
                    const edit = document.createElement('button');
                    edit.type = 'button';
                    edit.className = 'dropdown-item';
                    edit.dataset.messageEdit = String(message.id);
                    edit.textContent = 'Sửa';
                    menu.append(edit);
                }
                if (!message.recalled && message.is_mine) {
                    const recall = document.createElement('button');
                    recall.type = 'button';
                    recall.className = 'dropdown-item';
                    recall.dataset.messageRecall = String(message.id);
                    recall.textContent = 'Thu hồi';
                    menu.append(recall);
                }
                const hide = document.createElement('button');
                hide.type = 'button';
                hide.className = 'dropdown-item';
                hide.dataset.messageHide = String(message.id);
                hide.textContent = 'Xóa';
                menu.append(hide);
                actions.append(menu);
                bubble.append(actions);
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
        return true;
    }

    function schedule() {
        clearTimeout(timer);
        if (!stopped && !disposed && !document.hidden && navigator.onLine) timer = setTimeout(sync, connected ? Math.max(retry, 60000) : retry);
    }

    async function sync() {
        if (stopped || disposed || document.hidden || !navigator.onLine) return;
        if (pending) { queued = true; return; }
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
                realtime?.pause();
                conversation.replaceChildren();
                if (form) form.dataset.accessLost = 'true';
                form?.querySelectorAll('textarea, button').forEach(input => { input.disabled = true; });
                showStatus(response.status === 401 ? 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.' : 'Cuộc trò chuyện không còn khả dụng.', 'paused');
                return;
            }
            if (response.status === 503) {
                const body = await response.json().catch(() => ({}));
                showStatus(body.message || 'Máy chủ chưa tải được nội dung hội thoại. Đang thử lại…', 'error');
                retry = Math.min(retry * 2, 120000);
                return;
            }
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                throw new Error(body.message || `Máy chủ lỗi khi tải tin nhắn (HTTP ${response.status}).`);
            }
            const data = await response.json();
            if (data.order_id !== orderId || (customerId !== null && data.customer_account_id !== customerId) || !Array.isArray(data.messages) || data.messages.length > 100
                || data.messages.some(message => !Number.isSafeInteger(message.id) || message.id < 1
                    || typeof message.content !== 'string' || typeof message.sender_name !== 'string'
                    || typeof message.is_mine !== 'boolean' || (message.image_url != null && typeof message.image_url !== 'string')
                    || (message.sent_at !== null && typeof message.sent_at !== 'string'))) {
                throw new Error('Invalid conversation response');
            }
            if (document.hidden || disposed) return;
            const messagesChanged = render(data.messages);
            const ids = data.messages.filter(message => !acknowledged.has(message.id)).map(message => message.id);
            if (conversation.dataset.messageRead && ids.length) {
                const receipt = await fetch(conversation.dataset.messageRead, {
                    method: 'POST', credentials: 'same-origin', signal: controller.signal,
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    body: JSON.stringify({ ...(customerId !== null ? { customer_id: customerId } : { order_id: orderId }), message_ids: ids }),
                });
                if (!receipt.ok) throw new Error('Read acknowledgement failed');
                ids.forEach(id => acknowledged.add(id));
            }
            if (messagesChanged) window.dispatchEvent(new Event('message-inbox-changed'));
            retry = interval;
            showStatus('', connected ? 'realtime' : 'polling');
        } catch (error) {
            if (!document.hidden && !stopped) showStatus(error?.message || 'Kết nối gián đoạn. Đang thử lại…', 'error');
            retry = Math.min(retry * 2, 120000);
        } finally {
            clearTimeout(deadline);
            controller = null;
            pending = false;
            if (queued && !stopped && !disposed && !document.hidden && navigator.onLine) {
                queued = false;
                timer = setTimeout(sync, 1000);
            } else {
                queued = false;
                schedule();
            }
        }
    }

    function resume() {
        if (stopped) return;
        clearTimeout(timer);
        if (document.hidden || !navigator.onLine) {
            controller?.abort();
            realtime?.pause();
            showStatus(document.hidden ? 'Tạm dừng cập nhật' : 'Mất kết nối. Đang chờ kết nối lại…', document.hidden ? 'paused' : 'error');
        } else {
            retry = interval;
            void realtime?.resume();
            void sync();
        }
    }

    function conversationTarget() {
        return customerId !== null ? { customer_id: customerId } : { order_id: orderId };
    }

    conversation.addEventListener('click', async event => {
        const menuToggle = event.target.closest('[data-message-menu-toggle]');
        if (menuToggle) {
            const row = menuToggle.closest('[data-message-id]');
            const shouldOpen = row.dataset.actionsOpen !== 'true';
            conversation.querySelectorAll('[data-actions-open="true"]').forEach(open => {
                open.dataset.actionsOpen = 'false';
                open.querySelector('[data-message-menu-toggle]')?.setAttribute('aria-expanded', 'false');
            });
            row.dataset.actionsOpen = shouldOpen ? 'true' : 'false';
            menuToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            return;
        }
        if (!event.target.closest('[data-message-actions-menu]')) {
            conversation.querySelectorAll('[data-actions-open="true"]').forEach(open => {
                open.dataset.actionsOpen = 'false';
                open.querySelector('[data-message-menu-toggle]')?.setAttribute('aria-expanded', 'false');
            });
        }
        const button = event.target.closest('[data-message-hide], [data-message-recall], [data-message-edit]');
        if (!button || !messageActionsAllowed) return;
        if (button.hasAttribute('data-message-edit')) {
            const messageId = button.dataset.messageEdit;
            const row = button.closest('[data-message-id]');
            const content = row?.querySelector('.text-break');
            if (!row || !content) return;
            row.dataset.actionsOpen = 'false';
            row.querySelector('[data-message-menu-toggle]')?.setAttribute('aria-expanded', 'false');
            const original = content.textContent ?? '';
            const editor = document.createElement('div');
            editor.className = 'message-inline-editor mt-2';
            const input = document.createElement('textarea');
            input.className = 'form-control form-control-sm';
            input.rows = 2;
            input.maxLength = 1000;
            input.value = original;
            const controls = document.createElement('div');
            controls.className = 'd-flex justify-content-end gap-2 mt-2';
            const cancel = document.createElement('button');
            cancel.type = 'button'; cancel.className = 'btn btn-sm btn-outline-secondary'; cancel.textContent = 'Hủy';
            const save = document.createElement('button');
            save.type = 'button'; save.className = 'btn btn-sm btn-light'; save.textContent = 'Lưu';
            controls.append(cancel, save); editor.append(input, controls); content.replaceWith(editor); input.focus();
            cancel.addEventListener('click', () => { const restored = document.createElement('div'); restored.className = 'text-break'; restored.textContent = original; editor.replaceWith(restored); });
            save.addEventListener('click', async () => {
                const editedContent = input.value.trim();
                if (!editedContent || editedContent === original) { cancel.click(); return; }
                save.disabled = true;
                try {
                    const endpoint = conversation.dataset.messageEdit.replace(/\/0\/edit$/, `/${messageId}/edit`);
                    const response = await fetch(endpoint, {
                        method: 'PATCH', credentials: 'same-origin', headers: {
                            Accept: 'application/json', 'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        }, body: JSON.stringify({ ...conversationTarget(), content: editedContent }),
                    });
                    if (!response.ok) {
                        const body = await response.json().catch(() => ({}));
                        throw new Error(body.message || Object.values(body.errors ?? {}).flat()[0] || 'Không sửa được tin nhắn.');
                    }
                    signature = undefined;
                    await sync();
                } catch (error) {
                    showStatus(error.message || 'Không sửa được tin nhắn.');
                    save.disabled = false;
                }
            });
            return;
        }
        const isRecall = button.hasAttribute('data-message-recall');
        const messageId = button.dataset[isRecall ? 'messageRecall' : 'messageHide'];
        const action = isRecall ? 'recall' : 'hide';
        if (!window.confirm(isRecall ? 'Thu hồi tin nhắn này khỏi cuộc trò chuyện?' : 'Xóa tin nhắn này khỏi phía cửa hàng?')) return;
        button.disabled = true;
        try {
            const template = conversation.dataset[isRecall ? 'messageRecall' : 'messageHide'];
            const endpoint = template.replace(/\/0\/(hide|recall)$/, `/${messageId}/$1`);
            const response = await fetch(endpoint, {
                method: 'POST', credentials: 'same-origin', headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                }, body: JSON.stringify(conversationTarget()),
            });
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                throw new Error(body.message || Object.values(body.errors ?? {}).flat()[0] || 'Không thể cập nhật tin nhắn.');
            }
            signature = undefined;
            await sync();
        } catch (error) {
            showStatus(error.message || 'Không thể cập nhật tin nhắn.');
            button.disabled = false;
        }
    });

    conversation.addEventListener('contextmenu', event => {
        const row = event.target.closest('[data-message-id]');
        if (!row || !messageActionsAllowed) return;
        event.preventDefault();
        conversation.querySelectorAll('[data-actions-open="true"]').forEach(open => { open.dataset.actionsOpen = 'false'; });
        row.dataset.actionsOpen = 'true';
    });

    if (form) {
        const submit = form.querySelector('button[type="submit"]');
        const originalSubmitMarkup = submit?.innerHTML ?? '';
        form.querySelectorAll('[data-message-template]').forEach(template => {
            template.addEventListener('click', () => {
                if (form.dataset.accessLost === 'true') return;
                const composer = form.querySelector('textarea[name="content"]');
                if (!composer) return;
                composer.value = template.dataset.messageTemplate ?? '';
                composer.focus();
            });
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.accessLost === 'true') return;
            submit.disabled = true;
            form.setAttribute('aria-busy', 'true');
            submit.textContent = 'Đang gửi…';
            showStatus('Đang gửi tin nhắn…');
            const sendController = new AbortController();
            const sendDeadline = setTimeout(() => sendController.abort(), 90000);
            try {
                const formData = new FormData(form);
                const imageInput = form.querySelector('[data-message-image]');
                const image = imageInput?.files?.[0];
                if (image) {
                    if (image.size > 5 * 1024 * 1024) throw new Error('Ảnh tối đa 5 MB.');
                    if (!['image/jpeg', 'image/png', 'image/webp'].includes(image.type)) throw new Error('Chỉ hỗ trợ ảnh JPEG, PNG hoặc WEBP.');
                    const previewUrl = URL.createObjectURL(image);
                    const optimistic = document.createElement('div');
                    optimistic.className = 'd-flex justify-content-end mb-3';
                    optimistic.dataset.pendingImage = 'true';
                    const bubble = document.createElement('div');
                    bubble.className = 'rounded-3 px-3 py-2 bg-primary text-white';
                    bubble.style.maxWidth = '82%';
                    const sender = document.createElement('div'); sender.className = 'small text-white-50'; sender.textContent = 'Cửa hàng';
                    const preview = document.createElement('img'); preview.src = previewUrl; preview.alt = 'Ảnh đang gửi';
                    preview.className = 'img-fluid rounded mb-1'; preview.style.maxHeight = '240px';
                    const pendingLabel = document.createElement('div'); pendingLabel.className = 'small text-end text-white-50'; pendingLabel.textContent = 'Đang gửi ảnh…';
                    bubble.append(sender, preview, pendingLabel); optimistic.append(bubble); conversation.append(optimistic);
                    conversation.scrollTop = conversation.scrollHeight;
                    try {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                        const imageToUpload = await prepareMessageImage(image);
                        showStatus('Đang gửi ảnh…');
                        const uploadUrlResponse = await fetch(conversation.dataset.messageImageUpload, {
                            method: 'POST', credentials: 'same-origin', cache: 'no-store',
                            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ ...(customerId !== null ? { customer_id: customerId } : { order_id: orderId }), mime_type: imageToUpload.type }),
                            signal: sendController.signal,
                        });
                        const uploadData = await uploadUrlResponse.json().catch(() => ({}));
                        if (!uploadUrlResponse.ok) {
                            throw new Error(uploadData.message || Object.values(uploadData.errors ?? {}).flat()[0] || `Không tạo được nơi tải ảnh (HTTP ${uploadUrlResponse.status}).`);
                        }
                        if (!uploadData.path || !uploadData.signed_url || !uploadData.api_key || !uploadData.project_url) throw new Error('Máy chủ trả về thông tin tải ảnh không hợp lệ.');
                        let signedUrl = uploadData.signed_url;
                        if (!/^https?:\/\//i.test(signedUrl)) {
                            const base = uploadData.project_url.replace(/\/$/, '');
                            signedUrl = base + (signedUrl.startsWith('/storage/v1/') ? signedUrl : `/storage/v1${signedUrl.startsWith('/') ? '' : '/'}${signedUrl}`);
                        }
                        const storageResponse = await fetch(signedUrl, {
                            method: 'PUT', headers: { apikey: uploadData.api_key, 'x-upsert': 'false', 'Content-Type': imageToUpload.type },
                            body: imageToUpload, signal: sendController.signal,
                        });
                        if (!storageResponse.ok) {
                            const detail = await storageResponse.json().catch(() => ({}));
                            throw new Error(detail.message || detail.error || `Tải ảnh lên kho thất bại (HTTP ${storageResponse.status}).`);
                        }
                        const messageResponse = await fetch(form.action, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ ...conversationTarget(), image_path: uploadData.path }),
                            signal: sendController.signal,
                        });
                        if (!messageResponse.ok) {
                            const body = await messageResponse.json().catch(() => ({}));
                            throw new Error(body.message || Object.values(body.errors ?? {}).flat()[0] || `Lưu tin nhắn ảnh thất bại (HTTP ${messageResponse.status}).`);
                        }
                        optimistic.remove(); URL.revokeObjectURL(previewUrl);
                    } catch (error) { optimistic.remove(); URL.revokeObjectURL(previewUrl); throw error; }
                    form.querySelector('textarea[name="content"]').value = '';
                    imageInput.value = '';
                    window.dispatchEvent(new Event('message-inbox-changed'));
                    signature = undefined;
                    await sync();
                    return;
                }
                const response = await fetch(form.action, {
                    method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: sendController.signal,
                    body: formData,
                });
                if (!response.ok) {
                    const body = await response.json().catch(() => ({}));
                    throw new Error(body.message || Object.values(body.errors ?? {}).flat()[0] || 'Không gửi được tin nhắn.');
                }
                form.querySelector('textarea[name="content"]').value = '';
                form.querySelector('[data-message-image]').value = '';
                window.dispatchEvent(new Event('message-inbox-changed'));
                signature = undefined;
                await sync();
            } catch (error) {
                showStatus(error.name === 'AbortError' ? 'Gửi quá thời gian chờ. Vui lòng thử lại.' : (error.message || 'Không gửi được tin nhắn.'));
            } finally {
                clearTimeout(sendDeadline);
                submit.disabled = false;
                form.removeAttribute('aria-busy');
                submit.innerHTML = originalSubmitMarkup;
            }
        });

        const imageInput = form.querySelector('[data-message-image]');
        const attachClipboardImage = file => {
            if (!file || !file.type.startsWith('image/')) return false;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                showStatus('Chỉ hỗ trợ ảnh JPEG, PNG hoặc WEBP.');
                return true;
            }
            if (file.size > 5 * 1024 * 1024) {
                showStatus('Ảnh tối đa 5 MB.');
                return true;
            }
            const transfer = new DataTransfer();
            const namedFile = new File([file], file.name || `anh-dan-vao.${file.type.split('/')[1]}`, { type: file.type });
            transfer.items.add(namedFile);
            imageInput.files = transfer.files;
            showStatus('Đã đính kèm ảnh. Nhấn Gửi để gửi vào cuộc trò chuyện.');
            return true;
        };
        imageInput?.addEventListener('change', () => {
            if (imageInput.files?.[0]) showStatus(`Đã chọn ảnh: ${imageInput.files[0].name}`);
        });
        form.addEventListener('paste', event => {
            const items = [...(event.clipboardData?.items ?? [])];
            const imageItem = items.find(item => item.kind === 'file' && item.type.startsWith('image/'));
            if (imageItem && attachClipboardImage(imageItem.getAsFile())) event.preventDefault();
        });
    }

    window.addEventListener('message-inbox-changed', () => { void sync(); });

    document.addEventListener('visibilitychange', resume);
    window.addEventListener('online', resume);
    window.addEventListener('offline', resume);
    window.addEventListener('pagehide', () => {
        disposed = true;
        realtime?.pause();
        clearTimeout(timer);
        controller?.abort();
    });
    // Browser back/forward cache restores this document without rerunning scripts.
    window.addEventListener('pageshow', event => { if (event.persisted) { disposed = false; resume(); } });
    if (conversation.dataset.messageRealtime) {
        void import('./message-realtime.js').then(({ startMessageRealtime }) => {
            if (stopped || disposed) return;
            realtime = startMessageRealtime({
                endpoint: conversation.dataset.messageRealtime,
                body: customerId !== null ? { customer_id: customerId } : { order_id: orderId },
                csrf: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                refresh: () => { void sync(); },
                connection: ready => {
                    connected = ready;
                    setConnectionState(ready ? 'realtime' : 'polling');
                    if (!ready) schedule();
                },
                denied: () => { void sync(); },
            });
            if (!document.hidden && navigator.onLine) void realtime.resume();
        }).catch(() => {
            console.error('Message Realtime module could not load; polling fallback remains active.');
        });
    }
    void sync();
}
