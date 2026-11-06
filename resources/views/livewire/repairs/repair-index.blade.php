@php
    $statusColors = [
        'received' => 'bg-slate-100 text-slate-700',
        'diagnosing' => 'bg-sky-50 text-sky-700',
        'waiting_for_parts' => 'bg-amber-50 text-amber-700',
        'repairing' => 'bg-indigo-50 text-indigo-700',
        'ready' => 'bg-emerald-50 text-emerald-700',
        'delivered' => 'bg-slate-100 text-slate-500',
        'cancelled' => 'bg-red-50 text-red-700',
    ];
@endphp
<div class="space-y-4">
    <x-flash />
    <div class="flex flex-wrap items-center gap-2">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('Job no, phone, IMEI or model') }}" class="form-input max-w-sm">
        <div class="flex-1"></div>
        <a href="{{ route('repairs.create') }}" class="btn-primary" wire:navigate>+ {{ __('New repair job') }}</a>
    </div>

    <div class="flex flex-wrap gap-1">
        <button type="button" wire:click="$set('status', 'open')" @class(['btn-sm btn', 'bg-indigo-600 text-white' => $status === 'open', 'btn-secondary' => $status !== 'open'])>{{ __('In shop') }} ({{ shop_digits($openCount) }})</button>
        @foreach (\App\Enums\RepairStatus::cases() as $case)
            <button type="button" wire:click="$set('status', '{{ $case->value }}')" @class(['btn-sm btn', 'bg-indigo-600 text-white' => $status === $case->value, 'btn-secondary' => $status !== $case->value])>
                {{ $case->label() }} ({{ shop_digits($counts[$case->value] ?? 0) }})
            </button>
        @endforeach
        <button type="button" wire:click="$set('status', 'all')" @class(['btn-sm btn', 'bg-indigo-600 text-white' => $status === 'all', 'btn-secondary' => $status !== 'all'])>{{ __('All') }}</button>
    </div>

    <div class="card overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>{{ __('Job no') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Device') }}</th><th>{{ __('Problem') }}</th><th>{{ __('Status') }}</th><th>{{ __('Delivery') }}</th><th class="text-right">{{ __('Bill') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
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
                    <tr><td colspan="8" class="py-8 text-center text-slate-500">{{ __('No repair jobs.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $jobs->links() }}
</div>
