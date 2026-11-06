@php
    $statusColors = [
        'received' => 'bg-slate-100 text-slate-700',
        'diagnosing' => 'bg-sky-50 text-sky-700',
        'waiting_for_parts' => 'bg-amber-50 text-amber-700',
        'repairing' => 'bg-brand-50 text-brand-700',
        'ready' => 'bg-emerald-50 text-emerald-700',
        'delivered' => 'bg-slate-100 text-slate-500',
        'cancelled' => 'bg-red-50 text-red-700',
    ];
@endphp
<div class="space-y-5">
    <x-flash />
    <x-page-header class="mb-0" :title="__('Repairs')" :subtitle="__('Devices in the shop, sorted by delivery date. Late jobs are shown in red.')">
        <a href="{{ route('repairs.create') }}" class="btn-primary" wire:navigate><x-icon name="plus" class="size-4" /> {{ __('New repair job') }}</a>
    </x-page-header>

    <div class="card">
        <div class="flex gap-5 overflow-x-auto border-b border-slate-200 px-4 pt-3">
            <button type="button" wire:click="$set('status', 'open')" class="{{ $status === 'open' ? 'tab-link-active' : 'tab-link' }}">{{ __('In shop') }} <span class="rounded-full bg-slate-100 px-1.5 text-[11px]">{{ shop_digits($openCount) }}</span></button>
            @foreach (\App\Enums\RepairStatus::cases() as $case)
                <button type="button" wire:click="$set('status', '{{ $case->value }}')" class="{{ $status === $case->value ? 'tab-link-active' : 'tab-link' }}">
                    {{ $case->label() }} <span class="rounded-full bg-slate-100 px-1.5 text-[11px]">{{ shop_digits($counts[$case->value] ?? 0) }}</span>
                </button>
            @endforeach
            <button type="button" wire:click="$set('status', 'all')" class="{{ $status === 'all' ? 'tab-link-active' : 'tab-link' }}">{{ __('All') }}</button>
        </div>
        <div class="border-b border-slate-100 p-3">
            <div class="relative max-w-md">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Job no, phone, IMEI or model') }}" class="form-input pl-9">
            </div>
        </div>
        <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Job no') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Device') }}</th><th>{{ __('Problem') }}</th><th>{{ __('Status') }}</th><th>{{ __('Delivery') }}</th><th class="text-right">{{ __('Bill') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($jobs as $job)
                    @php $late = $job->estimated_delivery_at?->isPast() && ! in_array($job->status, [\App\Enums\RepairStatus::Delivered, \App\Enums\RepairStatus::Cancelled, \App\Enums\RepairStatus::Ready]); @endphp
                    <tr wire:key="job-{{ $job->id }}">
                        <td class="font-mono text-xs">{{ $job->job_no }}@if ($job->warranty_of_job_id)<div class="badge bg-amber-50 text-amber-700">{{ __('Warranty') }}</div>@endif</td>
                        <td>{{ $job->customer->name }}<div class="text-xs text-slate-500">{{ $job->customer->phone }}</div></td>
                        <td>{{ $job->device_brand }} {{ $job->device_model }}@if ($job->imei_serial)<div class="font-mono text-xs text-slate-400">{{ $job->imei_serial }}</div>@endif</td>
                        <td class="max-w-xs truncate text-slate-600">{{ $job->problem }}</td>
                        <td><span class="badge {{ $statusColors[$job->status->value] ?? '' }}">{{ $job->status->label() }}</span>@if ($job->technician)<div class="text-xs text-slate-400">{{ $job->technician->name }}</div>@endif</td>
                        <td @class(['whitespace-nowrap text-xs', 'font-semibold text-red-600' => $late])>{{ $job->estimated_delivery_at ? shop_digits($job->estimated_delivery_at->format('d M h:i A')) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ money($job->total) }}@if ($job->paid_amount > 0)<div class="text-xs text-slate-400">{{ __('Paid') }} {{ money($job->paid_amount) }}</div>@endif</td>
                        <td class="text-right"><a href="{{ route('repairs.show', $job) }}" class="btn-secondary btn-sm" wire:navigate>{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <x-empty :colspan="8" icon="wrench" :title="__('No repair jobs.')" />
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
    {{ $jobs->links() }}
</div>
