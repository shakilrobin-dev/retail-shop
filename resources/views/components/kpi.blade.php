@props(['label', 'value', 'icon' => null, 'tone' => 'indigo', 'hint' => null, 'change' => null, 'changeLabel' => null, 'href' => null])

{{-- Metric card with a soft coloured icon and an optional up/down change. --}}
@php
    $tones = [
        'indigo' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300',
        'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
        'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
        'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300',
        'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
        'slate' => 'bg-surface text-muted-foreground-2',
    ];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'card p-4 '.($href ? 'transition-colors hover:bg-muted' : '')]) }}>
    <div class="flex items-start justify-between gap-2">
        <span class="text-[13px] text-muted-foreground-1">{{ $label }}</span>
        @if ($icon)<span class="flex size-7 items-center justify-center rounded-md {{ $tones[$tone] ?? $tones['indigo'] }}"><x-icon :name="$icon" class="size-4" /></span>@endif
    </div>
    <div class="mt-1 truncate text-[22px] font-semibold tracking-tight tabular-nums text-foreground">{{ $value }}</div>
    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
        @if ($change !== null)
            <span @class(['inline-flex items-center gap-0.5 rounded px-1 font-medium', 'bg-emerald-50 text-emerald-700' => $change >= 0, 'bg-red-50 text-red-700' => $change < 0])>
                {{ $change >= 0 ? '▲' : '▼' }} {{ shop_digits(abs($change)) }}%
            </span>
            @if ($changeLabel)<span class="text-muted-foreground">{{ $changeLabel }}</span>@endif
        @endif
        @if ($hint)<span class="text-muted-foreground-1">{{ $hint }}</span>@endif
    </div>
</{{ $tag }}>
