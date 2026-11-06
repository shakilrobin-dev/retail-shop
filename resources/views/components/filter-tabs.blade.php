@props(['tabs', 'active', 'model'])

{{-- Quick filters shown as tabs with counts, e.g. All · Low stock · Out of stock. --}}
<div class="flex gap-5 overflow-x-auto px-4">
    @foreach ($tabs as $key => $tab)
        @php [$label, $count] = is_array($tab) ? $tab + [1 => null] : [$tab, null]; @endphp
        <button type="button" wire:click="$set('{{ $model }}', '{{ $key }}')" class="{{ (string) $active === (string) $key ? 'tab-link-active' : 'tab-link' }}">
            {{ $label }}
            @if ($count !== null)<span @class(['rounded px-1.5 text-[11px] font-medium tabular-nums', 'bg-foreground text-background' => (string) $active === (string) $key, 'bg-surface text-muted-foreground-1' => (string) $active !== (string) $key])>{{ shop_digits($count) }}</span>@endif
        </button>
    @endforeach
</div>
