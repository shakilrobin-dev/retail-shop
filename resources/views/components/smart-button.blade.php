@props(['icon', 'label', 'value', 'href' => null])

{{-- Small stat button shown at the top of a record (like Odoo "smart buttons"). --}}
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'flex min-w-36 items-center gap-3 rounded-lg border border-card-line bg-layer px-3 py-2 '.($href ? 'transition hover:border-primary-300 hover:bg-primary-50/40' : '')]) }}>
    <x-icon :name="$icon" class="size-5 text-muted-foreground" />
    <span class="leading-tight">
        <span class="block text-sm font-semibold tabular-nums text-foreground">{{ $value }}</span>
        <span class="block text-[11px] text-muted-foreground-1">{{ $label }}</span>
    </span>
</{{ $tag }}>
