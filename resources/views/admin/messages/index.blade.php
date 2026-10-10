@extends('layouts.app')

@section('title', 'Tin nhắn khách hàng - Sky Laundry')
@section('page-title', 'Tin nhắn khách hàng')

@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-semibold"><i class="bi bi-chat-dots me-2"></i>Đơn hàng</h5>
            </div>
            <div class="list-group list-group-flush">
                @forelse($orders as $order)
                    <a href="{{ route('admin.messages.index', ['order_id' => $order->DonHangID]) }}"
                        class="list-group-item list-group-item-action {{ $selectedOrder?->DonHangID === $order->DonHangID ? 'active' : '' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold">{{ $order->MaDonHang }}</span>
                            <small>{{ $order->NgayTao?->format('d-m-Y') }}</small>
                        </div>
                        <small>{{ $order->khachHang?->HoTen ?: 'Khách hàng' }}</small>
                    </a>
                @empty
                    <div class="text-center text-muted p-4">Chưa có đơn hàng.</div>
                @endforelse
            </div>
            <div class="card-header bg-white"><h6 class="mb-0">Hỗ trợ trước khi đặt hàng</h6></div>
            <div class="list-group list-group-flush">
                @foreach($supportCustomers as $customer)
                    <a class="list-group-item list-group-item-action {{ $selectedCustomer?->getKey() === $customer->getKey() ? 'active' : '' }}" href="{{ route('admin.messages.index', ['customer_id' => $customer->getKey()]) }}">{{ $customer->name }}</a>
                @endforeach
            </div>
            <div class="card-footer bg-white">{{ $supportCustomers->links() }}</div>
            @if($orders->hasPages())
                <div class="card-footer bg-white">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            @if($selectedOrder || $selectedCustomer)
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1 fw-semibold">{{ $selectedCustomer ? ('Hỗ trợ: '.$selectedCustomer->name) : ('Đơn '.$selectedOrder->MaDonHang) }}</h5>
                        <small class="text-muted">{{ $selectedCustomer?->name ?: ($selectedOrder?->khachHang?->HoTen ?: 'Khách hàng') }}</small>
                        <small class="d-block text-muted" data-message-status role="status">Đang đồng bộ tin nhắn…</small>
                    </div>
                    @if($selectedOrder)<a href="{{ route('orders.show', $selectedOrder) }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Chi tiết đơn
                    </a>@endif
                </div>
                <div class="card-body bg-light" style="min-height: 360px; max-height: 520px; overflow-y: auto; position: relative;"
                    data-message-realtime="{{ route('admin.messages.realtime') }}"
                    data-message-updates="{{ $selectedCustomer ? route('admin.messages.support-updates', $selectedCustomer->getKey()) : route('admin.messages.updates', $selectedOrder->DonHangID) }}"
                    data-order-id="{{ $selectedOrder?->DonHangID }}" data-customer-account-id="{{ $selectedCustomer?->getKey() }}" aria-label="Nội dung cuộc trò chuyện">
                    @forelse($messages as $message)
                        @php($isMine = (int) $message->NguoiGuiID === (int) auth()->id())
                        <div class="d-flex {{ $isMine ? 'justify-content-end' : 'justify-content-start' }} mb-3" data-message-id="{{ $message->TinNhanID }}">
                            <div class="rounded-3 px-3 py-2 {{ $isMine ? 'bg-primary text-white' : 'bg-white border' }}" style="max-width: 82%;">
                                <div class="small {{ $isMine ? 'text-white-50' : 'text-muted' }}">
                                    {{ app(\App\Services\MessageService::class)->displayName($message, auth()->user()) }}
                                </div>
                                <div class="text-break">{{ $message->NoiDung }}</div>
                                <div class="small text-end {{ $isMine ? 'text-white-50' : 'text-muted' }}">
                                    {{ app(\App\Services\MessageService::class)->sentAt($message)?->format('d-m-Y H:i') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="h-100 d-flex align-items-center justify-content-center text-muted">
                            Chưa có tin nhắn. Hãy gửi lời chào tới khách hàng.
                        </div>
                    @endforelse
                </div>
                <div class="card-footer bg-white">
                    @if(!$selectedCustomer && !$selectedOrder?->khachHang?->taiKhoan)
                        <div class="alert alert-warning mb-0">Khách hàng chưa có tài khoản nhận tin nhắn.</div>
                    @else
                        <form method="POST" action="{{ route('admin.messages.store') }}" class="d-flex gap-2" data-message-form>
                            @csrf
                            @if($selectedCustomer)
                                <input type="hidden" name="customer_id" value="{{ $selectedCustomer->getKey() }}">
                            @else
                                <input type="hidden" name="order_id" value="{{ $selectedOrder->DonHangID }}">
                            @endif
                            <textarea name="content" rows="2" maxlength="1000" required class="form-control" placeholder="Nhập tin nhắn cho khách hàng...">{{ old('content') }}</textarea>
                            <button type="submit" class="btn btn-primary align-self-end">
                                <i class="bi bi-send me-1"></i>Gửi
                            </button>
                        </form>
                        @error('content')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        @error('message')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @endif
                </div>
            @else
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-chat-square-text display-5 d-block mb-3"></i>
                    Chọn một đơn hàng để xem và trả lời tin nhắn.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @if($selectedOrder || $selectedCustomer)
        @vite(['resources/js/message-updates.js', 'resources/js/message-send-guard.js'])
    @endif
@endpush
