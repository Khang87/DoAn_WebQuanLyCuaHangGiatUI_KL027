const debounceDelay = 400;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-address-autocomplete]').forEach(input => {
        const wrapper = input.closest('.position-relative');
        const suggestions = wrapper.querySelector('[role="listbox"]');
        const status = wrapper.querySelector('[data-address-autocomplete-status]');
        let debounceTimer;
        let requestController;
        let requestGeneration = 0;

        function closeSuggestions() {
            suggestions.replaceChildren();
            suggestions.classList.add('d-none');
            input.setAttribute('aria-expanded', 'false');
        }

        function cancelSuggestions(clearStatus = false) {
            clearTimeout(debounceTimer);
            requestController?.abort();
            requestGeneration++;
            closeSuggestions();
            if (clearStatus) {
                status.textContent = '';
            }
        }

        async function loadSuggestions(query, generation) {
            requestController = new AbortController();
            const url = new URL(input.dataset.autocompleteUrl, window.location.origin);
            url.searchParams.set('input', query);

            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: requestController.signal,
                });
                const payload = await response.json();

                if (generation !== requestGeneration) {
                    return;
                }
                if (!response.ok) {
                    status.textContent = payload.message || 'Không thể tải gợi ý địa chỉ lúc này.';
                    return;
                }
                if (!Array.isArray(payload)) {
                    status.textContent = 'Dịch vụ gợi ý địa chỉ trả về dữ liệu không hợp lệ.';
                    return;
                }
                if (payload.length === 0) {
                    status.textContent = 'Không tìm thấy địa chỉ phù hợp.';
                    return;
                }

                payload.forEach(place => {
                    if (typeof place.description !== 'string' || place.description === '') {
                        return;
                    }

                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'list-group-item list-group-item-action text-start';
                    option.setAttribute('role', 'option');
                    option.textContent = place.description;
                    option.addEventListener('click', event => {
                        event.stopPropagation();
                        input.value = place.description;
                        cancelSuggestions();
                        status.textContent = 'Đã chọn địa chỉ.';
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    option.addEventListener('keydown', event => {
                        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
                            return;
                        }

                        event.preventDefault();
                        const options = [...suggestions.querySelectorAll('[role="option"]')];
                        const currentIndex = options.indexOf(option);
                        const nextIndex = event.key === 'ArrowDown'
                            ? (currentIndex + 1) % options.length
                            : (currentIndex - 1 + options.length) % options.length;
                        options[nextIndex].focus();
                    });
                    suggestions.append(option);
                });

                if (suggestions.childElementCount > 0) {
                    suggestions.classList.remove('d-none');
                    input.setAttribute('aria-expanded', 'true');
                    status.textContent = `${suggestions.childElementCount} gợi ý địa chỉ.`;
                } else {
                    status.textContent = 'Không tìm thấy địa chỉ phù hợp.';
                }
            } catch (error) {
                if (error.name === 'AbortError' || generation !== requestGeneration) {
                    return;
                }

                status.textContent = 'Không thể kết nối dịch vụ gợi ý địa chỉ. Vui lòng thử lại.';
            }
        }

        input.addEventListener('input', () => {
            cancelSuggestions();

            const query = input.value.trim();
            if (input.disabled || query.length < 3) {
                status.textContent = query.length > 0 && !input.disabled
                    ? 'Nhập ít nhất 3 ký tự để tìm địa chỉ.'
                    : '';
                return;
            }

            status.textContent = 'Đang tìm địa chỉ...';
            const generation = requestGeneration;
            debounceTimer = setTimeout(() => loadSuggestions(query, generation), debounceDelay);
        });

        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' && !suggestions.classList.contains('d-none')) {
                event.preventDefault();
                suggestions.querySelector('[role="option"]')?.focus();
            } else if (event.key === 'Escape') {
                cancelSuggestions(true);
            }
        });

        document.addEventListener('click', event => {
            if (!wrapper.contains(event.target)) {
                cancelSuggestions(true);
            }
        });

        input.form.addEventListener('reset', () => {
            cancelSuggestions(true);
        });

        input.form.querySelectorAll('#booking-method, #booking-return-method').forEach(method => {
            method.addEventListener('change', () => {
                queueMicrotask(() => {
                    if (input.disabled) {
                        cancelSuggestions(true);
                    }
                });
            });
        });

        input.addEventListener('change', () => {
            queueMicrotask(() => {
                if (input.disabled) {
                    cancelSuggestions(true);
                }
            });
        });
    });
});
