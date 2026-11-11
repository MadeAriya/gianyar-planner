@props(['current' => 1, 'total' => 2, 'next_label' => null])

<div class="flex items-start justify-between gap-8 mb-12">
    <div class="flex-1 max-w-lg">
        <p class="font-inter font-semibold text-terracotta text-xs tracking-[2.20px] uppercase">
            LANGKAH {{ $current }} DARI {{ $total }}
        </p>
        <div class="mt-3 h-1 w-full bg-line rounded-full overflow-hidden">
            <div class="h-full bg-forest rounded-full transition-all" style="width: {{ ($current / $total) * 100 }}%"></div>
        </div>
    </div>
    @if($next_label)
        <p class="font-inter text-sm text-muted whitespace-nowrap pt-0.5">
            Selanjutnya: {{ $next_label }}
        </p>
    @endif
</div>
