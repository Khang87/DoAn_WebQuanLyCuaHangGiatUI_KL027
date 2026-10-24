@props(['customer' => null, 'size' => 40, 'url' => null, 'name' => null])

@php
    $avatarUrl = $url ?: (($customer?->getAttributes()['AvatarUrl'] ?? null) ?: $customer?->taiKhoan?->AvatarURL);
    $customerName = trim($name ?? $customer?->HoTen ?? '');
    $initial = mb_strtoupper(mb_substr($customerName, 0, 1));
@endphp

<span {{ $attributes->class(['rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0 overflow-hidden fw-bold']) }}
      style="width: {{ (int) $size }}px; height: {{ (int) $size }}px;"
      role="img" aria-label="Ảnh đại diện{{ $customerName !== '' ? ' của '.$customerName : ' khách hàng' }}">
    <span aria-hidden="true" @if($avatarUrl) hidden @endif>
        @if($initial !== '')
            {{ $initial }}
        @else
            <i class="bi bi-person"></i>
        @endif
    </span>
    @if($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="" class="w-100 h-100 avatar-cover"
             onerror="this.onerror=null; this.previousElementSibling.hidden=false; this.remove();">
    @endif
</span>
