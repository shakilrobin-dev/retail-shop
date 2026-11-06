<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $job->job_no }}</title>
    @include('print.partials.thermal-style')
</head>
<body>
    <div class="receipt">
        <div class="center">
            <div class="shop">{{ $shopName }}</div>
            @if ($address)<div>{{ $address }}</div>@endif
            @if ($shopPhone)<div>{{ __('Phone') }}: {{ $shopPhone }}</div>@endif
            <div class="bold" style="margin-top:1mm">{{ __('REPAIR BILL') }}</div>
        </div>
        <hr>
        <table>
            <tr><td>{{ __('Job no') }}</td><td class="right bold">{{ $job->job_no }}</td></tr>
            <tr><td>{{ __('Date') }}</td><td class="right">{{ shop_digits(($job->delivered_at ?? now())->format('d/m/Y h:i A')) }}</td></tr>
            <tr><td>{{ __('Customer') }}</td><td class="right">{{ $job->customer->name }} ({{ $job->customer->phone }})</td></tr>
            <tr><td>{{ __('Device') }}</td><td class="right">{{ $job->device_brand }} {{ $job->device_model }}</td></tr>
            @if ($job->imei_serial)<tr><td>{{ __('IMEI / serial') }}</td><td class="right">{{ $job->imei_serial }}</td></tr>@endif
        </table>
        <hr>
        <table>
            <thead><tr><th>{{ __('Item') }}</th><th class="right">{{ __('Qty') }}</th><th class="right">{{ __('Total') }}</th></tr></thead>
            <tbody>
                @foreach ($job->parts as $part)
                    <tr><td>{{ $part->product_name }}</td><td class="right">{{ shop_digits($part->quantity + 0) }}</td><td class="right">{{ shop_digits(number_format((float) $part->line_total, 2)) }}</td></tr>
                @endforeach
                @if ($job->service_charge > 0)
                    <tr><td>{{ __('Service charge') }}</td><td></td><td class="right">{{ shop_digits(number_format((float) $job->service_charge, 2)) }}</td></tr>
                @endif
            </tbody>
        </table>
        <hr>
        <table>
            @if ($job->discount > 0)<tr><td>{{ __('Discount') }}</td><td class="right">− {{ money($job->discount) }}</td></tr>@endif
            <tr class="grand"><td>{{ __('Total') }}</td><td class="right">{{ money($job->total) }}</td></tr>
            @foreach ($job->payments as $payment)
                <tr><td>{{ $payment->direction->label() }} · {{ $payment->paymentMethod->localName() }} ({{ shop_digits($payment->paid_at->format('d/m')) }})</td><td class="right">{{ money($payment->amount) }}</td></tr>
            @endforeach
            @php $due = (float) $job->total - (float) $job->paid_amount; @endphp
            @if (abs($due) > 0.009)<tr class="bold"><td>{{ $due > 0 ? __('Due') : __('Refund due') }}</td><td class="right">{{ money(abs($due)) }}</td></tr>@endif
        </table>
        @if ($job->warranty_until)
            <hr><div class="center bold">{{ __('Repair warranty till :date', ['date' => shop_digits($job->warranty_until->format('d/m/Y'))]) }}</div>
        @elseif ($job->warranty_days && ! $job->delivered_at)
            <hr><div class="center">{{ __('Repair warranty: :days days from delivery', ['days' => shop_digits($job->warranty_days)]) }}</div>
        @endif
        @if ($job->status === \App\Enums\RepairStatus::Cancelled)<hr><div class="center bold">*** {{ __('CANCELLED') }} ***</div>@endif
        <hr>
        <div class="center">{{ __('Thank you!') }}</div>
    </div>
    @unless (request()->boolean('preview'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endunless
</body>
</html>
