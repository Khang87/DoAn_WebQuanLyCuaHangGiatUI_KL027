@extends('layouts.app')
@section('title', 'Hóa đơn - Sky Laundry')
@section('page-title', 'Hóa đơn')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    @can('invoices.create')
        <a href="{{ route('invoices.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm hóa đơn
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Danh sách chỉ hiển thị hóa đơn đã thanh toán. Hóa đơn còn chờ thu được theo dõi và thu tiền tại trang Đơn hàng.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden filter-search">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm kiếm..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <input type="date" name="date_from" class="form-control form-control-sm shadow-sm rounded-3 filter-date" value="{{ request('date_from') }}" placeholder="Từ ngày">
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <input type="date" name="date_to" class="form-control form-control-sm shadow-sm rounded-3 filter-date" value="{{ request('date_to') }}" placeholder="Đến ngày">
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th scope="col">STT</th>
                        <th>Mã hóa đơn</th>
                        <th>Khách hàng</th>
                        <th>Mã đơn hàng</th>
                        <th>Tạm tính</th>
                        <th>Giảm giá</th>
                        <th>Phí giao hàng</th>
                        <th>Tổng thanh toán</th>
                        <th>Ngày lập</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td>{{ $invoices->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $invoice->MaHoaDon }}</strong></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <x-admin.customer-avatar :customer="$invoice->donHang?->khachHang" />
                                <span>{{ $invoice->donHang?->khachHang?->HoTen ?: '-' }}</span>
                            </div>
                        </td>
                        <td>
                            @if($invoice->donHang)
                                {{ $invoice->donHang->MaDonHang }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ number_format($invoice->TongTien) }} VNĐ</td>
                        <td class="text-danger">{{ number_format($invoice->GiamGia) }} VNĐ</td>
                        <td>{{ number_format($invoice->PhiGiaoHang) }} VNĐ</td>
                        <td class="fw-semibold text-dark">{{ number_format($invoice->ThanhTien) }} VNĐ</td>
                        <td>{{ $invoice->NgayLap?->format('d/m/Y') }}</td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center justify-content-start gap-2 flex-wrap">
                                <x-admin.status-badge :status="$invoice->TrangThai" :enum="\App\Enums\InvoiceStatus::class" />
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                @can('invoices.view')
                                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @endcan
                                @can('invoices.view')
                                    <button type="button" data-invoice-print="{{ route('invoices.show', $invoice) }}" class="btn btn-order-action view" title="In hóa đơn" aria-label="In hóa đơn"><i class="bi bi-printer" aria-hidden="true"></i></button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">Chưa có hóa đơn nào</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="invoice-print-error" class="alert alert-danger mt-3" role="alert" hidden></div>
<x-admin.pagination :paginator="$invoices" label="hóa đơn" />
@endsection

@push('scripts')
<script>
    (() => {
        let activeFrame = null;
        const errorMessage = document.getElementById('invoice-print-error');

        document.querySelectorAll('[data-invoice-print]').forEach((button) => {
            button.addEventListener('click', () => {
                if (activeFrame) return;

                errorMessage.hidden = true;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');

                const frame = document.createElement('iframe');
                activeFrame = frame;
                frame.title = 'Bản in hóa đơn';
                frame.setAttribute('aria-hidden', 'true');
                frame.tabIndex = -1;
                frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:210mm;height:297mm;border:0;';

                const cleanup = () => {
                    clearTimeout(timeout);
                    frame.remove();
                    activeFrame = null;
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                    button.focus({ preventScroll: true });
                };
                const fail = () => {
                    if (activeFrame !== frame) return;
                    cleanup();
                    errorMessage.textContent = 'Không thể tải hóa đơn để in. Vui lòng thử lại.';
                    errorMessage.hidden = false;
                };
                const timeout = setTimeout(fail, 30000);

                frame.addEventListener('load', async () => {
                    try {
                        const printWindow = frame.contentWindow;
                        if (!printWindow.document.querySelector('.invoice-print-heading')) {
                            fail();
                            return;
                        }

                        await printWindow.document.fonts.ready;
                        if (activeFrame !== frame) return;

                        clearTimeout(timeout);
                        printWindow.addEventListener('afterprint', cleanup, { once: true });
                        printWindow.print();
                    } catch {
                        fail();
                    }
                }, { once: true });
                frame.addEventListener('error', fail, { once: true });
                frame.src = button.dataset.invoicePrint;
                document.body.appendChild(frame);
            });
        });
    })();
</script>
@endpush
