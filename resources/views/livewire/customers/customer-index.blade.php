@php $repairsOn = app(\App\Services\Settings::class)->bool('repairs_enabled'); @endphp
@php $pageIds = $customers->pluck('id')->all(); @endphp
<div>
    <x-flash />
    <x-page-header :title="__('Customers')" :subtitle="$repairsOn ? __('Purchase and repair history of every customer.') : __('Purchase and due history of every customer.')">
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" class="size-4" /> {{ __('New customer') }}</button>
    </x-page-header>

    <div class="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi :label="__('Customers')" :value="shop_digits($summary['total'])" icon="users" tone="indigo" />
        <x-kpi :label="__('New this month')" :value="shop_digits($summary['new'])" icon="plus" tone="emerald" />
        <x-kpi :label="__('Repeat buyers')" :value="shop_digits($summary['repeat'])" icon="history" tone="violet" :hint="__('Bought more than once')" />
        <x-kpi :label="__('Total customer due')" :value="money($summary['due'])" icon="banknotes" :tone="$summary['due'] > 0 ? 'rose' : 'emerald'" :hint="__(':count customers owe money', ['count' => shop_digits($summary['due_customers'])])" :href="route('reports.index', ['tab' => 'customer-dues'])" />
    </div>

    <div class="card overflow-hidden">
        <x-filter-tabs model="dueFilter" :active="$dueFilter" :tabs="['' => [__('All'), $summary['total']], 'due' => [__('With due'), $summary['due_customers']]]" />
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
                        <x-table.sort field="due" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Due') }}</x-table.sort>
                        @if ($repairsOn)<x-table.sort field="repairs" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Repairs') }}</x-table.sort>@endif
                        <x-table.sort field="created" :sort="$sortField" :dir="$sortDirection">{{ __('Since') }}</x-table.sort>
                        <th class="w-12"><span class="sr-only">{{ __('Actions') }}</span></th>
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
                            <td class="text-right tabular-nums">@if ($customer->due_sum > 0)<span class="font-semibold text-red-600">{{ money($customer->due_sum) }}</span>@else<span class="text-muted-foreground">—</span>@endif</td>
                            @if ($repairsOn)<td class="text-right tabular-nums">{{ shop_digits($customer->repair_jobs_count) }}</td>@endif
                            <td class="whitespace-nowrap text-xs text-muted-foreground-1">{{ shop_digits($customer->created_at->format('d M Y')) }}</td>
                            <td class="whitespace-nowrap text-right" x-on:click.stop>
                                @if ($customer->due_sum > 0)
                                    @can('customers.collect-due')
                                        <a href="{{ route('customers.show', ['customer' => $customer, 'collect' => 1]) }}" wire:navigate class="btn-secondary btn-sm">{{ __('Collect') }}</a>
                                    @endcan
                                @endif
                                <button type="button" wire:click="edit({{ $customer->id }})" class="btn-ghost btn-sm" title="{{ __('Edit') }}"><x-icon name="pencil" class="size-4" /></button>
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="$repairsOn ? 9 : 8" icon="users" :title="__('No customers found.')" :text="__('Customers are added from the POS or repair desk, or here.')" />
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
            <x-form.field :label="__('Credit limit')" error="credit_limit" stacked>
                <input wire:model="credit_limit" type="number" step="0.01" min="0" class="form-input" placeholder="{{ __('No limit') }}">
                <p class="form-hint">{{ __('Most this customer can owe at a time. Leave empty for no limit.') }}</p>
            </x-form.field>
            <x-form.field :label="__('Notes')" stacked><textarea wire:model="notes" rows="3" class="form-input"></textarea></x-form.field>
        </form>
        @if ($editingId)
            <div class="mt-6 border-t border-card-line pt-5">
                <h3 class="mb-3 text-xs font-medium text-muted-foreground-1">{{ __('History') }}</h3>
                <x-activity-feed :subjects="\App\Models\Customer::find($editingId)" :limit="10" />
            </div>
        @endif
        <x-slot:footer>
            <button type="button" wire:click="$set('showForm', false)" class="btn-secondary">{{ __('Cancel') }}</button>
            <button type="submit" form="customer-form" class="btn-primary">{{ __('Save') }}</button>
        </x-slot:footer>
    </x-drawer>
</div>
