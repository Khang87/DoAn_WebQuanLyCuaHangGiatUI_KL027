@extends('layouts.app')

@section('title', 'Tin nhắn khách hàng - Sky Laundry')
@section('page-title', 'Tin nhắn khách hàng')

@push('styles')
<style>
    .message-chat-card { display: flex; flex-direction: column; height: min(48rem, calc(100vh - 10rem)); min-height: 30rem; }
    .message-chat-history { flex: 1; min-height: 0; overflow-y: auto; }
    .message-inbox-list { max-height: min(35rem, calc(100vh - 19rem)); overflow-y: auto; }
    .message-inbox-preview { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    @media (max-width: 991.98px) {
        .message-chat-card { height: 36rem; }
        .message-inbox-list { max-height: 19rem; }
    }
</style>
@endpush

@section('content')
@can('messages.view')
@php($selectedProfile = $selectedCustomer?->khachHang ?: $selectedOrder?->khachHang)
<div class="row g-3 align-items-start">
    <div class="col-12 col-md-4 col-xl-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-3 fw-semibold"><i class="bi bi-chat-dots me-2"></i>Hội thoại</h5>
                <form action="{{ route('admin.messages.index') }}" method="GET" role="search" class="input-group input-group-sm">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <input type="search" name="q" value="{{ $search }}" class="form-control" maxlength="100" placeholder="Tên, SĐT hoặc mã đơn" aria-label="Tìm hội thoại">
                    <button class="btn btn-outline-primary" type="submit" aria-label="Tìm kiếm"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <nav class="nav nav-pills nav-fill gap-1 p-2 border-bottom" aria-label="Loại hội thoại">
                <a class="nav-link {{ $activeTab === 'orders' ? 'active' : '' }}" href="{{ route('admin.messages.index', ['tab' => 'orders', 'q' => $search]) }}" @if($activeTab === 'orders') aria-current="page" @endif>Đơn hàng</a>
                <a class="nav-link {{ $activeTab === 'support' ? 'active' : '' }}" href="{{ route('admin.messages.index', ['tab' => 'support', 'q' => $search]) }}" @if($activeTab === 'support') aria-current="page" @endif>Tư vấn</a>
            </nav>
            @if($activeTab === 'orders')
            <div class="list-group list-group-flush message-inbox-list" data-message-inbox="{{ route('admin.messages.inbox', ['page' => $orders->currentPage(), 'q' => $search]) }}" data-selected-order="{{ $selectedOrder?->DonHangID }}" data-message-link="{{ route('admin.messages.index', ['q' => $search]) }}">
                @forelse($orders as $order)
                    <a href="{{ route('admin.messages.index', ['order_id' => $order->DonHangID, 'q' => $search]) }}"
                        data-inbox-order="{{ $order->DonHangID }}" class="list-group-item list-group-item-action {{ $selectedOrder?->DonHangID === $order->DonHangID ? 'active' : '' }}">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold text-truncate">{{ $order->khachHang?->HoTen ?: 'Khách hàng' }} @if($order->unread_count > 0)<span class="d-inline-block rounded-circle bg-danger ms-2" style="width:10px;height:10px" role="img" aria-label="Tin nhắn chưa đọc" data-unread-dot></span>@endif</span>
                            <small>{{ $order->last_message_at ? \Carbon\Carbon::parse($order->last_message_at, 'UTC')->setTimezone(config('app.timezone'))->format('d-m-Y') : $order->NgayTao?->format('d-m-Y') }}</small>
                        </div>
                        <small class="d-block">{{ $order->MaDonHang }}</small>
                        <small class="d-block message-inbox-preview">{{ $order->last_message_preview ?: 'Chưa có tin nhắn' }}</small>
                    </a>
                @empty
                    <div class="text-center text-muted p-4">Không tìm thấy hội thoại đơn hàng.</div>
                @endforelse
            </div>
            @if($orders->hasPages())<div class="card-footer bg-white">{{ $orders->links() }}</div>@endif
            @else
            <div class="list-group list-group-flush message-inbox-list">
                @forelse($supportCustomers as $customer)
                    <a class="list-group-item list-group-item-action {{ $selectedCustomer?->getKey() === $customer->getKey() ? 'active' : '' }}" href="{{ route('admin.messages.index', ['customer_id' => $customer->getKey(), 'q' => $search]) }}">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-semibold text-truncate">{{ $customer->name }}</span>
                            <small>{{ $customer->last_message_at ? \Carbon\Carbon::parse($customer->last_message_at, 'UTC')->setTimezone(config('app.timezone'))->format('d-m-Y') : '' }}</small>
                        </div>
                        <small class="d-block">{{ $customer->khachHang?->SoDienThoai ?: 'Tư vấn khách hàng' }}</small>
                        <small class="d-block message-inbox-preview">{{ $customer->last_message_preview ?: 'Chưa có tin nhắn' }}</small>
                    </a>
                @empty
                    <div class="text-center text-muted p-4">Không tìm thấy khách hàng tư vấn.</div>
                @endforelse
            </div>
            @if($supportCustomers->hasPages())<div class="card-footer bg-white">{{ $supportCustomers->links() }}</div>@endif
            @endif
        </div>
    </div>

    <div class="col-12 col-md-8 col-xl-6">
        <div class="card border-0 shadow-sm message-chat-card">
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
                <div class="card-body bg-light message-chat-history"
                    data-message-read="{{ route('admin.messages.read') }}"
                    data-message-realtime="{{ route('admin.messages.realtime') }}"
                    data-message-updates="{{ $selectedCustomer ? route('admin.messages.support-updates', $selectedCustomer->getKey()) : route('admin.messages.updates', $selectedOrder->DonHangID) }}"
                    data-message-hide="{{ route('admin.messages.hide', ['message' => 0]) }}"
                    data-message-recall="{{ route('admin.messages.recall', ['message' => 0]) }}"
                    data-message-edit="{{ route('admin.messages.edit', ['message' => 0]) }}"
                    data-message-image-upload="{{ route('admin.messages.image-upload-url') }}"
                    data-message-can-manage="{{ auth()->user()->can('messages.create') ? '1' : '0' }}"
                    data-order-id="{{ $selectedOrder?->DonHangID }}" data-customer-account-id="{{ $selectedCustomer?->getKey() }}" aria-label="Nội dung cuộc trò chuyện">
                    @forelse($messages as $message)
                        @php($isMine = (int) $message->NguoiGuiID === (int) auth()->id())
                        <div class="d-flex {{ $isMine ? 'justify-content-end' : 'justify-content-start' }} mb-3" data-message-id="{{ $message->TinNhanID }}" tabindex="0">
                            <div class="rounded-3 px-3 py-2 {{ $isMine ? 'bg-primary text-white' : 'bg-white border' }}" style="max-width: 82%;">
                                <div class="small {{ $isMine ? 'text-white-50' : 'text-muted' }}">
                                    {{ app(\App\Services\MessageService::class)->displayName($message, auth()->user()) }}
                                </div>
                                @if($message->ImageUrl ?? false)
                                    <a href="{{ $message->ImageUrl }}" target="_blank" rel="noopener noreferrer"><img src="{{ $message->ImageUrl }}" alt="Ảnh đính kèm" class="img-fluid rounded mb-1" style="max-height:240px"></a>
                                @else
                                    <div class="text-break">{{ $message->NoiDung }}</div>
                                @endif
                                <div class="small text-end {{ $isMine ? 'text-white-50' : 'text-muted' }}">
                                    {{ app(\App\Services\MessageService::class)->sentAt($message)?->format('d-m-Y H:i') }}
                                </div>
                                @can('messages.create')
                                    <div class="message-actions d-flex justify-content-end gap-2 mt-1" data-message-actions>
                                        @if($isMine && !($message->ImageUrl ?? false))<button type="button" class="btn btn-sm btn-link p-0 {{ $isMine ? 'text-white' : '' }}" data-message-edit="{{ $message->TinNhanID }}">Sửa</button>@endif
                                        @if($isMine)<button type="button" class="btn btn-sm btn-link p-0 text-white" data-message-recall="{{ $message->TinNhanID }}">Thu hồi</button>@endif
                                        <button type="button" class="btn btn-sm btn-link p-0 {{ $isMine ? 'text-white' : '' }}" data-message-hide="{{ $message->TinNhanID }}">Xóa</button>
                                    </div>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <div class="h-100 d-flex align-items-center justify-content-center text-muted">
                            Chưa có tin nhắn. Hãy gửi lời chào tới khách hàng.
                        </div>
                    @endforelse
                </div>
                @can('messages.create')
                <div class="card-footer bg-white">
                    @if(!$selectedCustomer && !$selectedOrder?->khachHang?->taiKhoan)
                        <div class="alert alert-warning mb-0">Khách hàng chưa có tài khoản nhận tin nhắn.</div>
                    @else
                        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.messages.store') }}" class="d-flex flex-column gap-2" data-message-form>
                            @csrf
                            @if($selectedCustomer)
                                <input type="hidden" name="customer_id" value="{{ $selectedCustomer->getKey() }}">
                            @else
                                <input type="hidden" name="order_id" value="{{ $selectedOrder->DonHangID }}">
                            @endif
                            <div class="d-flex flex-wrap gap-2" aria-label="Mẫu trả lời nhanh">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-message-template="Xin chào, cửa hàng đã nhận tin nhắn của bạn.">Chào khách</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-message-template="Bạn vui lòng cho biết mã đơn hàng để cửa hàng kiểm tra nhé.">Hỏi mã đơn</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-message-template="Cửa hàng sẽ kiểm tra và phản hồi bạn sớm.">Hẹn phản hồi</button>
                            </div>
                            <div class="d-flex gap-2">
                                <textarea name="content" rows="2" maxlength="1000" class="form-control" placeholder="Nhập tin nhắn cho khách hàng...">{{ old('content') }}</textarea>
                                <label class="btn btn-outline-secondary align-self-end mb-0" title="Chọn ảnh">
                                    <i class="bi bi-image"></i><span class="visually-hidden">Chọn ảnh</span>
                                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="d-none" data-message-image>
                                </label>
                                <button type="submit" class="btn btn-primary align-self-end">
                                    <i class="bi bi-send me-1"></i>Gửi
                                </button>
                            </div>
                            <small class="text-muted">Ảnh JPEG, PNG hoặc WEBP tối đa 5 MB. Ảnh sẽ hiện ngay trong khung chat khi bắt đầu gửi.</small>
                        </form>
                        @error('content')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        @error('message')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @endif
                </div>
                @endcan
            @else
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-chat-square-text display-5 d-block mb-3"></i>
                    Chọn một hội thoại để xem và trả lời tin nhắn.
                </div>
            @endif
        </div>
    </div>
    <aside class="col-12 col-xl-3" aria-label="Thông tin cuộc trò chuyện">
        @if($selectedOrder || $selectedCustomer)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Thông tin khách hàng</h6></div>
            <div class="card-body small">
                <div class="fw-semibold mb-2">{{ $selectedProfile?->HoTen ?: ($selectedCustomer?->name ?: 'Khách hàng') }}</div>
                <div class="mb-2"><span class="text-muted">Số điện thoại:</span> {{ $selectedProfile?->SoDienThoai ?: 'Chưa cập nhật' }}</div>
                <div><span class="text-muted">Địa chỉ:</span> {{ $selectedProfile?->DiaChi ?: 'Chưa cập nhật' }}</div>
            </div>
        </div>
        @if($selectedOrder)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold">Đơn {{ $selectedOrder->MaDonHang }}</h6></div>
            <div class="card-body small">
                <div class="mb-3"><x-admin.order-status-badges :order="$selectedOrder" :is-paid="$selectedOrder->isPaid()" :is-receiving="false" compact /></div>
                <div class="d-flex justify-content-between gap-2 mb-3"><span class="text-muted">Giá trị đơn</span><strong>{{ number_format((float) $selectedOrder->ThanhTien) }} đ</strong></div>
                <a href="{{ route('orders.show', $selectedOrder) }}" class="btn btn-sm btn-outline-primary w-100">Xem chi tiết đơn</a>
            </div>
        </div>
        @endif
        @else
        <div class="card border-0 shadow-sm"><div class="card-body text-muted small py-4">Thông tin khách hàng và đơn hàng sẽ hiển thị khi bạn chọn hội thoại.</div></div>
        @endif
    </aside>
</div>
@endcan
@endsection

@push('scripts')
@can('messages.view')
    @vite('resources/js/message-inbox.js')
    @if($selectedOrder || $selectedCustomer)
        @vite(['resources/js/message-updates.js', 'resources/js/message-send-guard.js'])
    @endif
@endcan
@endpush

@push('styles')
<style>
    .message-chat-history [data-message-actions] { display: none !important; }
    .message-chat-history [data-message-id][data-actions-open="true"] [data-message-actions] { display: flex !important; }
</style>
@endpush
