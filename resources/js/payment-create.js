    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('paymentForm');
        const methodSelect = form?.querySelector('[name="method"]');
        const orderSelect = form?.querySelector('[name="order_id"]:not([type="hidden"])');
        const invoiceSelect = form?.querySelector('[name="invoice_id"]');
        const transactionCode = form?.querySelector('#transaction_code');
        const vietQrPanel = document.getElementById('paymentVietQrPanel');
        const vietQrImage = document.getElementById('payment-vietqr-image');
        const vietQrAmount = document.getElementById('payment-vietqr-amount');
        const vietQrMemo = document.getElementById('payment-vietqr-memo');
        const amountInput = form?.querySelector('input[name="amount"]');
        const bankNotice = document.getElementById('paymentBankNotice');
        const bankStatusText = bankNotice?.querySelector('[data-bank-status-text]');
        const bankSpinner = bankNotice?.querySelector('[data-bank-spinner]');
        const cashSubmit = document.getElementById('paymentCashSubmit');
        const bankComplete = document.getElementById('paymentBankComplete');

        if (!methodSelect || !transactionCode) {
            return;
        }

        let lastSuggestedCode = '';
        let bankKey = null;
        let intentReady = false;
        let startTimer;
        let pollTimer;
        let polling = false;

        function bankMessage(message, state = 'info', loading = false) {
            bankNotice?.classList.remove('d-none');
            bankNotice?.firstElementChild?.classList.remove('alert-info', 'alert-warning', 'alert-success');
            bankNotice?.firstElementChild?.classList.add('alert-' + state);
            if (bankStatusText) bankStatusText.textContent = message;
            bankSpinner?.classList.toggle('d-none', !loading);
        }

        function resetBank() {
            clearTimeout(startTimer);
            clearTimeout(pollTimer);
            bankKey = null;
            intentReady = false;
            vietQrPanel?.classList.add('d-none');
            bankComplete?.classList.add('d-none');
        }

        function selectedOrderCode() {
            const selectedOrder = orderSelect?.selectedOptions[0];
            if (selectedOrder?.dataset.orderCode) {
                return selectedOrder.dataset.orderCode;
            }

            const selectedInvoice = invoiceSelect?.selectedOptions[0];
            return selectedInvoice?.dataset.orderCode || 'TT';
        }

        function selectedPaymentOption() {
            const selectedInvoice = invoiceSelect?.selectedOptions[0];
            if (selectedInvoice?.value && (!orderSelect?.value || selectedInvoice.dataset.orderId === orderSelect.value)) {
                return selectedInvoice;
            }

            const selectedOrder = orderSelect?.selectedOptions[0];
            return selectedOrder?.value ? selectedOrder : null;
        }

        function updateVietQr() {
            const isBankTransfer = methodSelect.value === 'bank_transfer';
            const selectedOption = selectedPaymentOption();
            form.querySelectorAll('[data-cash-only]').forEach(field => field.classList.toggle('d-none', isBankTransfer));
            cashSubmit?.classList.toggle('d-none', isBankTransfer);
            if (cashSubmit) cashSubmit.disabled = isBankTransfer;
            if (amountInput && !amountInput.disabled) amountInput.readOnly = isBankTransfer;
            const selectedAmount = selectedOption?.dataset.amount;
            if (selectedAmount !== undefined && amountInput) {
                amountInput.value = selectedAmount;
            }

            const amount = Number(amountInput?.value || selectedAmount || 0);
            if (vietQrAmount) {
                vietQrAmount.textContent = amount.toLocaleString('vi-VN') + ' đ';
            }

            const memo = selectedOption?.dataset.paymentReference || '';
            if (vietQrMemo) {
                vietQrMemo.textContent = memo;
            }

            if (vietQrImage?.dataset.quicklinkBase) {
                const qrUrl = new URL(vietQrImage.dataset.quicklinkBase);
                qrUrl.searchParams.set('amount', String(Math.round(amount)));
                qrUrl.searchParams.set('addInfo', memo);
                vietQrImage.src = qrUrl.toString();
            }

            if (!isBankTransfer) {
                resetBank();
                bankNotice?.classList.add('d-none');
                return;
            }

            const orderId = orderSelect?.value || selectedOption?.dataset.orderId;
            if (!orderId || selectedOption?.dataset.invoiceReady !== '1') {
                resetBank();
                bankMessage('Hãy chọn đơn hàng có hóa đơn chưa thanh toán để nhận mã QR.', 'warning');
                return;
            }
            if (!vietQrImage) {
                resetBank();
                bankMessage('Chưa cấu hình tài khoản nhận chuyển khoản. Vui lòng liên hệ quản trị viên.', 'warning');
                return;
            }

            const invoiceId = selectedOption?.dataset.invoiceReady === '1' && selectedOption?.dataset.orderId
                ? selectedOption.value : '';
            const nextKey = orderId + ':' + invoiceId;
            if (bankKey === nextKey) return;
            resetBank();
            bankKey = nextKey;
            bankMessage('Đang chuẩn bị thông tin chuyển khoản...', 'info', true);
            startTimer = setTimeout(() => startBankTransfer(nextKey, orderId, invoiceId), 250);
        }

        async function startBankTransfer(key, orderId, invoiceId) {
            try {
                const response = await fetch(form.dataset.bankStartUrl, {
                    method: 'POST', credentials: 'same-origin', cache: 'no-store',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({ order_id: Number(orderId), ...(invoiceId ? { invoice_id: Number(invoiceId) } : {}) }),
                });
                const result = await response.json();
                if (bankKey !== key || methodSelect.value !== 'bank_transfer') return;
                if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Không thể chuẩn bị mã QR.');
                if (Number(result.order_id) !== Number(orderId) || !Number.isFinite(Number(result.amount)) || !result.reference) {
                    throw new Error('Thông tin chuyển khoản không hợp lệ.');
                }
                intentReady = true;
                if (amountInput) amountInput.value = result.amount;
                if (vietQrAmount) vietQrAmount.textContent = Number(result.amount).toLocaleString('vi-VN') + ' đ';
                if (vietQrMemo) vietQrMemo.textContent = result.reference;
                if (vietQrImage?.dataset.quicklinkBase) {
                    const qrUrl = new URL(vietQrImage.dataset.quicklinkBase);
                    qrUrl.searchParams.set('amount', String(Math.round(Number(result.amount))));
                    qrUrl.searchParams.set('addInfo', result.reference);
                    vietQrImage.src = qrUrl.toString();
                }
                vietQrPanel?.classList.remove('d-none');
                bankMessage('Đang chờ khách chuyển khoản...', 'info', true);
                pollBankStatus(key, orderId);
            } catch (error) {
                if (bankKey === key) bankMessage(error.message || 'Không thể chuẩn bị mã QR.', 'warning');
            }
        }

        async function pollBankStatus(key, orderId) {
            if (!intentReady || bankKey !== key || methodSelect.value !== 'bank_transfer') return;
            if (polling) {
                pollTimer = setTimeout(() => pollBankStatus(key, orderId), 1000);
                return;
            }
            if (document.hidden || !navigator.onLine) {
                pollTimer = setTimeout(() => pollBankStatus(key, orderId), 8000);
                return;
            }
            polling = true;
            try {
                const url = new URL(form.dataset.bankStatusUrl, location.origin);
                url.searchParams.set('order_id', orderId);
                const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
                if (bankKey !== key) return;
                if ([401, 403, 404].includes(response.status)) {
                    intentReady = false;
                    bankMessage('Không thể tiếp tục kiểm tra thanh toán. Vui lòng tải lại trang.', 'warning');
                    return;
                }
                if (!response.ok) throw new Error('Không thể kiểm tra trạng thái thanh toán.');
                const result = await response.json();
                if (result.paid) {
                    intentReady = false;
                    vietQrPanel?.classList.add('d-none');
                    bankComplete?.classList.remove('d-none');
                    bankMessage('Đã nhận đủ tiền chuyển khoản.' + (result.transaction_code ? ' Mã giao dịch: ' + result.transaction_code : ''), 'success');
                    return;
                }
                if (result.settled_by_other_method) {
                    intentReady = false;
                    vietQrPanel?.classList.add('d-none');
                    bankMessage('Đơn hàng đã được thanh toán bằng phương thức khác.', 'warning');
                    return;
                }
            } catch (_) {
                if (bankKey === key) bankMessage('Đang chờ xác nhận từ ngân hàng. Hệ thống sẽ tự kiểm tra lại...', 'info', true);
            } finally {
                polling = false;
                if (intentReady && bankKey === key) pollTimer = setTimeout(() => pollBankStatus(key, orderId), 8000);
            }
        }

        function suggestTransactionCode() {
            const isCash = methodSelect.value === 'cash';
            transactionCode.placeholder = 'Mã giao dịch tiền mặt tự động';

            if (!isCash) {
                transactionCode.value = '';
                updateVietQr();
                return;
            }

            const prefix = isCash ? 'TM' : 'CK';
            const now = new Date();
            const timestamp = [
                now.getFullYear(),
                String(now.getMonth() + 1).padStart(2, '0'),
                String(now.getDate()).padStart(2, '0'),
                String(now.getHours()).padStart(2, '0'),
                String(now.getMinutes()).padStart(2, '0'),
                String(now.getSeconds()).padStart(2, '0'),
            ].join('');
            lastSuggestedCode = prefix + '_' + selectedOrderCode() + '_' + timestamp;
            transactionCode.value = lastSuggestedCode;
            updateVietQr();
        }

        methodSelect.addEventListener('change', suggestTransactionCode);

        orderSelect?.addEventListener('change', function() {
            suggestTransactionCode();
        });

        invoiceSelect?.addEventListener('change', function() {
            suggestTransactionCode();
        });

        amountInput?.addEventListener('input', updateVietQr);

        form.addEventListener('submit', function(event) {
            if (methodSelect.value !== 'cash') event.preventDefault();
        });

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && intentReady && bankKey) {
                clearTimeout(pollTimer);
                pollBankStatus(bankKey, bankKey.split(':')[0]);
            }
        });

        suggestTransactionCode();
    });
