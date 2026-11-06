@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'brand', 'href' => null])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'green' => 'bg-emerald-50 text-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'red' => 'bg-red-50 text-red-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'card flex items-start gap-4 p-4 '.($href ? 'transition hover:border-brand-300 hover:shadow-md' : '')]) }}>
    @if ($icon)
        <div class="rounded-lg p-2.5 {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-icon :name="$icon" class="size-5" />
        </div>
    @endif
    <div class="min-w-0">
        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
        <div class="mt-1 truncate text-2xl font-semibold tabular-nums text-slate-900">{{ $value }}</div>
        @if ($hint)<div class="mt-0.5 text-xs text-slate-500">{{ $hint }}</div>@endif
    </div>
</{{ $tag }}>
