@props([
    'route',
    'filter',
    'periodLabel',
    'periodDescription',
    'note',
    'exportRoute' => null,
    'customRange' => false,
    'reserveRangeSpace' => false,
    'dateFrom' => '',
    'dateTo' => '',
])

@once
    @push('styles')
        <style>
            .period-filter-layout .form-select-sm,
            .period-filter-layout .form-control-sm {
                font-size: 0.8125rem;
                padding: 0.375rem 0.75rem;
                border-radius: 8px;
                border-color: #d1d5db;
            }

            .period-filter-layout .form-select-sm:focus,
            .period-filter-layout .form-control-sm:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            }

            .period-filter-layout {
                display: grid;
                grid-template-columns: minmax(300px, 1fr) auto;
                align-items: center;
                gap: 0.75rem;
            }

            .period-filter-layout #period-filter-form {
                grid-column: 2;
                grid-row: 1;
                display: flex;
                flex-wrap: nowrap;
                align-items: end;
                gap: 0.5rem;
            }

            .period-filter-layout > div:first-child {
                grid-column: 1;
                grid-row: 1;
            }

            .period-filter-field {
                display: flex;
                flex: 0 0 200px;
                flex-direction: column;
                min-width: 0;
            }

            .period-filter-layout #period-filter-select {
                width: 100%;
            }

            .period-filter-layout #period-date-range-fields[aria-hidden="true"] {
                visibility: hidden;
            }

            .period-filter-layout > #period-filter-form > .period-filter-field {
                flex-basis: 180px;
            }

            .period-date-range-fields {
                display: flex;
                flex: 1 1 auto;
                flex-wrap: nowrap;
                align-items: end;
                gap: 0.5rem;
                min-width: 0;
            }

            .period-date-range-fields .period-filter-field {
                flex-basis: 170px;
            }

            .period-date-range-fields #period-date-from + input,
            .period-date-range-fields #period-date-to + input {
                width: 170px;
                height: 44px;
                min-height: 44px;
            }

            .period-filter-layout #period-filter-form #period-filter-apply,
            .period-filter-layout #period-filter-form > .btn-outline-success {
                display: inline-flex;
                flex: 0 0 102px;
                align-items: center;
                justify-content: center;
                width: 102px;
                height: 44px;
                padding: 6px 12px;
                white-space: nowrap;
            }

            .period-filter-layout #period-filter-form > .btn-outline-success {
                margin-left: 0.5rem;
            }

            @media (max-width: 1399.98px) {
                .period-filter-layout {
                    grid-template-columns: minmax(0, 1fr);
                }

                .period-filter-layout #period-filter-form {
                    grid-column: 1;
                    grid-row: 2;
                    flex-wrap: wrap;
                }

                .period-filter-field {
                    flex-basis: min(200px, 100%);
                }

                .period-date-range-fields {
                    flex-basis: 100%;
                    flex-wrap: wrap;
                    max-width: 100%;
                }
            }
        </style>
    @endpush
@endonce

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
                <h5 class="mb-1" id="period-filter-title">Số liệu - {{ $periodLabel }}</h5>
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
                        class="period-date-range-fields {{ $filter === 'all_time' || $reserveRangeSpace ? '' : 'd-none' }} {{ $reserveRangeSpace ? 'period-date-range-fields--reserved' : '' }}"
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
