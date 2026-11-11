@props(['variant' => 'primary', 'type' => 'button', 'href' => null])

@php
$base = 'inline-flex h-14 items-center justify-center gap-3 px-6 font-inter font-medium text-[15px] rounded transition-colors whitespace-nowrap cursor-pointer';
$variants = [
    'primary' => 'bg-forest text-white hover:bg-forest-dark',
    'outline' => 'border border-forest text-forest hover:bg-forest hover:text-white bg-transparent',
    'ghost' => 'text-forest hover:underline bg-transparent',
];
$classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
