@props([
    'route',
    'filter',
    'periodLabel',
    'periodDescription',
    'note',
    'exportRoute' => null,
    'customRange' => false,
    'dateFrom' => '',
    'dateTo' => '',
])

@php
    $exportParameters = ['filter' => $filter];
    if ($dateFrom !== '' && $dateTo !== '') {
        $exportParameters['date_from'] = $dateFrom;
        $exportParameters['date_to'] = $dateTo;
    }
@endphp

<div class="card mb-4">
    <div class="card-body">
        <div class="{{ $customRange ? 'period-filter-layout' : 'd-flex flex-wrap justify-content-between align-items-center gap-3' }}">
            <div>
                <h5 class="mb-1" id="period-filter-title">Số liệu · {{ $periodLabel }}</h5>
                <div class="small text-muted" id="period-filter-description">{{ $periodDescription }}</div>
            </div>
            <form method="GET" action="{{ $route }}" id="period-filter-form" class="{{ $customRange ? '' : 'd-flex flex-wrap align-items-center gap-2' }}">
                @if($customRange)
                    <div class="period-filter-field">
                        <label for="period-filter-select" class="form-label mb-1">Thời gian</label>
                        <select name="filter" id="period-filter-select" class="form-select form-select-sm" data-active-filter="{{ $filter }}" data-allow-custom="true" aria-describedby="period-filter-description">
                            <option value="today" @selected($filter === 'today')>Hôm nay</option>
                            <option value="this_week" @selected($filter === 'this_week')>Tuần này</option>
                            <option value="this_month" @selected($filter === 'this_month')>Tháng này</option>
                            <option value="all_time" @selected($filter === 'all_time')>Toàn thời gian (All time)</option>
                        </select>
                    </div>
                @else
                    <label for="period-filter-select" class="text-nowrap mb-0">Thời gian</label>
                    <select name="filter" id="period-filter-select" class="form-select form-select-sm" data-active-filter="{{ $filter }}" data-allow-custom="false" aria-describedby="period-filter-description">
                        <option value="today" @selected($filter === 'today')>Hôm nay</option>
                        <option value="this_week" @selected($filter === 'this_week')>Tuần này</option>
                        <option value="this_month" @selected($filter === 'this_month')>Tháng này</option>
                        <option value="all_time" @selected($filter === 'all_time')>Toàn thời gian (All time)</option>
                    </select>
                @endif

                @if($customRange)
                    <div
                        id="period-date-range-fields"
                        class="period-date-range-fields {{ $filter === 'all_time' ? '' : 'd-none' }}"
                        aria-hidden="{{ $filter === 'all_time' ? 'false' : 'true' }}"
                    >
                        <div class="period-filter-field">
                            <label for="period-date-from" class="form-label mb-1">Từ ngày</label>
                            <input
                                type="date"
                                name="date_from"
                                id="period-date-from"
                                class="form-control form-control-sm"
                                value="{{ $dateFrom }}"
                                data-active-date="{{ $dateFrom }}"
                                aria-label="Từ ngày"
                                @disabled($filter !== 'all_time')
                            >
                        </div>
                        <div class="period-filter-field">
                            <label for="period-date-to" class="form-label mb-1">Đến ngày</label>
                            <input
                                type="date"
                                name="date_to"
                                id="period-date-to"
                                class="form-control form-control-sm"
                                value="{{ $dateTo }}"
                                data-active-date="{{ $dateTo }}"
                                aria-label="Đến ngày"
                                @disabled($filter !== 'all_time')
                            >
                        </div>
                        <button type="submit" id="period-filter-apply" class="btn btn-primary btn-sm">
                            Áp dụng
                        </button>
                    </div>
                @endif
                @if($exportRoute)
                    <a class="btn btn-outline-success btn-sm" href="{{ route($exportRoute, $exportParameters) }}">
                        <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Xuất Excel
                    </a>
                @endif
            </form>
        </div>
        <p class="small text-muted mb-0 mt-2">{{ $note }}</p>
    </div>
</div>
