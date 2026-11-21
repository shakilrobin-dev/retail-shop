@php
    $width = match ($paper) { '58' => '58mm', 'a4' => '190mm', default => '80mm' };
    $isThermal = $paper !== 'a4';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_no }}</title>
    @fonts
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: {!! app()->getLocale() === 'bn' ? "'Hind Siliguri', " : '' !!}Arial, sans-serif; color: #000; font-size: {{ $paper === '58' ? '10px' : '12px' }}; }
        .receipt { width: {{ $width }}; padding: {{ $isThermal ? '2mm 3mm' : '10mm' }}; margin: 0 auto; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .shop { font-size: 1.5em; font-weight: bold; }
        .muted { color: #333; }
        hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 0.6mm 0; vertical-align: top; }
        th { text-align: left; border-bottom: 1px dashed #000; }
        .totals td { padding: 0.4mm 0; }
        .grand td { font-size: 1.3em; font-weight: bold; border-top: 1px dashed #000; padding-top: 1mm; }
        .logo { max-height: 18mm; max-width: 60%; margin-bottom: 1mm; }
        @page { size: {{ $isThermal ? $width.' auto' : 'A4' }}; margin: {{ $isThermal ? '0' : '10mm' }}; }
        @media screen { body { background: #e2e8f0; padding: 16px 0; } .receipt { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2); } }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center">
            @if ($logo)<img src="{{ asset('storage/'.$logo) }}" alt="" class="logo">@endif
            <div class="shop">{{ $shopName }}</div>
            @if ($address)<div>{{ $address }}</div>@endif
            @if ($shopPhone)<div>{{ __('Phone') }}: {{ $shopPhone }}</div>@endif
            <div class="bold" style="margin-top:2mm; font-size:1.15em; letter-spacing:1px">{{ __('MONEY RECEIPT') }}</div>
        </div>
        <hr>
        <table>
            <tr><td>{{ __('Receipt no') }}: <span class="bold">{{ $receipt->receipt_no }}</span></td></tr>
            <tr><td>{{ __('Date') }}: {{ shop_digits($receipt->paid_at->format('d/m/Y h:i A')) }}</td></tr>
            <tr><td>{{ __('Customer') }}: {{ $receipt->customer->name }}@if ($receipt->customer->phone) ({{ $receipt->customer->phone }})@endif</td></tr>
            <tr><td>{{ __('Received by') }}: {{ $receipt->user?->name }}</td></tr>
        </table>
        <hr>
        <table>
            <thead><tr><th>{{ __('Against invoice') }}</th><th class="right">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach ($receipt->allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->payable?->invoice_no }} <span class="muted">({{ shop_digits($allocation->payable?->sold_at?->format('d/m/Y')) }})</span></td>
                        <td class="right">{{ money($allocation->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <hr>
        <table class="totals">
            <tr class="grand"><td>{{ __('Received') }}</td><td class="right">{{ money($receipt->amount) }}</td></tr>
            <tr><td>{{ __('Paid by') }} {{ $receipt->paymentMethod->localName() }}@if ($receipt->transaction_ref) ({{ $receipt->transaction_ref }})@endif</td><td></td></tr>
            <tr class="bold"><td>{{ __('Due remaining') }}</td><td class="right">{{ money($dueNow) }}</td></tr>
        </table>
        @if ($receipt->note)<hr><div>{{ $receipt->note }}</div>@endif
        <hr>
        <div class="center">
            @if ($footer)<div>{{ $footer }}</div>@endif
        </div>
    </div>
    @if ($autoPrint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
