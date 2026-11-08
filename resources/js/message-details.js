const toggle = document.querySelector('[data-message-details-toggle]');
const panel = document.querySelector('[data-message-details-panel]');
const chatColumn = document.querySelector('[data-message-chat-column]');

if (toggle && panel && chatColumn) {
    toggle.addEventListener('click', () => {
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        const shouldExpand = !isExpanded;

        toggle.setAttribute('aria-expanded', String(shouldExpand));
        toggle.setAttribute(
            'aria-label',
            shouldExpand ? 'Ẩn thông tin khách hàng và đơn hàng' : 'Hiện thông tin khách hàng và đơn hàng',
        );
        panel.hidden = !shouldExpand;
        chatColumn.classList.toggle('col-xl-9', !shouldExpand);
        chatColumn.classList.toggle('col-xl-6', shouldExpand);
    });
}
