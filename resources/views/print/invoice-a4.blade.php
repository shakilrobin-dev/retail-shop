<x-print.a4-document :title="$sale->invoice_no" :heading="__('INVOICE')" :letterhead="$letterhead" :branch-name="$sale->branch->localName()" :auto-print="$autoPrint">
    <x-slot:info>
        <span>{{ __('Invoice no') }}: <strong>{{ $sale->invoice_no }}</strong></span>
        <span>{{ __('Date') }}: {{ shop_digits($sale->sold_at->format('d/m/Y')) }}</span>
        @if ($sale->customer)<span>{{ __('Customer') }}: {{ $sale->customer->name }}</span>@endif
    </x-slot:info>

    <div class="parties">
        <div>
            <div class="label">{{ __('Bill to') }}</div>
            @if ($sale->customer)
                <div class="bold">{{ $sale->customer->name }}</div>
                @if ($sale->customer->phone)<div>{{ __('Phone') }}: {{ $sale->customer->phone }}</div>@endif
                @if ($sale->customer->address)<div>{{ $sale->customer->address }}</div>@endif
            @else
                <div class="bold">{{ __('Walk-in customer') }}</div>
            @endif
        </div>
        <table>
            <tr><td class="muted">{{ __('Invoice no') }}</td><td><strong>{{ $sale->invoice_no }}</strong></td></tr>
            <tr><td class="muted">{{ __('Date') }}</td><td>{{ shop_digits($sale->sold_at->format('d/m/Y h:i A')) }}</td></tr>
            <tr><td class="muted">{{ __('Branch') }}</td><td>{{ $sale->branch->localName() }}</td></tr>
            <tr><td class="muted">{{ __('Cashier') }}</td><td>{{ $sale->user?->name }}</td></tr>
            @if ($sale->due_date && $sale->due_amount > 0)<tr><td class="muted">{{ __('Pay by') }}</td><td>{{ shop_digits($sale->due_date->format('d/m/Y')) }}</td></tr>@endif
        </table>
    </div>

    @if ($header)<p class="muted" style="margin-bottom:2mm">{{ $header }}</p>@endif

    <table class="items">
        <thead>
            <tr>
                <th style="width:9mm">#</th>
                <th>{{ __('Description') }}</th>
                <th class="right" style="width:34mm">{{ __('Quantity') }}</th>
                <th class="right" style="width:28mm">{{ __('Rate') }}</th>
                <th class="right" style="width:30mm">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ shop_digits($loop->iteration) }}</td>
                    <td>
                        {{ $item->product_name }}
                        @if ($item->measure_quantity !== null)
                            <div class="small muted">{{ $item->product?->stockBreakdown((float) $item->base_quantity) }}</div>
                        @endif
                        @if ($item->discount_amount > 0)<div class="small muted">{{ __('Discount') }}: −{{ money($item->discount_amount) }}</div>@endif
                        @if ($item->warranty_days)
                            <div class="small muted">{{ __('Warranty') }}: {{ shop_digits($item->warranty_days) }} {{ __('days') }} ({{ __('till') }} {{ shop_digits($sale->sold_at->copy()->addDays($item->warranty_days)->format('d/m/Y')) }})</div>
                        @endif
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

    <div class="after-items">
        <div>
            @if ($sale->status === \App\Enums\SaleStatus::Voided)<div class="stamp">{{ __('VOIDED') }}</div>@endif
        </div>
        <table class="totals">
            <tr><td>{{ __('Subtotal') }}</td><td class="right">{{ money($sale->subtotal) }}</td></tr>
            @if ($sale->discount_amount > 0)
                <tr><td>{{ __('Discount') }}@if ($sale->discount_type?->value === 'percent') ({{ shop_digits($sale->discount_value + 0) }}%)@endif</td><td class="right">− {{ money($sale->discount_amount) }}</td></tr>
            @endif
            @if ($sale->vat_amount > 0)
                <tr><td>{{ __('VAT') }}</td><td class="right">{{ money($sale->vat_amount) }}</td></tr>
            @endif
            @if ($sale->rounding_amount != 0)
                <tr><td>{{ __('Rounding') }}</td><td class="right">{{ $sale->rounding_amount > 0 ? '+' : '−' }} {{ money(abs($sale->rounding_amount)) }}</td></tr>
            @endif
            <tr class="grand"><td>{{ __('Total') }}</td><td class="right">{{ money($sale->total) }}</td></tr>
            @foreach ($sale->payments->where('direction', \App\Enums\PaymentDirection::In) as $payment)
                <tr><td>{{ __('Paid by') }} {{ $payment->paymentMethod->localName() }}@if ($payment->transaction_ref) ({{ $payment->transaction_ref }})@endif</td><td class="right">{{ money($payment->amount) }}</td></tr>
            @endforeach
            @if ($sale->change_amount > 0)
                <tr><td>{{ __('Received') }}</td><td class="right">{{ money($sale->paid_amount) }}</td></tr>
                <tr class="bold"><td>{{ __('Change amount') }}</td><td class="right">{{ money($sale->change_amount) }}</td></tr>
            @endif
            @if ($sale->due_amount > 0)
                <tr class="bold"><td>{{ __('Due on this invoice') }}</td><td class="right">{{ money($sale->due_amount) }}</td></tr>
            @endif
            @if ($sale->customer && $customerDue > 0)
                @if ($customerDue - (float) $sale->due_amount > 0.009)
                    <tr><td>{{ __('Previous due') }}</td><td class="right">{{ money($customerDue - (float) $sale->due_amount) }}</td></tr>
                @endif
                <tr class="bold"><td>{{ __('Total due') }}</td><td class="right">{{ money($customerDue) }}</td></tr>
            @endif
        </table>
    </div>

    @if ($sale->note)<div class="note">{{ $sale->note }}</div>@endif

    <div class="signs">
        <div>{{ __('Customer signature') }}</div>
        <div>{{ __('Authorised signature') }}</div>
    </div>

    <div class="doc-foot">
        @if ($returnDays > 0)<div>{{ __('Exchange/return within :days days with this receipt.', ['days' => shop_digits($returnDays)]) }}</div>@endif
        @if ($footer)<div>{{ $footer }}</div>@endif
    </div>
</x-print.a4-document>
