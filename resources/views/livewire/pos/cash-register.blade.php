<div class="mx-auto max-w-6xl space-y-5">
    <x-flash />
    <x-page-header class="mb-0" :title="__('Cash register')" :subtitle="__('Open the register with starting cash, close it by counting the drawer.')" />

    <div class="grid gap-4 md:grid-cols-2">
        <div class="card p-5">
            @if ($openShift)
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">{{ __('Register is open') }}</h2>
                    <span class="badge bg-emerald-50 text-emerald-700">{{ __('Open since') }} {{ shop_digits($openShift->opened_at->format('d M h:i A')) }}</span>
                </div>
                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt>{{ __('Opening cash') }}</dt><dd class="tabular-nums">{{ money($openSummary['opening']) }}</dd></div>
                    <div class="flex justify-between"><dt>{{ __('Cash in (sales, repairs)') }}</dt><dd class="tabular-nums text-emerald-700">+ {{ money($openSummary['cash_in']) }}</dd></div>
                    <div class="flex justify-between"><dt>{{ __('Cash out (refunds, expenses)') }}</dt><dd class="tabular-nums text-red-700">− {{ money($openSummary['cash_out']) }}</dd></div>
                    <div class="flex justify-between border-t border-card-line pt-2 text-base font-semibold"><dt>{{ __('Cash that should be in drawer') }}</dt><dd class="tabular-nums">{{ money($openSummary['expected']) }}</dd></div>
                </dl>
                <form wire:submit="close" class="mt-5 space-y-2">
                    <label class="form-label">{{ __('Count the cash in the drawer') }}</label>
                    <input wire:model="countedCash" type="number" step="0.01" min="0" class="form-input text-right text-lg">
                    @error('countedCash') <p class="form-error">{{ $message }}</p> @enderror
                    <input wire:model="closeNote" type="text" placeholder="{{ __('Note (optional)') }}" class="form-input">
                    <button type="submit" data-confirm="{{ __('Close the cash register now?') }}" data-confirm-tone="primary" data-confirm-button="{{ __('Close register') }}" class="btn-danger w-full">{{ __('Close register') }}</button>
                </form>
            @else
                <h2 class="font-semibold">{{ __('Register is closed') }}</h2>
                <form wire:submit="open" class="mt-4 space-y-2">
                    <label class="form-label">{{ __('Opening cash') }}</label>
                    <input wire:model="openingCash" type="number" step="0.01" min="0" class="form-input text-right text-lg">
                    @error('openingCash') <p class="form-error">{{ $message }}</p> @enderror
                    <button type="submit" class="btn-primary w-full">{{ __('Open register') }}</button>
                </form>
            @endif
        </div>

        <div class="card p-5">
            @php $summary = $openSummary ?? $viewSummary; @endphp
            <h2 class="mb-3 font-semibold">{{ $openShift ? __('Today by payment method') : ($viewShift ? __('Closed register summary') : __('Payment methods')) }}</h2>
            @if ($summary)
                <dl class="space-y-1.5 text-sm">
                    @forelse ($summary['by_method'] as $method => $amount)
                        <div class="flex justify-between"><dt>{{ $method }}</dt><dd class="tabular-nums">{{ money($amount) }}</dd></div>
                    @empty
                        <p class="text-muted-foreground-1">{{ __('No payments yet.') }}</p>
                    @endforelse
                </dl>
                @if ($viewShift)
                    <dl class="mt-4 space-y-1.5 border-t border-card-line pt-3 text-sm">
                        <div class="flex justify-between"><dt>{{ __('Expected cash') }}</dt><dd class="tabular-nums">{{ money($viewShift->expected_cash) }}</dd></div>
                        <div class="flex justify-between"><dt>{{ __('Counted cash') }}</dt><dd class="tabular-nums">{{ money($viewShift->counted_cash) }}</dd></div>
                        <div @class(['flex justify-between font-semibold', 'text-red-700' => $viewShift->difference < 0, 'text-emerald-700' => $viewShift->difference >= 0])>
                            <dt>{{ $viewShift->difference < 0 ? __('Short') : __('Extra') }}</dt><dd class="tabular-nums">{{ money(abs((float) $viewShift->difference)) }}</dd>
                        </div>
                    </dl>
                @endif
            @else
                <p class="text-sm text-muted-foreground-1">{{ __('Open the register to see live totals.') }}</p>
            @endif
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Opened') }}</th><th>{{ __('Closed') }}</th><th>{{ __('By') }}</th><th class="text-right">{{ __('Expected') }}</th><th class="text-right">{{ __('Counted') }}</th><th class="text-right">{{ __('Difference') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($history as $shift)
                    <tr wire:key="shift-{{ $shift->id }}">
                        <td>{{ shop_digits($shift->opened_at->format('d M h:i A')) }}</td>
                        <td>{{ shop_digits($shift->closed_at->format('d M h:i A')) }}</td>
                        <td>{{ $shift->user?->name }}</td>
                        <td class="text-right tabular-nums">{{ money($shift->expected_cash) }}</td>
                        <td class="text-right tabular-nums">{{ money($shift->counted_cash) }}</td>
                        <td @class(['text-right tabular-nums', 'text-red-700' => $shift->difference < 0, 'text-emerald-700' => $shift->difference > 0])>{{ money($shift->difference) }}</td>
                        <td class="text-right"><button type="button" wire:click="$set('viewShiftId', {{ $shift->id }})" class="btn-secondary btn-sm">{{ __('View') }}</button></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-muted-foreground-1">{{ __('No closed registers yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $history->links() }}
</div>
