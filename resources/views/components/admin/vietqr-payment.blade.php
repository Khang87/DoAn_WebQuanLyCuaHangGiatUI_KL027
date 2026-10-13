@props([
    'amount' => 0,
    'memo' => '',
    'idPrefix' => 'vietqr',
])

@php
    $bankName = config('services.vietqr.bank_name');
    $accountNumber = config('services.vietqr.account_number');
    $accountName = config('services.vietqr.account_name');
    $bankBin = config('services.vietqr.bank_bin');
    $isConfigured = $bankBin === '970422' && ctype_digit((string) $accountNumber)
        && (string) $accountNumber !== '' && trim((string) $accountName) !== '';
    $qrImageUrl = $isConfigured
        ? 'https://img.vietqr.io/image/'.$bankBin.'-'.$accountNumber.'-compact2.png?'
            .http_build_query([
                'accountName' => $accountName,
                'amount' => (int) $amount,
                'addInfo' => $memo,
            ], '', '&', PHP_QUERY_RFC3986)
        : null;
@endphp

<div class="vietqr-payment">
    <div class="row align-items-center justify-content-center g-4">
        @if($qrImageUrl)
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
        @endif
        <div class="col">
            @unless($isConfigured)
                <p class="alert alert-warning">Chưa cấu hình tài khoản nhận MB Bank. Không thể tạo mã QR thanh toán.</p>
            @endunless
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
