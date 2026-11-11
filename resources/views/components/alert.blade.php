@props(['type' => 'info'])

@php
$styles = [
    'success' => 'border-forest bg-forest/5 text-forest',
    'error' => 'border-red-700 bg-red-50 text-red-700',
    'warning' => 'border-terracotta bg-terracotta/5 text-terracotta',
    'info' => 'border-forest bg-forest/5 text-forest',
];
$classes = $styles[$type] ?? $styles['info'];
@endphp

<div {{ $attributes->merge(['class' => 'border-l-4 px-5 py-4 font-inter text-sm rounded-r ' . $classes]) }}>
    {{ $slot }}
</div>
