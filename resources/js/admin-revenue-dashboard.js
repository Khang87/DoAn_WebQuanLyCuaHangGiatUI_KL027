const validFilters = new Set(['today', '7_days', 'this_month', 'this_year']);
const filterLabels = {
    today: 'Hôm nay',
    '7_days': '7 ngày gần nhất',
    this_month: 'Tháng này',
    this_year: 'Năm nay',
};
const filterSelect = document.getElementById('revenueFilter');
const revenueChart = window.adminRevenueChart;

if (filterSelect && revenueChart) {
    const title = document.getElementById('revenue-chart-title');
    const status = document.getElementById('revenue-filter-status');
    let activeFilter = validFilters.has(filterSelect.value) ? filterSelect.value : '7_days';
    let activeChartData = {
        labels: [...window.adminRevenueChartData.labels],
        data: [...window.adminRevenueChartData.data],
    };
    let requestId = 0;
    let activeRequest;
    let chartNeedsRestore = false;

    async function updateChart(chartData) {
        await revenueChart.updateOptions({
            series: [{name: 'Doanh thu', data: chartData.data}],
            xaxis: {categories: chartData.labels},
        });
    }

    async function restoreActiveChart() {
        try {
            await updateChart(activeChartData);
            chartNeedsRestore = false;

            return true;
        } catch (error) {
            console.error('Unable to restore the active revenue chart:', error);
            showStatus('Không thể khôi phục biểu đồ doanh thu. Vui lòng tải lại trang.', true);

            return false;
        }
    }

    function setUrlFilter(filter, method) {
        const url = new URL(window.location.href);

        [...url.searchParams.keys()].forEach(key => {
            if (key === 'filter' || key.startsWith('filter[')) {
                url.searchParams.delete(key);
            }
        });
        url.searchParams.set('filter', filter);
        window.history[method]({}, '', url.toString());
    }

    function urlFilter() {
        const url = new URL(window.location.href);
        const keys = [...url.searchParams.keys()].filter(key => key === 'filter' || key.startsWith('filter['));
        const values = url.searchParams.getAll('filter');

        if (keys.length === 0) {
            return {filter: '7_days', hasFilter: false, isValid: true};
        }

        if (keys.length !== 1 || values.length !== 1 || !validFilters.has(values[0])) {
            return {filter: '7_days', hasFilter: true, isValid: false};
        }

        return {filter: values[0], hasFilter: true, isValid: true};
    }

    function showStatus(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('text-danger', isError);
        status.classList.toggle('text-muted', !isError);
        status.hidden = message === '';
    }

    function restoreActiveFilter() {
        filterSelect.value = activeFilter;
        filterSelect.disabled = false;
    }

    async function loadFilter(filter, historyMode) {
        if (!validFilters.has(filter)) {
            return;
        }

        if (filter === activeFilter) {
            if (activeRequest) {
                activeRequest.abort();
                activeRequest = undefined;
                requestId++;
            }
            restoreActiveFilter();
            showStatus('');
            return;
        }

        activeRequest?.abort();
        const controller = new AbortController();
        const currentRequestId = ++requestId;
        activeRequest = controller;
        restoreActiveFilter();
        filterSelect.disabled = true;
        showStatus(`Đang tải doanh thu: ${filterLabels[filter]}...`);

        const endpoint = new URL(filterSelect.dataset.revenueChartUrl, window.location.href);
        endpoint.searchParams.set('filter', filter);

        try {
            const response = await fetch(endpoint, {
                headers: {Accept: 'application/json'},
                signal: controller.signal,
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(`Yêu cầu tải dữ liệu thất bại (HTTP ${response.status}).`);
            }

            if (
                data.filter !== filter
                || typeof data.filter_label !== 'string'
                || !Array.isArray(data.labels)
                || !Array.isArray(data.data)
                || data.labels.length !== data.data.length
                || !data.labels.every(label => typeof label === 'string')
                || !data.data.every(value => Number.isFinite(Number(value)))
            ) {
                throw new Error('Dữ liệu doanh thu trả về không hợp lệ.');
            }

            if (currentRequestId !== requestId) {
                return;
            }

            chartNeedsRestore = true;
            await updateChart(data);

            if (currentRequestId !== requestId) {
                if (chartNeedsRestore && !activeRequest) {
                    await restoreActiveChart();
                }
                return;
            }

            activeFilter = filter;
            activeChartData = {labels: [...data.labels], data: [...data.data]};
            chartNeedsRestore = false;
            filterSelect.value = filter;
            filterSelect.disabled = false;
            title.textContent = data.filter_label;
            showStatus('');

            if (historyMode === 'push') {
                setUrlFilter(filter, 'pushState');
            }
        } catch (error) {
            if (currentRequestId !== requestId) {
                if (chartNeedsRestore && !activeRequest) {
                    await restoreActiveChart();
                }
                return;
            }

            const chartRestored = !chartNeedsRestore || await restoreActiveChart();
            restoreActiveFilter();
            showStatus(
                chartRestored
                    ? `Không thể tải dữ liệu cho bộ lọc “${filterLabels[filter]}”. Bộ lọc trước đó vẫn được giữ.`
                    : 'Không thể khôi phục biểu đồ doanh thu. Vui lòng tải lại trang.',
                true
            );

            if (historyMode === 'pop') {
                setUrlFilter(activeFilter, 'replaceState');
            }
        } finally {
            if (currentRequestId === requestId) {
                activeRequest = undefined;
            }
        }
    }

    const initialUrlFilter = urlFilter();

    if (
        (initialUrlFilter.hasFilter && !initialUrlFilter.isValid)
        || initialUrlFilter.filter !== activeFilter
    ) {
        setUrlFilter(activeFilter, 'replaceState');
    }

    filterSelect.addEventListener('change', () => {
        const requestedFilter = filterSelect.value;
        restoreActiveFilter();
        return loadFilter(requestedFilter, 'push');
    });

    window.addEventListener('popstate', () => {
        const currentUrlFilter = urlFilter();

        if (!currentUrlFilter.isValid) {
            setUrlFilter(currentUrlFilter.filter, 'replaceState');
        }

        return loadFilter(currentUrlFilter.filter, 'pop');
    });
}
