<div class="space-y-4">
    <x-flash />
    <div class="flex flex-wrap gap-2">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Search name or phone') }}" class="form-input max-w-sm">
        <button type="button" wire:click="create" class="btn-primary">+ {{ __('New customer') }}</button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card grid gap-3 p-5 md:grid-cols-2">
            <div>
                <label class="form-label">{{ __('Name') }} *</label>
                <input wire:model="name" type="text" class="form-input" autofocus>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('Phone') }}</label>
                <input wire:model="phone" type="text" class="form-input">
                @error('phone') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">{{ __('Address') }}</label>
                <input wire:model="address" type="text" class="form-input">
            </div>
            <div>
                <label class="form-label">{{ __('Notes') }}</label>
                <input wire:model="notes" type="text" class="form-input">
            </div>
            <div class="flex justify-end gap-2 md:col-span-2">
                <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    @endif

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th class="text-right">{{ __('Purchases') }}</th><th class="text-right">{{ __('Total spent') }}</th><th class="text-right">{{ __('Repairs') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($customers as $customer)
                    <tr wire:key="customer-{{ $customer->id }}">
                        <td class="font-medium">{{ $customer->name }}</td>
                        <td>{{ $customer->phone }}</td>
                        <td class="text-right">{{ shop_digits($customer->sales_count) }}</td>
                        <td class="text-right tabular-nums">{{ money($customer->sales_sum_total) }}</td>
                        <td class="text-right">{{ shop_digits($customer->repair_jobs_count) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('customers.show', $customer) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('History') }}</a>
                            <button type="button" wire:click="edit({{ $customer->id }})" class="btn-secondary btn-sm">{{ __('Edit') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">{{ __('No customers found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
</div>
