const filterSelect = document.getElementById('period-filter-select');
const filterForm = document.getElementById('period-filter-form');

if (filterSelect && filterForm) {
    const activeFilter = filterSelect.dataset.activeFilter;
    const validFilters = new Set(['today', 'this_week', 'this_month', 'all_time']);
    const url = new URL(window.location.href);
    const filterKeys = [...url.searchParams.keys()].filter(key => key === 'filter' || key.startsWith('filter['));

    if (filterKeys.length && (filterKeys.length !== 1 || url.searchParams.get('filter') !== activeFilter)) {
        filterKeys.forEach(key => url.searchParams.delete(key));
        url.searchParams.set('filter', activeFilter);
        window.history.replaceState(null, '', url.toString());
    }

    filterSelect.addEventListener('change', () => {
        if (validFilters.has(filterSelect.value) && filterSelect.value !== activeFilter) {
            filterForm.requestSubmit();
        } else {
            filterSelect.value = activeFilter;
        }
    });

    // Back/Forward can restore a form selection changed after this page's data was rendered.
    window.addEventListener('pageshow', () => {
        filterSelect.value = activeFilter;
    });
}
