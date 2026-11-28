<x-print.a4-document :title="$quotation->quotation_no" :heading="__('QUOTATION')" :letterhead="$letterhead" :branch-name="$quotation->branch->localName()" :auto-print="! request()->boolean('preview')">
    <x-slot:info>
        <span>{{ __('Quotation no') }}: <strong>{{ $quotation->quotation_no }}</strong></span>
        <span>{{ __('Date') }}: {{ shop_digits($quotation->quotation_date->format('d/m/Y')) }}</span>
        @if ($quotation->customerLabel())<span>{{ __('To') }}: {{ $quotation->customerLabel() }}</span>@endif
    </x-slot:info>

    <div class="parties">
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
                <th style="width:9mm">#</th>
                <th>{{ __('Description') }}</th>
                <th class="right" style="width:34mm">{{ __('Quantity') }}</th>
                <th class="right" style="width:28mm">{{ __('Rate') }}</th>
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
                        @if ($item->discount_amount > 0)<div class="small muted">{{ __('Discount') }}@if ($item->discount_percents) {{ \App\Services\DiscountChain::label($item->discount_percents) }}@endif: −{{ money($item->discount_amount) }}</div>@endif
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
        <div></div>
        <table class="totals">
            <tr><td>{{ __('Subtotal') }}</td><td class="right">{{ money($quotation->subtotal) }}</td></tr>
            @if ($quotation->discount_amount > 0)<tr><td>{{ __('Discount') }}</td><td class="right">− {{ money($quotation->discount_amount) }}</td></tr>@endif
            @if ($quotation->vat_amount > 0)<tr><td>{{ __('VAT') }}</td><td class="right">{{ money($quotation->vat_amount) }}</td></tr>@endif
            @if ($quotation->rounding_amount != 0)<tr><td>{{ __('Rounding') }}</td><td class="right">{{ money($quotation->rounding_amount) }}</td></tr>@endif
            <tr class="grand"><td>{{ __('Total') }}</td><td class="right">{{ money($quotation->total) }}</td></tr>
        </table>
    </div>

    @if ($quotation->note)<div class="note">{{ $quotation->note }}</div>@endif

    <div class="signs">
        <div>{{ __('Customer signature') }}</div>
        <div>{{ __('Authorised signature') }}</div>
    </div>
    @if ($letterhead['footer_note'])<div class="doc-foot">{{ $letterhead['footer_note'] }}</div>@endif
</x-print.a4-document>
