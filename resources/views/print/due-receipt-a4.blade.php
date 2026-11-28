<x-print.a4-document :title="$receipt->receipt_no" :heading="__('MONEY RECEIPT')" :letterhead="$letterhead" :branch-name="$receipt->branch->localName()" :auto-print="$autoPrint">
    <x-slot:info>
        <span>{{ __('Receipt no') }}: <strong>{{ $receipt->receipt_no }}</strong></span>
        <span>{{ __('Date') }}: {{ shop_digits($receipt->paid_at->format('d/m/Y')) }}</span>
        <span>{{ __('Customer') }}: {{ $receipt->customer->name }}</span>
    </x-slot:info>

    <div class="parties">
        <div>
            <div class="label">{{ __('Received from') }}</div>
            <div class="bold">{{ $receipt->customer->name }}</div>
            @if ($receipt->customer->phone)<div>{{ __('Phone') }}: {{ $receipt->customer->phone }}</div>@endif
            @if ($receipt->customer->address)<div>{{ $receipt->customer->address }}</div>@endif
        </div>
        <table>
            <tr><td class="muted">{{ __('Receipt no') }}</td><td><strong>{{ $receipt->receipt_no }}</strong></td></tr>
            <tr><td class="muted">{{ __('Date') }}</td><td>{{ shop_digits($receipt->paid_at->format('d/m/Y h:i A')) }}</td></tr>
            <tr><td class="muted">{{ __('Branch') }}</td><td>{{ $receipt->branch->localName() }}</td></tr>
            <tr><td class="muted">{{ __('Received by') }}</td><td>{{ $receipt->user?->name }}</td></tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:9mm">#</th>
                <th>{{ __('Against invoice') }}</th>
                <th style="width:34mm">{{ __('Date') }}</th>
                <th class="right" style="width:34mm">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt->allocations as $allocation)
                <tr>
                    <td>{{ shop_digits($loop->iteration) }}</td>
                    <td>{{ $allocation->payable?->invoice_no }}</td>
                    <td>{{ shop_digits((string) $allocation->payable?->sold_at?->format('d/m/Y')) }}</td>
                    <td class="right">{{ money($allocation->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="after-items">
        <div></div>
        <table class="totals">
            <tr class="grand"><td>{{ __('Received') }}</td><td class="right">{{ money($receipt->amount) }}</td></tr>
            <tr><td>{{ __('Paid by') }} {{ $receipt->paymentMethod->localName() }}@if ($receipt->transaction_ref) ({{ $receipt->transaction_ref }})@endif</td><td></td></tr>
            <tr class="bold"><td>{{ __('Due remaining') }}</td><td class="right">{{ money($dueNow) }}</td></tr>
        </table>
    </div>

    @if ($receipt->note)<div class="note">{{ $receipt->note }}</div>@endif

    <div class="signs">
        <div>{{ __('Customer signature') }}</div>
        <div>{{ __('Authorised signature') }}</div>
    </div>

    <div class="doc-foot">
        @if ($footer)<div>{{ $footer }}</div>@endif
    </div>
</x-print.a4-document>
