<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->quotation_no }}</title>
    @fonts
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: {!! app()->getLocale() === 'bn' ? "'Hind Siliguri', " : '' !!}Arial, sans-serif; color: #111; font-size: 12px; }
        .page { width: 190mm; margin: 0 auto; padding: 10mm; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; border-bottom: 2px solid #111; padding-bottom: 3mm; }
        .shop { font-size: 20px; font-weight: bold; }
        .muted { color: #555; }
        .right { text-align: right; }
        .center { text-align: center; }
        .title { margin: 5mm 0 3mm; text-align: center; font-size: 16px; font-weight: bold; letter-spacing: 2px; }
        .meta { display: flex; justify-content: space-between; gap: 6mm; margin-bottom: 4mm; }
        .meta table td { padding: 0.5mm 2mm 0.5mm 0; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 1.5mm 2mm; text-align: left; font-size: 11px; }
        table.items td { border: 1px solid #cbd5e1; padding: 1.5mm 2mm; vertical-align: top; }
        .small { font-size: 10.5px; }
        .totals { width: 75mm; margin-left: auto; margin-top: 3mm; border-collapse: collapse; }
        .totals td { padding: 1mm 2mm; }
        .totals .grand td { border-top: 2px solid #111; font-size: 15px; font-weight: bold; }
        .note { margin-top: 5mm; padding: 2.5mm 3mm; border: 1px dashed #94a3b8; white-space: pre-line; }
        .signs { display: flex; justify-content: space-between; margin-top: 18mm; }
        .signs div { width: 55mm; border-top: 1px solid #111; padding-top: 1mm; text-align: center; }
        .logo { max-height: 16mm; max-width: 45mm; }
        @page { size: A4; margin: 8mm; }
        @media screen { body { background: #e2e8f0; padding: 16px 0; } .page { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2); } }
    </style>
</head>
<body>
    <div class="page">
        <div class="head">
            <div>
                @if ($letterhead['logo_url'])<img src="{{ $letterhead['logo_url'] }}" alt="" class="logo">@endif
                <div class="shop">{{ $letterhead['name'] }}</div>
                @if ($letterhead['tagline'])<div class="muted">{{ $letterhead['tagline'] }}</div>@endif
            </div>
            <div class="right">
                <div><strong>{{ __('Branch') }}: {{ $quotation->branch->localName() }}</strong></div>
                @if ($letterhead['address'])<div>{{ $letterhead['address'] }}</div>@endif
                @if ($letterhead['phone'])<div>{{ __('Phone') }}: {{ $letterhead['phone'] }}</div>@endif
                @if ($letterhead['bin'])<div>{{ __('BIN') }}: {{ $letterhead['bin'] }}</div>@endif
            </div>
        </div>

        <div class="title">{{ __('QUOTATION') }}</div>

        <div class="meta">
            <table>
                <tr><td class="muted">{{ __('To') }}</td><td><strong>{{ $quotation->customerLabel() ?? '—' }}</strong></td></tr>
                @if ($quotation->customer_phone)<tr><td class="muted">{{ __('Phone') }}</td><td>{{ $quotation->customer_phone }}</td></tr>@endif
                @if ($quotation->customer_address)<tr><td class="muted">{{ __('Address / site') }}</td><td>{{ $quotation->customer_address }}</td></tr>@endif
            </table>
            <table>
                <tr><td class="muted">{{ __('Quotation no') }}</td><td><strong>{{ $quotation->quotation_no }}</strong></td></tr>
                <tr><td class="muted">{{ __('Date') }}</td><td>{{ shop_digits($quotation->quotation_date->format('d M Y')) }}</td></tr>
                @if ($quotation->valid_until)<tr><td class="muted">{{ __('Valid until') }}</td><td>{{ shop_digits($quotation->valid_until->format('d M Y')) }}</td></tr>@endif
                <tr><td class="muted">{{ __('Prepared by') }}</td><td>{{ $quotation->user?->name }}</td></tr>
            </table>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:8mm">#</th>
                    <th>{{ __('Description') }}</th>
                    <th class="right" style="width:34mm">{{ __('Quantity') }}</th>
                    <th class="right" style="width:26mm">{{ __('Rate') }}</th>
                    <th class="right" style="width:30mm">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotation->items as $item)
                    <tr>
                        <td>{{ shop_digits($loop->iteration) }}</td>
                        <td>
                            {{ $item->product_name }}
                            @if ($item->measure_quantity !== null)
                                <div class="small muted">{{ $item->product?->stockBreakdown((float) $item->base_quantity) }}</div>
                            @endif
                            @if ($item->discount_amount > 0)<div class="small muted">{{ __('Discount') }}: −{{ money($item->discount_amount) }}</div>@endif
                        </td>
                        @if ($item->measure_quantity !== null)
                            <td class="right">{{ shop_digits($item->measure_quantity + 0) }} {{ $item->measure_unit }}</td>
                            <td class="right">{{ money($item->measure_rate) }}<span class="small muted">/{{ $item->measure_unit }}</span></td>
                        @else
                            <td class="right">{{ shop_digits($item->quantity + 0) }} {{ $item->unit_name }}</td>
                            <td class="right">{{ money($item->unit_price) }}</td>
                        @endif
                        <td class="right">{{ money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td>{{ __('Subtotal') }}</td><td class="right">{{ money($quotation->subtotal) }}</td></tr>
            @if ($quotation->discount_amount > 0)<tr><td>{{ __('Discount') }}</td><td class="right">− {{ money($quotation->discount_amount) }}</td></tr>@endif
            @if ($quotation->vat_amount > 0)<tr><td>{{ __('VAT') }}</td><td class="right">{{ money($quotation->vat_amount) }}</td></tr>@endif
            @if ($quotation->rounding_amount != 0)<tr><td>{{ __('Rounding') }}</td><td class="right">{{ money($quotation->rounding_amount) }}</td></tr>@endif
            <tr class="grand"><td>{{ __('Total') }}</td><td class="right">{{ money($quotation->total) }}</td></tr>
        </table>

        @if ($quotation->note)<div class="note">{{ $quotation->note }}</div>@endif

        <div class="signs">
            <div>{{ __('Customer signature') }}</div>
            <div>{{ __('Authorised signature') }}</div>
        </div>
        @if ($letterhead['footer_note'])<p class="center muted small" style="margin-top:6mm">{{ $letterhead['footer_note'] }}</p>@endif
    </div>
    @unless (request()->boolean('preview'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endunless
</body>
</html>
