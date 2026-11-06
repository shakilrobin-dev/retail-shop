@php $pageIds = $customers->pluck('id')->all(); @endphp
<div>
    <x-flash />
    <x-page-header :title="__('Customers')" :subtitle="__('Purchase and repair history of every customer.')">
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New customer') }}</button>
    </x-page-header>

    <div class="card overflow-hidden">
        <x-list-toolbar :placeholder="__('Search name or phone')" />
        <x-table.bulk-bar :count="count($selected)">
            <button type="button" wire:click="export" class="btn-secondary btn-sm"><x-icon name="receipt" class="size-3.5" /> {{ __('Export selected') }}</button>
        </x-table.bulk-bar>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <x-table.check-all :ids="$pageIds" :selected="$selected" />
                        <x-table.sort field="name" :sort="$sortField" :dir="$sortDirection">{{ __('Customer') }}</x-table.sort>
                        <th>{{ __('Address') }}</th>
                        <x-table.sort field="purchases" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Purchases') }}</x-table.sort>
                        <x-table.sort field="spent" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Total spent') }}</x-table.sort>
                        <x-table.sort field="repairs" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Repairs') }}</x-table.sort>
                        <x-table.sort field="created" :sort="$sortField" :dir="$sortDirection">{{ __('Since') }}</x-table.sort>
                        <th class="w-12"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr wire:key="customer-{{ $customer->id }}" x-on:click="Livewire.navigate('{{ route('customers.show', $customer) }}')" class="cursor-pointer">
                            <x-table.check :value="$customer->id" />
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :name="$customer->name" />
                                    <div>
                                        <div class="font-medium text-foreground">{{ $customer->name }}</div>
                                        <div class="text-xs text-muted-foreground-1">{{ $customer->phone ?? '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="max-w-56 truncate text-muted-foreground-1">{{ $customer->address ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ shop_digits($customer->sales_count) }}</td>
                            <td class="text-right font-medium tabular-nums">{{ money($customer->sales_sum_total) }}</td>
                            <td class="text-right tabular-nums">{{ shop_digits($customer->repair_jobs_count) }}</td>
                            <td class="whitespace-nowrap text-xs text-muted-foreground-1">{{ shop_digits($customer->created_at->format('d M Y')) }}</td>
                            <td class="text-right" x-on:click.stop>
                                <button type="button" wire:click="edit({{ $customer->id }})" class="btn-ghost btn-sm" title="{{ __('Edit') }}"><x-icon name="pencil" class="size-4" /></button>
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="8" icon="users" :title="__('No customers found.')" :text="__('Customers are added from the POS or repair desk, or here.')" />
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-table.footer :paginator="$customers" />
    </div>

    <x-drawer wire:model="showForm" :title="$editingId ? __('Edit customer') : __('New customer')">
        <form id="customer-form" wire:submit="save" class="space-y-4">
            <x-form.field :label="__('Name')" required error="name" stacked><input wire:model="name" type="text" class="form-input"></x-form.field>
            <x-form.field :label="__('Phone')" error="phone" stacked><input wire:model="phone" type="text" class="form-input" placeholder="01XXXXXXXXX"></x-form.field>
            <x-form.field :label="__('Address')" stacked><input wire:model="address" type="text" class="form-input"></x-form.field>
            <x-form.field :label="__('Notes')" stacked><textarea wire:model="notes" rows="3" class="form-input"></textarea></x-form.field>
        </form>
        @if ($editingId)
            <div class="mt-6 border-t border-card-line pt-5">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground-1">{{ __('History') }}</h3>
                <x-activity-feed :subjects="\App\Models\Customer::find($editingId)" :limit="10" />
            </div>
        @endif
        <x-slot:footer>
            <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="customer-form" class="btn-primary">{{ __('Save') }}</button>
        </x-slot:footer>
    </x-drawer>
</div>
