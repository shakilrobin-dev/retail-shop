<div class="mx-auto max-w-4xl space-y-4">
    @error('stock') <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

    @if ($branches->isEmpty())
        <div class="card p-6 text-center text-slate-600">{{ __('There is only one branch. Add another branch in Settings to transfer stock.') }}</div>
    @else
        <div class="card grid gap-3 p-5 md:grid-cols-2">
            <div>
                <label class="form-label">{{ __('Send to') }} *</label>
                <select wire:model="to_branch_id" class="form-input">
                    <option value="">{{ __('Choose branch...') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->localName() }}</option>
                    @endforeach
                </select>
                @error('to_branch_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('Note') }}</label>
                <input wire:model="note" type="text" class="form-input">
            </div>
        </div>

        <div class="card space-y-3 p-5">
            <x-product-search :results="$results" :message="$scanMessage" />
            @error('lines') <p class="form-error">{{ $message }}</p> @enderror
            <table class="data-table">
                <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('Available') }}</th><th class="w-40">{{ __('Send (pieces)') }}</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($lines as $index => $line)
                        <tr wire:key="tl-{{ $line['product_id'] }}">
                            <td>{{ $line['name'] }}</td>
                            <td class="text-right">{{ shop_digits($line['available'] + 0) }}</td>
                            <td><input wire:model="lines.{{ $index }}.quantity" type="number" step="any" min="0" class="form-input">@error("lines.$index.quantity") <p class="form-error">{{ $message }}</p> @enderror</td>
                            <td class="text-right"><button type="button" wire:click="removeLine({{ $index }})" class="btn-secondary btn-sm">✕</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-slate-400">{{ __('Scan products to send.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="flex justify-end gap-2">
                <a href="{{ route('stock.transfers.index') }}" class="btn-secondary" wire:navigate>{{ __('Cancel') }}</a>
                <button type="button" wire:click="save" class="btn-primary">{{ __('Send stock') }}</button>
            </div>
        </div>
    @endif
</div>
