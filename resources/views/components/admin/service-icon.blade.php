@props([
    'icon',
    'class' => '',
])

@php
    $glyph = \App\Support\ServiceIcon::glyph($icon);
@endphp

@if($glyph !== null)
    <svg {{ $attributes->merge(['class' => $class]) }} data-icon-glyph viewBox="0 0 512 512" style="width: 1em; height: 1em; vertical-align: -0.125em;" fill="currentColor" aria-hidden="true">
        <path transform="translate(0 512) scale(1 -1)" d="{{ $glyph }}"/>
    </svg>
@else
    <i {{ $attributes->merge(['class' => trim($icon.' '.$class)]) }} aria-hidden="true"></i>
@endif
