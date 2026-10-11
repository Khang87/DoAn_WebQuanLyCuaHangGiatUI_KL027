@props([
    'amount' => 0,
    'memo' => '',
    'idPrefix' => 'vietqr',
])

@php
    $bankName = config('services.vietqr.bank_name');
    $accountNumber = config('services.vietqr.account_number');
    $accountName = config('services.vietqr.account_name');
    $quicklinkUrl = config('services.vietqr.quicklink_url');
    $quicklinkParts = parse_url($quicklinkUrl);
    $quicklinkQuery = [];

    parse_str($quicklinkParts['query'] ?? '', $quicklinkQuery);
    $quicklinkQuery['accountName'] = $accountName ?: ($quicklinkQuery['accountName'] ?? '');
    $quicklinkQuery['amount'] = (int) $amount;
    $quicklinkQuery['addInfo'] = $memo;
    $qrImageUrl = ($quicklinkParts['scheme'] ?? 'https').'://'.($quicklinkParts['host'] ?? 'api.vietqr.io')
        .($quicklinkParts['path'] ?? '')
        .'?'.http_build_query($quicklinkQuery, '', '&', PHP_QUERY_RFC3986);
@endphp

<div class="vietqr-payment">
    <div class="row align-items-center justify-content-center g-4">
        <div class="col-sm-auto text-center">
            <img
                src="{{ $qrImageUrl }}"
                alt="Mã QR thanh toán VietQR"
                data-quicklink-base="{{ $qrImageUrl }}"
                id="{{ $idPrefix }}-image"
                class="img-fluid border rounded p-2 bg-white"
                style="width: min(100%, 260px);"
                width="260"
                height="260"
            >
            <div class="small text-muted mt-2">Quét bằng ứng dụng ngân hàng</div>
        </div>
        <div class="col">
            <dl class="row mb-0">
                <dt class="col-sm-5">Ngân hàng</dt>
                <dd class="col-sm-7">{{ $bankName ?: 'Chưa cấu hình' }}</dd>
                <dt class="col-sm-5">Số tài khoản</dt>
                <dd class="col-sm-7">{{ $accountNumber ?: 'Chưa cấu hình' }}</dd>
                <dt class="col-sm-5">Chủ tài khoản</dt>
                <dd class="col-sm-7">{{ $accountName ?: 'Chưa cấu hình' }}</dd>
                <dt class="col-sm-5">Số tiền cần chuyển</dt>
                <dd class="col-sm-7 fw-bold text-primary" id="{{ $idPrefix }}-amount">
                    {{ number_format((float) $amount, 0, ',', '.') }} đ
                </dd>
                <dt class="col-sm-5">Nội dung chuyển khoản</dt>
                <dd class="col-sm-7 fw-semibold text-break" id="{{ $idPrefix }}-memo">{{ $memo }}</dd>
            </dl>
            <p class="small text-muted mb-0">
                Đối chiếu đúng số tiền và nội dung chuyển khoản trước khi xác nhận giao dịch.
            </p>
        </div>
    </div>
</div>
