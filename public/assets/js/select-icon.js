/**
 * select-icon.js — Lightweight icon preview for service-category <select> filters.
 *
 * Keeps the native <select> intact; a sibling <i class="icon-preview">
 * mirrors the FontAwesome icon of the currently-selected option via
 * data-icon attributes. One delegated change listener covers selects
 * added to the DOM later (e.g. order rows injected by JS).
 *
 * Vanilla JS — no jQuery, no Select2/Choices/Tom Select.
 */
(function () {
    'use strict';

    var MARKER = '.js-icon-select';

    /**
     * Sync the preview <i> that immediately follows `select` to the
     * data-icon of the currently selected <option>.
     */
    function refreshPreview(select) {
        var preview = select.nextElementSibling;
        if (!preview || !preview.classList.contains('icon-preview')) {
            return;
        }

        var option = select.options[select.selectedIndex];
        var icon = option && option.dataset ? option.dataset.icon : '';

        if (!icon) {
            preview.setAttribute('hidden', '');
            preview.className = 'icon-preview';
        } else {
            preview.removeAttribute('hidden');
            preview.className = 'icon-preview ' + icon;
        }
    }

    // One delegated listener — works for selects added to the DOM later too
    document.addEventListener('change', function (e) {
        if (e.target.matches(MARKER)) {
            refreshPreview(e.target);
        }
    });

    // Initialise every marker select on page load (edit forms, pre-filtered pages)
    document.addEventListener('DOMContentLoaded', function () {
        var selects = document.querySelectorAll(MARKER);
        selects.forEach(function (select) {
            refreshPreview(select);
        });
    });
})();
