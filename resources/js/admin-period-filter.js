const filterSelect = document.getElementById('period-filter-select');
const filterForm = document.getElementById('period-filter-form');

if (filterSelect && filterForm) {
    const activeFilter = filterSelect.dataset.activeFilter;
    const customRangeEnabled = filterSelect.dataset.allowCustom === 'true';
    const validFilters = new Set(['today', 'this_week', 'this_month', 'all_time']);
    const dateRangeFields = document.getElementById('period-date-range-fields');
    const dateFromInput = document.getElementById('period-date-from');
    const dateToInput = document.getElementById('period-date-to');
    const applyButton = document.getElementById('period-filter-apply');
    const url = new URL(window.location.href);
    const filterKeys = [...url.searchParams.keys()].filter(key => key === 'filter' || key.startsWith('filter['));

    if (filterKeys.length && (filterKeys.length !== 1 || url.searchParams.get('filter') !== activeFilter)) {
        filterKeys.forEach(key => url.searchParams.delete(key));
        url.searchParams.set('filter', activeFilter);
        window.history.replaceState(null, '', url.toString());
    }

    const syncDateRange = () => {
        if (!dateRangeFields || !dateFromInput || !dateToInput) {
            return;
        }

        const isAllTime = customRangeEnabled && filterSelect.value === 'all_time';
        const keepRangeSpace = dateRangeFields.classList.contains('period-date-range-fields--reserved');
        dateRangeFields.classList.toggle('d-none', !isAllTime && !keepRangeSpace);
        dateRangeFields.setAttribute('aria-hidden', String(!isAllTime));
        dateFromInput.disabled = !isAllTime;
        dateToInput.disabled = !isAllTime;
        dateFromInput.required = false;
        dateToInput.required = false;

        if (applyButton) {
            applyButton.disabled = !isAllTime
                || !dateFromInput.value
                || !dateToInput.value
                || dateFromInput.value > dateToInput.value;
        }
    };

    filterSelect.addEventListener('change', () => {
        if (validFilters.has(filterSelect.value) && filterSelect.value !== activeFilter) {
            syncDateRange();
            filterForm.requestSubmit();
        } else {
            if (!validFilters.has(filterSelect.value)) {
                filterSelect.value = activeFilter;
            }
            syncDateRange();
        }
    });
    dateFromInput?.addEventListener('input', syncDateRange);
    dateFromInput?.addEventListener('change', syncDateRange);
    dateToInput?.addEventListener('input', syncDateRange);
    dateToInput?.addEventListener('change', syncDateRange);
    syncDateRange();

    // Back/Forward can restore a form selection changed after this page's data was rendered.
    window.addEventListener('pageshow', () => {
        filterSelect.value = activeFilter;
        if (dateFromInput) {
            dateFromInput.value = dateFromInput.dataset.activeDate || '';
        }
        if (dateToInput) {
            dateToInput.value = dateToInput.dataset.activeDate || '';
        }
        syncDateRange();
    });
}
