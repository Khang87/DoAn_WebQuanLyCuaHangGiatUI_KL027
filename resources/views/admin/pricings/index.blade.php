@extends('layouts.app')
@section('title', 'Bảng giá - Sky Laundry')
@section('page-title', 'Bảng giá')
@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    @can('pricings.create')
        <a href="{{ route('pricings.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm bảng giá
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Giá dịch vụ theo loại đồ và đơn vị tính.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4" data-live-search-form>
    <div class="col-12 col-md-auto flex-grow-1">
        <x-admin.search-field placeholder="Tìm theo dịch vụ, loại đồ..." label="Tìm kiếm bảng giá" :live="true" />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="service_id" id="filter-service" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả dịch vụ</option>
            @foreach($services as $service)
                <option value="{{ $service->DichVuID }}" @selected((string) request('service_id') === (string) $service->DichVuID)>{{ $service->TenDichVu }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="garment_id" id="filter-garment" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả loại đồ</option>
            @foreach($garments as $garment)
                <option value="{{ $garment->LoaiDoGiatID }}" @selected((string) request('garment_id') === (string) $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statuses"
            placeholder="Tất cả trạng thái"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
</form>

<div id="pricing-results" aria-live="polite" aria-busy="false">
    @include('admin.pricings.partials.list')
</div>

<p id="pricing-search-error" class="alert alert-danger mt-3" role="alert" hidden></p>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.querySelector('[data-live-search-form]');
        const searchInput = searchForm?.querySelector('[data-live-search-input]');
        const results = document.getElementById('pricing-results');
        const errorMessage = document.getElementById('pricing-search-error');

        if (searchForm && searchInput && results && errorMessage) {
            let activeRequest = null;
            let requestSequence = 0;

            function searchPricings() {
                if (activeRequest) {
                    activeRequest.abort();
                }

                activeRequest = new AbortController();
                const currentRequest = ++requestSequence;
                const params = new URLSearchParams(new FormData(searchForm));
                params.delete('page');
                const emptyKeys = [];
                params.forEach(function(value, key) {
                    if (value === '') {
                        emptyKeys.push(key);
                    }
                });
                emptyKeys.forEach(function(key) {
                    params.delete(key);
                });

                const url = new URL(searchForm.action, window.location.href);
                url.search = params.toString();
                results.setAttribute('aria-busy', 'true');
                errorMessage.hidden = true;
                errorMessage.textContent = '';

                fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: activeRequest.signal,
                })
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('Không thể tìm kiếm bảng giá. Vui lòng thử lại.');
                        }

                        return response.json();
                    })
                    .then(function(data) {
                        if (currentRequest !== requestSequence) {
                            return;
                        }
                        if (typeof data.html !== 'string') {
                            throw new Error('Dữ liệu tìm kiếm bảng giá không hợp lệ.');
                        }

                        results.innerHTML = data.html;
                        history.replaceState(null, '', url);
                    })
                    .catch(function(error) {
                        if (error.name === 'AbortError' || currentRequest !== requestSequence) {
                            return;
                        }

                        errorMessage.textContent = error.message || 'Không thể tìm kiếm bảng giá. Vui lòng thử lại.';
                        errorMessage.hidden = false;
                    })
                    .finally(function() {
                        if (currentRequest === requestSequence) {
                            results.setAttribute('aria-busy', 'false');
                        }
                    });
            }

            searchInput.addEventListener('input', function(event) {
                if (!event.isComposing) {
                    searchPricings();
                }
            });
            searchInput.addEventListener('compositionend', searchPricings);
            searchForm.addEventListener('submit', function(event) {
                event.preventDefault();
                searchPricings();
            });
        }

        document.addEventListener('submit', function(e) {
            const form = e.target.closest('[id^="deletePricingForm_"]');
            if (!form) {
                return;
            }

            e.preventDefault();

            if (typeof Swal === 'undefined') {
                if (confirm('Bạn có chắc muốn xóa?')) {
                    form.submit();
                }
                return;
            }

            Swal.fire({
                title: 'Xóa bảng giá?',
                text: 'Hành động này không thể hoàn tác.',
                icon: 'warning',
                showCancelButton: true,
                reverseButtons: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush