@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => null])

{{-- Page title row with actions on the right (ERP style). --}}
<div {{ $attributes->merge(['class' => 'mb-5 flex flex-wrap items-end justify-between gap-3']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-brand-700">
                <x-icon name="arrow-left" class="size-3.5" /> {{ $backLabel ?? __('Back') }}
            </a>
        @endif
        <h1 class="truncate text-xl font-semibold tracking-tight text-slate-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim($slot))
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
