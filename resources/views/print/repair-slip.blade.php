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
            <div class="bold" style="margin-top:1mm">{{ __('REPAIR JOB SLIP') }} · {{ __('Customer copy') }}</div>
        </div>
        <hr>
        <div class="center big">{{ $job->job_no }}</div>
        {!! $barcodeSvg !!}
        <table>
            <tr><td>{{ __('Date') }}</td><td class="right">{{ shop_digits($job->created_at->format('d/m/Y h:i A')) }}</td></tr>
            <tr><td>{{ __('Customer') }}</td><td class="right">{{ $job->customer->name }}</td></tr>
            <tr><td>{{ __('Phone') }}</td><td class="right">{{ $job->customer->phone }}</td></tr>
            <tr><td>{{ __('Device') }}</td><td class="right">{{ $job->device_brand }} {{ $job->device_model }} {{ $job->device_color }}</td></tr>
            @if ($job->imei_serial)<tr><td>{{ __('IMEI / serial') }}</td><td class="right">{{ $job->imei_serial }}</td></tr>@endif
            @if ($job->accessories)<tr><td>{{ __('Accessories') }}</td><td class="right">{{ collect($job->accessories)->map(fn ($a) => __('repair_accessories.'.$a))->implode(', ') }}</td></tr>@endif
            @if ($job->estimated_delivery_at)<tr><td>{{ __('Delivery date') }}</td><td class="right bold">{{ shop_digits($job->estimated_delivery_at->format('d/m/Y h:i A')) }}</td></tr>@endif
            @if ($job->estimated_cost !== null)<tr><td>{{ __('Estimated cost') }}</td><td class="right">{{ money($job->estimated_cost) }}</td></tr>@endif
            @if ($job->paid_amount > 0)<tr><td>{{ __('Advance paid') }}</td><td class="right bold">{{ money($job->paid_amount) }}</td></tr>@endif
        </table>
        <hr>
        <div><span class="bold">{{ __('Problem') }}:</span> {{ $job->problem }}</div>
        @if ($job->condition_notes)<div><span class="bold">{{ __('Condition') }}:</span> {{ $job->condition_notes }}</div>@endif
        @if ($terms)<hr><div class="muted" style="font-size:0.9em">{{ $terms }}</div>@endif
        <div style="margin-top:6mm">______________________<br>{{ __('Customer signature') }}</div>

        <div class="cut">✂ {{ __('Device tag - keep with the device') }}</div>
        <div class="center big">{{ $job->job_no }}</div>
        <div class="center">{{ $job->customer->name }} · {{ $job->customer->phone }}</div>
        <div class="center">{{ $job->device_brand }} {{ $job->device_model }}</div>
        @if ($job->device_password)<div class="center">{{ __('Password') }}: {{ $job->device_password }}</div>@endif
        <div class="center muted">{{ $job->problem }}</div>
    </div>
    @unless (request()->boolean('preview'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endunless
</body>
</html>
