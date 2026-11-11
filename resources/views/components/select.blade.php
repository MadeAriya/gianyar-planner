@props(['label' => '', 'name', 'placeholder' => '', 'required' => false])

<div class="flex flex-col gap-2">
    @if($label)
        <label for="{{ $name }}" class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">
            {{ $label }}
        </label>
    @endif

    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'w-full h-12 px-4 border border-line rounded bg-white font-inter text-[15px] text-text focus:border-forest focus:ring-1 focus:ring-forest outline-none appearance-none']) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>

    @error($name)
        <p class="text-red-600 text-xs font-inter">{{ $message }}</p>
    @enderror
</div>
