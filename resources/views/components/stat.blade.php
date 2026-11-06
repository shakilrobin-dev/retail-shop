@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'brand', 'href' => null])

{{-- Stripe-style metric: small label, big number, short hint. --}}
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'group card p-4 '.($href ? 'transition-colors hover:border-layer-line hover:bg-muted' : '')]) }}>
    <div class="flex items-center justify-between gap-2">
        <span class="text-[13px] text-muted-foreground-1">{{ $label }}</span>
        @if ($icon)<x-icon :name="$icon" class="size-4 text-muted-foreground" />@endif
    </div>
    <div class="mt-2 truncate text-2xl font-semibold tracking-tight tabular-nums text-foreground">{{ $value }}</div>
    @if ($hint)<div class="mt-1 text-xs text-muted-foreground-1">{{ $hint }}</div>@endif
    @if ($href)<div class="mt-2 text-xs font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100">{{ __('View') }} →</div>@endif
</{{ $tag }}>
