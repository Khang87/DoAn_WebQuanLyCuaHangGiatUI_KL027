@props(['route', 'filter', 'periodLabel', 'periodDescription', 'note', 'exportRoute' => null])

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="mb-1" id="period-filter-title">Số liệu · {{ $periodLabel }}</h5>
                <div class="small text-muted" id="period-filter-description">{{ $periodDescription }}</div>
            </div>
            <form method="GET" action="{{ $route }}" id="period-filter-form" class="d-flex align-items-center gap-2">
                <label for="period-filter-select" class="text-nowrap mb-0">Thời gian</label>
                <select name="filter" id="period-filter-select" class="form-select form-select-sm" data-active-filter="{{ $filter }}" aria-describedby="period-filter-description">
                    <option value="today" @selected($filter === 'today')>Hôm nay</option>
                    <option value="this_week" @selected($filter === 'this_week')>Tuần này</option>
                    <option value="this_month" @selected($filter === 'this_month')>Tháng này</option>
                    <option value="all_time" @selected($filter === 'all_time')>Toàn thời gian (All time)</option>
                </select>
            </form>
            @if($exportRoute)
                <a class="btn btn-outline-success btn-sm" href="{{ route($exportRoute, ['filter' => $filter]) }}">
                    <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Xuất Excel
                </a>
            @endif
        </div>
        <p class="small text-muted mb-0 mt-2">{{ $note }}</p>
    </div>
</div>
