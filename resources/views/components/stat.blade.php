@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'brand', 'href' => null])

@php
    $tones = [
        'brand' => 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400',
        'green' => 'bg-teal-50 text-teal-600 dark:bg-teal-500/10 dark:text-teal-400',
        'amber' => 'bg-yellow-50 text-yellow-600 dark:bg-yellow-500/10 dark:text-yellow-400',
        'red' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
        'sky' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
        'slate' => 'bg-surface text-muted-foreground-2',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'card flex items-start gap-4 p-4 '.($href ? 'transition hover:shadow-md' : '')]) }}>
    @if ($icon)
        <div class="rounded-lg p-2.5 {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-icon :name="$icon" class="size-5" />
        </div>
    @endif
    <div class="min-w-0">
        <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground-1">{{ $label }}</div>
        <div class="mt-1 truncate text-2xl font-semibold tabular-nums text-foreground">{{ $value }}</div>
        @if ($hint)<div class="mt-0.5 text-xs text-muted-foreground-1">{{ $hint }}</div>@endif
    </div>
</{{ $tag }}>
