@props(['label' => '', 'name', 'type' => 'text', 'placeholder' => '', 'value' => '', 'required' => false])

<div class="flex flex-col gap-2">
    @if($label)
        <label for="{{ $name }}" class="text-xs font-inter font-semibold uppercase tracking-wider text-muted">
            {{ $label }}
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        {{ $required ? 'required' : '' }}
        {{ $attributes->merge(['class' => 'w-full h-12 px-4 border border-line rounded bg-white font-inter text-[15px] text-text placeholder:text-muted/60 focus:border-forest focus:ring-1 focus:ring-forest outline-none']) }}
    />

    @error($name)
        <p class="text-red-600 text-xs font-inter">{{ $message }}</p>
    @enderror
</div>
