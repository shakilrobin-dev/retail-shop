@props(['icon', 'label', 'value', 'href' => null])

{{-- Small stat button shown at the top of a record (like Odoo "smart buttons"). --}}
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'flex min-w-36 items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2 '.($href ? 'transition hover:border-brand-300 hover:bg-brand-50/40' : '')]) }}>
    <x-icon :name="$icon" class="size-5 text-slate-400" />
    <span class="leading-tight">
        <span class="block text-sm font-semibold tabular-nums text-slate-900">{{ $value }}</span>
        <span class="block text-[11px] text-slate-500">{{ $label }}</span>
    </span>
</{{ $tag }}>
