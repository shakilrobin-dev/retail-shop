@php
    $width = match ($paper) { '58' => '58mm', 'a4' => '190mm', default => '80mm' };
    $isThermal = $paper !== 'a4';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice_no }}</title>
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
            @if ($vatBin)<div>{{ __('BIN') }}: {{ $vatBin }}</div>@endif
            @if ($header)<div class="muted" style="margin-top:1mm">{{ $header }}</div>@endif
        </div>
        <hr>
        <table>
            <tr><td>{{ __('Invoice') }}: <span class="bold">{{ $sale->invoice_no }}</span></td></tr>
            <tr><td>{{ __('Date') }}: {{ shop_digits($sale->sold_at->format('d/m/Y h:i A')) }}</td></tr>
            <tr><td>{{ __('Cashier') }}: {{ $sale->user?->name }}</td></tr>
            @if ($sale->customer)<tr><td>{{ __('Customer') }}: {{ $sale->customer->name }} ({{ $sale->customer->phone }})</td></tr>@endif
        </table>
        <hr>
        <table>
            <thead><tr><th>{{ __('Item') }}</th><th class="right">{{ __('Qty') }}</th><th class="right">{{ __('Total') }}</th></tr></thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td colspan="3">{{ $item->product_name }}@if ($item->unit_quantity > 1) ({{ $item->unit_name }})@endif</td>
                    </tr>
                    <tr>
                        <td class="muted">{{ shop_digits(number_format((float) $item->unit_price, 2)) }} × </td>
                        <td class="right">{{ shop_digits($item->quantity + 0) }}</td>
                        <td class="right">{{ shop_digits(number_format((float) $item->line_total, 2)) }}</td>
                    </tr>
                    @if ($item->discount_amount > 0)
                        <tr><td colspan="3" class="muted">{{ __('Discount') }}: −{{ shop_digits(number_format((float) $item->discount_amount, 2)) }}</td></tr>
                    @endif
                    @if ($item->warranty_days)
                        <tr><td colspan="3" class="muted">{{ __('Warranty') }}: {{ shop_digits($item->warranty_days) }} {{ __('days') }} ({{ __('till') }} {{ shop_digits($sale->sold_at->copy()->addDays($item->warranty_days)->format('d/m/Y')) }})</td></tr>
                    @endif
                @endforeach
            </tbody>
        </table>
        <hr>
        <table class="totals">
            <tr><td>{{ __('Subtotal') }}</td><td class="right">{{ money($sale->subtotal) }}</td></tr>
            @if ($sale->discount_amount > 0)
                <tr><td>{{ __('Discount') }}@if ($sale->discount_type?->value === 'percent') ({{ shop_digits($sale->discount_value + 0) }}%)@endif</td><td class="right">− {{ money($sale->discount_amount) }}</td></tr>
            @endif
            @if ($sale->vat_amount > 0)
                <tr><td>{{ __('VAT') }}</td><td class="right">{{ money($sale->vat_amount) }}</td></tr>
            @endif
            <tr class="grand"><td>{{ __('Total') }}</td><td class="right">{{ money($sale->total) }}</td></tr>
            @foreach ($sale->payments->where('direction', \App\Enums\PaymentDirection::In) as $payment)
                <tr><td>{{ __('Paid by') }} {{ $payment->paymentMethod->localName() }}@if ($payment->transaction_ref) ({{ $payment->transaction_ref }})@endif</td><td class="right">{{ money($payment->amount) }}</td></tr>
            @endforeach
            @if ($sale->change_amount > 0)
                <tr><td>{{ __('Received') }}</td><td class="right">{{ money($sale->paid_amount) }}</td></tr>
                <tr class="bold"><td>{{ __('Change amount') }}</td><td class="right">{{ money($sale->change_amount) }}</td></tr>
            @endif
        </table>
        @if ($sale->status === \App\Enums\SaleStatus::Voided)
            <hr><div class="center bold">*** {{ __('VOIDED') }} ***</div>
        @endif
        @if ($sale->note)<hr><div>{{ $sale->note }}</div>@endif
        <hr>
        <div class="center">
            @if ($returnDays > 0)<div class="muted">{{ __('Exchange/return within :days days with this receipt.', ['days' => shop_digits($returnDays)]) }}</div>@endif
            @if ($footer)<div style="margin-top:1mm">{{ $footer }}</div>@endif
        </div>
    </div>
    @if ($autoPrint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
    @endif
</body>
</html>
