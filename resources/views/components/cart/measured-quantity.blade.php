@props(['index', 'line', 'pieces', 'billed', 'breakdown'])

{{--
    Quantity of a product sold by measurement (tiles), filling the Unit and Qty columns of a cart row.
    Type the sqft the customer asks for, or the pieces; the other one is worked out.
--}}
@php
    $measure = $line['measure'];
    $pieceName = $line['units'][$line['product_unit_id']]['name'] ?? __('Piece');
    $asked = (float) $line['quantity'];
@endphp
<td colspan="2" class="align-top">
    <div class="grid w-max grid-cols-[auto_8.5rem] items-center gap-x-2 gap-y-1.5">
        <label for="measure-{{ $index }}" class="text-xs font-semibold text-foreground">{{ $measure['unit'] }}</label>
        <input id="measure-{{ $index }}" wire:model.live.debounce.600ms="cart.{{ $index }}.quantity" type="number" step="any" min="0" placeholder="0"
            class="form-input w-full py-1 text-right font-semibold" title="{{ __('How many :unit the customer needs', ['unit' => $measure['unit']]) }}">

        <label for="pieces-{{ $index }}" class="text-xs text-muted-foreground-2">{{ $pieceName }}</label>
        <div class="flex items-center gap-1">
            <button type="button" wire:click="decrement({{ $index }})" class="btn-secondary btn-sm" aria-label="{{ __('One piece less') }}">−</button>
            <input id="pieces-{{ $index }}" wire:model.live.debounce.500ms="cart.{{ $index }}.pieces" type="number" step="1" min="0"
                class="form-input w-full min-w-0 px-1 py-1 text-center" title="{{ __('Pieces to give') }}">
            <button type="button" wire:click="increment({{ $index }})" class="btn-secondary btn-sm" aria-label="{{ __('One piece more') }}">+</button>
        </div>
    </div>

    @if ($pieces > 0)
        <p class="mt-1.5 text-xs font-medium text-primary-700 dark:text-primary-400">{{ __('Give') }}: {{ $breakdown }}</p>
        @if (abs($billed - $asked) > 0.0005)
            <p class="text-[11px] text-amber-700 dark:text-amber-400">{{ __('Whole pieces: billed :billed :unit', ['billed' => shop_digits($billed + 0), 'unit' => $measure['unit']]) }}</p>
        @endif
    @endif
    @error("cart.$index.quantity") <p class="form-error">{{ $message }}</p> @enderror
</td>
