<div class="mx-auto max-w-5xl space-y-4">
    <x-flash />
    @error('stock') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('stock.index') }}" class="btn-secondary" wire:navigate>← {{ __('Stock') }}</a>
        <div class="inline-flex rounded-lg bg-slate-200 p-0.5 text-sm">
            <button type="button" wire:click="$set('mode', 'change')" @class(['rounded-md px-3 py-1.5', 'bg-white shadow-sm font-medium' => $mode === 'change'])>{{ __('Add / remove stock') }}</button>
            <button type="button" wire:click="$set('mode', 'count')" @class(['rounded-md px-3 py-1.5', 'bg-white shadow-sm font-medium' => $mode === 'count'])>{{ __('Stock count (scan shelf)') }}</button>
        </div>
    </div>

    <div class="card space-y-3 p-5">
        <p class="text-sm text-slate-500">
            {{ $mode === 'count'
                ? __('Scan every item on the shelf (each scan adds 1), or type the counted number. The system saves the difference.')
                : __('Enter a minus number to remove (e.g. -2 damaged) or a plus number to add (e.g. 3 found). Quantities are in pieces.') }}
        </p>
        <x-product-search :results="$results" :message="$scanMessage" />
        @error('lines') <p class="form-error">{{ $message }}</p> @enderror

        <table class="data-table">
            <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('In system') }}</th><th class="w-40">{{ $mode === 'count' ? __('Counted') : __('Change (+/−)') }}</th><th class="text-right">{{ __('After') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($lines as $index => $line)
                    @php $after = $mode === 'count' ? (float) $line['value'] : $line['current'] + (float) $line['value']; @endphp
                    <tr wire:key="adj-{{ $line['product_id'] }}">
                        <td>{{ $line['name'] }}</td>
                        <td class="text-right">{{ shop_digits($line['current'] + 0) }}</td>
                        <td><input wire:model.live.debounce.400ms="lines.{{ $index }}.value" type="number" step="any" class="form-input">@error("lines.$index.value") <p class="form-error">{{ $message }}</p> @enderror</td>
                        <td @class(['text-right font-medium', 'text-red-600' => $after < $line['current'], 'text-emerald-700' => $after > $line['current']])>{{ shop_digits($after + 0) }}</td>
                        <td class="text-right"><button type="button" wire:click="removeLine({{ $index }})" class="btn-secondary btn-sm">✕</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-400">{{ __('Scan products to add them.') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="grid gap-3 md:grid-cols-3">
            <select wire:model="reason" class="form-input">
                @foreach (\App\Enums\AdjustmentReason::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
            <input wire:model="note" type="text" placeholder="{{ __('Note') }}" class="form-input">
            <button type="button" wire:click="save" wire:confirm="{{ __('Save this stock change?') }}" class="btn-primary">{{ __('Save adjustment') }}</button>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <h3 class="px-4 pt-4 font-semibold">{{ __('Recent adjustments') }}</h3>
        <table class="data-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Items') }}</th><th>{{ __('By') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recent as $adjustment)
                    <tr wire:key="radj-{{ $adjustment->id }}">
                        <td class="whitespace-nowrap">{{ shop_digits($adjustment->created_at->format('d M Y')) }}</td>
                        <td class="font-mono text-xs">{{ $adjustment->reference_no }}</td>
                        <td>{{ $adjustment->reason->label() }}@if ($adjustment->note)<div class="text-xs text-slate-400">{{ $adjustment->note }}</div>@endif</td>
                        <td class="text-xs">@foreach ($adjustment->items as $item)<div>{{ $item->product->name }}: {{ $item->quantity > 0 ? '+' : '' }}{{ shop_digits($item->quantity + 0) }}</div>@endforeach</td>
                        <td class="text-slate-500">{{ $adjustment->user?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-500">{{ __('No adjustments yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
