@props(['icon', 'label', 'value', 'href' => null])

{{-- Small stat button shown at the top of a record (like Odoo "smart buttons"). --}}
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'flex min-w-32 items-center gap-2.5 rounded-md border border-layer-line bg-layer px-2.5 py-1.5 shadow-[0_1px_2px_rgb(0_0_0/0.04)] '.($href ? 'transition-colors hover:bg-layer-hover' : '')]) }}>
    <x-icon :name="$icon" class="size-4 text-muted-foreground" />
    <span class="leading-tight">
        <span class="block text-sm font-semibold tabular-nums text-foreground">{{ $value }}</span>
        <span class="block text-[11px] text-muted-foreground-1">{{ $label }}</span>
    </span>
</{{ $tag }}>
