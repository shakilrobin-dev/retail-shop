@php
    $tones = ['received' => 'slate', 'diagnosing' => 'sky', 'waiting_for_parts' => 'amber', 'repairing' => 'brand', 'ready' => 'green', 'delivered' => 'slate', 'cancelled' => 'red'];
    $columnAccent = ['received' => 'bg-slate-400', 'diagnosing' => 'bg-sky-500', 'waiting_for_parts' => 'bg-amber-500', 'repairing' => 'bg-primary-500', 'ready' => 'bg-emerald-500'];
@endphp
<div>
    <x-flash />
    <x-page-header :title="__('Repairs')" :subtitle="__('Drag a card to change its status. Late jobs are shown in red.')">
        <div class="inline-flex rounded-lg border border-layer-line bg-layer p-0.5">
            <button type="button" wire:click="$set('view', 'kanban')" @class(['rounded-md px-2.5 py-1 text-sm font-medium', 'bg-slate-900 text-white' => $view === 'kanban', 'text-muted-foreground-2 hover:text-foreground' => $view !== 'kanban'])>{{ __('Board') }}</button>
            <button type="button" wire:click="$set('view', 'list')" @class(['rounded-md px-2.5 py-1 text-sm font-medium', 'bg-slate-900 text-white' => $view === 'list', 'text-muted-foreground-2 hover:text-foreground' => $view !== 'list'])>{{ __('List') }}</button>
        </div>
        <button type="button" wire:click="export" class="btn-secondary"><x-icon name="receipt" class="size-4" /> {{ __('Export') }}</button>
        <a href="{{ route('repairs.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New repair job') }}</a>
    </x-page-header>

    @if ($view === 'kanban')
        <div class="mb-4 max-w-md">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Job no, phone, IMEI or model') }}" class="form-input pl-9">
            </div>
        </div>

        <div class="scroll-thin -mx-1 flex gap-4 overflow-x-auto px-1 pb-4" x-data="{ dragging: null, over: null }">
            @foreach ($board as $statusValue => $column)
                <div class="flex w-72 shrink-0 flex-col rounded-xl bg-surface"
                    x-on:dragover.prevent="over = '{{ $statusValue }}'"
                    x-on:dragleave="over = null"
                    x-on:drop.prevent="over = null; if (dragging) $wire.moveTo(dragging, '{{ $statusValue }}')"
                    :class="over === '{{ $statusValue }}' ? 'ring-2 ring-primary-400' : ''">
                    <div class="flex items-center justify-between px-3 py-2.5">
                        <div class="flex items-center gap-2">
                            <span class="size-2 rounded-full {{ $columnAccent[$statusValue] ?? 'bg-slate-400' }}"></span>
                            <span class="text-sm font-semibold text-foreground">{{ $column['status']->label() }}</span>
                        </div>
                        <span class="rounded-full bg-layer px-2 py-0.5 text-xs font-medium text-muted-foreground-2">{{ shop_digits($column['jobs']->count()) }}</span>
                    </div>
                    <div class="scroll-thin flex max-h-[calc(100vh-17rem)] min-h-32 flex-col gap-2 overflow-y-auto px-2 pb-2">
                        @forelse ($column['jobs'] as $job)
                            @php $late = $job->estimated_delivery_at?->isPast() && $job->status !== \App\Enums\RepairStatus::Ready; @endphp
                            <a href="{{ route('repairs.show', $job) }}" wire:navigate wire:key="card-{{ $job->id }}" draggable="true"
                                x-on:dragstart="dragging = {{ $job->id }}" x-on:dragend="dragging = null"
                                class="block cursor-grab rounded-lg border border-card-line bg-layer p-3 shadow-sm transition hover:border-primary-300 hover:shadow active:cursor-grabbing">
                                <div class="flex items-start justify-between gap-2">
                                    <span class="font-mono text-[11px] font-medium text-muted-foreground-1">{{ $job->job_no }}</span>
                                    @if ($job->warranty_of_job_id)<x-badge tone="amber">{{ __('Warranty') }}</x-badge>@endif
                                </div>
                                <div class="mt-1 text-sm font-semibold text-foreground">{{ trim($job->device_brand.' '.$job->device_model) ?: __('repair_devices.'.$job->device_type) }}</div>
                                <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground-1">{{ $job->problem }}</p>
                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <x-avatar :name="$job->customer->name" size="size-5" class="text-[9px]" />
                                        <span class="truncate text-xs text-muted-foreground-2">{{ $job->customer->name }}</span>
                                    </div>
                                    @if ($job->estimated_delivery_at)
                                        <span @class(['shrink-0 text-[11px]', 'font-semibold text-red-600' => $late, 'text-muted-foreground' => ! $late])>{{ shop_digits($job->estimated_delivery_at->format('d M')) }}</span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="rounded-lg border-2 border-dashed border-card-line py-6 text-center text-xs text-muted-foreground">{{ __('Drop here') }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
        <p class="text-xs text-muted-foreground-1">{{ __('Delivered and cancelled jobs are in the List view.') }}</p>
    @else
        <div class="card overflow-hidden">
            <x-filter-tabs model="status" :active="$status" :tabs="['open' => [__('In shop'), $openCount]] + collect(\App\Enums\RepairStatus::cases())->mapWithKeys(fn ($case) => [$case->value => [$case->label(), $counts[$case->value] ?? 0]])->all() + ['all' => [__('All'), $counts->sum()]]" />
            <div class="border-t border-card-line"></div>
            <x-list-toolbar :placeholder="__('Job no, phone, IMEI or model')" />
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <x-table.sort field="job" :sort="$sortField" :dir="$sortDirection">{{ __('Job no') }}</x-table.sort>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Device') }}</th>
                            <th>{{ __('Problem') }}</th>
                            <th>{{ __('Status') }}</th>
                            <x-table.sort field="delivery" :sort="$sortField" :dir="$sortDirection">{{ __('Delivery') }}</x-table.sort>
                            <x-table.sort field="total" :sort="$sortField" :dir="$sortDirection" align="right">{{ __('Bill') }}</x-table.sort>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jobs as $job)
                            @php $late = $job->estimated_delivery_at?->isPast() && ! in_array($job->status, [\App\Enums\RepairStatus::Delivered, \App\Enums\RepairStatus::Cancelled, \App\Enums\RepairStatus::Ready]); @endphp
                            <tr wire:key="job-{{ $job->id }}" x-on:click="Livewire.navigate('{{ route('repairs.show', $job) }}')" class="cursor-pointer">
                                <td class="font-mono text-xs font-medium text-foreground">{{ $job->job_no }}@if ($job->warranty_of_job_id) <x-badge tone="amber">{{ __('Warranty') }}</x-badge>@endif</td>
                                <td><div class="flex items-center gap-2"><x-avatar :name="$job->customer->name" size="size-6" /><div>{{ $job->customer->name }}<div class="text-xs text-muted-foreground-1">{{ $job->customer->phone }}</div></div></div></td>
                                <td>{{ $job->device_brand }} {{ $job->device_model }}@if ($job->imei_serial)<div class="font-mono text-xs text-muted-foreground">{{ $job->imei_serial }}</div>@endif</td>
                                <td class="max-w-xs truncate text-muted-foreground-2">{{ $job->problem }}</td>
                                <td><x-badge :tone="$tones[$job->status->value] ?? 'slate'">{{ $job->status->label() }}</x-badge>@if ($job->technician)<div class="mt-0.5 text-xs text-muted-foreground">{{ $job->technician->name }}</div>@endif</td>
                                <td @class(['whitespace-nowrap text-xs', 'font-semibold text-red-600' => $late, 'text-muted-foreground-2' => ! $late])>{{ $job->estimated_delivery_at ? shop_digits($job->estimated_delivery_at->format('d M h:i A')) : '—' }}</td>
                                <td class="text-right tabular-nums"><span class="font-semibold text-foreground">{{ money($job->total) }}</span>@if ($job->paid_amount > 0)<div class="text-xs text-muted-foreground">{{ __('Paid') }} {{ money($job->paid_amount) }}</div>@endif</td>
                            </tr>
                        @empty
                            <x-empty :colspan="7" icon="wrench" :title="__('No repair jobs.')" />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-table.footer :paginator="$jobs" />
        </div>
    @endif
</div>
