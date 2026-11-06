@props(['tabs', 'active', 'model'])

{{-- Quick filters shown as tabs with counts, e.g. All · Low stock · Out of stock. --}}
<div class="flex gap-5 overflow-x-auto px-4 pt-2">
    @foreach ($tabs as $key => $tab)
        @php [$label, $count] = is_array($tab) ? $tab + [1 => null] : [$tab, null]; @endphp
        <button type="button" wire:click="$set('{{ $model }}', '{{ $key }}')" class="{{ (string) $active === (string) $key ? 'tab-link-active' : 'tab-link' }}">
            {{ $label }}
            @if ($count !== null)<span @class(['rounded-full px-1.5 text-[11px]', 'bg-brand-100 text-brand-700' => (string) $active === (string) $key, 'bg-slate-100 text-slate-600' => (string) $active !== (string) $key])>{{ shop_digits($count) }}</span>@endif
        </button>
    @endforeach
</div>
