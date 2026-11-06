@props(['label', 'for' => null, 'required' => false, 'hint' => null, 'error' => null, 'stacked' => false])

{{-- Business-suite style form row: label on the left, input on the right. --}}
<div {{ $attributes->merge(['class' => $stacked ? 'space-y-1.5' : 'grid gap-1.5 sm:grid-cols-[11rem_1fr] sm:items-start sm:gap-4']) }}>
    <label @if ($for) for="{{ $for }}" @endif class="pt-2 text-[13px] font-medium text-slate-600 {{ $stacked ? '!pt-0' : '' }}">
        {{ $label }}@if ($required)<span class="text-red-500"> *</span>@endif
    </label>
    <div class="min-w-0">
        {{ $slot }}
        @if ($error)
            @error($error) <p class="form-error">{{ $message }}</p> @enderror
        @endif
        @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    </div>
</div>
