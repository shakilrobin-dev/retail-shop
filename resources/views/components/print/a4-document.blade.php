@props(['title', 'heading', 'letterhead', 'branchName' => null, 'autoPrint' => true])

{{--
    A4 printed document (invoice, quotation, money receipt).
    The letterhead and the document bar sit in a table header, so the browser repeats them on every printed page.
    The page number is drawn in the page margin ("Page 1 of 2").
    @var array{name: string|null, tagline: string|null, address: string|null, phone: string|null, bin: string|null, logo_url: string|null, footer_note: string|null} $letterhead
--}}
@php
    $counterStyle = shop_digits('1') !== '1' ? ', bengali' : '';
    $pageText = '"'.strtr(addslashes(__('Page :page of :total')), [':total' => '" counter(pages'.$counterStyle.') "', ':page' => '" counter(page'.$counterStyle.') "']).'"';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @fonts
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: {!! app()->getLocale() === 'bn' ? "'Hind Siliguri', " : '' !!}Arial, sans-serif; color: #111; font-size: 12px; line-height: 1.35; }
        .sheet { width: 190mm; margin: 0 auto; border-collapse: collapse; }
        .sheet > thead > tr > td, .sheet > tbody > tr > td { padding: 0; vertical-align: top; }
        .doc-head { display: flex; justify-content: space-between; align-items: center; gap: 8mm; }
        .doc-brand { display: flex; align-items: center; gap: 4mm; }
        .doc-logo { max-height: 18mm; max-width: 40mm; object-fit: contain; }
        .doc-shop { font-size: 20px; font-weight: bold; line-height: 1.15; }
        .doc-contact { text-align: right; font-size: 11px; }
        .doc-rule { border-top: 2px solid #111; border-bottom: 1px solid #94a3b8; height: 3px; margin: 3mm 0 2.5mm; }
        .doc-bar { display: flex; justify-content: space-between; align-items: baseline; gap: 6mm; padding-bottom: 3mm; margin-bottom: 3mm; border-bottom: 1px solid #cbd5e1; }
        .doc-title { font-size: 16px; font-weight: bold; letter-spacing: {{ app()->getLocale() === 'bn' ? '0' : '2px' }}; }
        .doc-bar-info { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 1mm 5mm; font-size: 11.5px; }
        .muted { color: #555; }
        .small { font-size: 10.5px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .parties { display: flex; justify-content: space-between; gap: 6mm; margin-bottom: 4mm; }
        .parties table td { padding: 0.5mm 3mm 0.5mm 0; vertical-align: top; }
        .label { font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #555; margin-bottom: 0.5mm; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 1.5mm 2mm; text-align: left; font-size: 11px; }
        table.items td { border: 1px solid #cbd5e1; padding: 1.5mm 2mm; vertical-align: top; }
        table.items th.right { text-align: right; }
        table.items tr { break-inside: avoid; }
        .after-items { display: flex; justify-content: space-between; align-items: flex-start; gap: 6mm; margin-top: 3mm; break-inside: avoid; }
        .totals { width: 80mm; margin-left: auto; border-collapse: collapse; }
        .totals td { padding: 0.9mm 2mm; }
        .totals .grand td { border-top: 2px solid #111; border-bottom: 1px solid #111; font-size: 15px; font-weight: bold; }
        .note { margin-top: 4mm; padding: 2.5mm 3mm; border: 1px dashed #94a3b8; white-space: pre-line; break-inside: avoid; }
        .stamp { display: inline-block; margin-top: 4mm; padding: 1mm 4mm; border: 2px solid #b91c1c; color: #b91c1c; font-weight: bold; letter-spacing: 2px; }
        .signs { display: flex; justify-content: space-between; margin-top: 18mm; break-inside: avoid; }
        .signs div { width: 55mm; border-top: 1px solid #111; padding-top: 1mm; text-align: center; }
        .doc-foot { margin-top: 6mm; text-align: center; font-size: 10.5px; color: #555; }
        @page {
            size: A4;
            margin: 12mm 10mm 12mm;
            @top-right { content: {!! $pageText !!}; font-family: Arial, sans-serif; font-size: 9px; color: #555; }
        }
        @media screen {
            body { background: #e2e8f0; padding: 16px 0; }
            .sheet { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2); outline: 10mm solid #fff; margin: 10mm auto; }
        }
    </style>
</head>
<body>
    <table class="sheet">
        <thead>
            <tr>
                <td>
                    <div class="doc-head">
                        <div class="doc-brand">
                            @if ($letterhead['logo_url'])<img src="{{ $letterhead['logo_url'] }}" alt="" class="doc-logo">@endif
                            <div>
                                <div class="doc-shop">{{ $letterhead['name'] }}</div>
                                @if ($letterhead['tagline'])<div class="muted">{{ $letterhead['tagline'] }}</div>@endif
                            </div>
                        </div>
                        <div class="doc-contact">
                            @if ($branchName)<div class="bold">{{ __('Branch') }}: {{ $branchName }}</div>@endif
                            @if ($letterhead['address'])<div>{{ $letterhead['address'] }}</div>@endif
                            @if ($letterhead['phone'])<div>{{ __('Phone') }}: {{ $letterhead['phone'] }}</div>@endif
                            @if ($letterhead['bin'])<div>{{ __('BIN') }}: {{ $letterhead['bin'] }}</div>@endif
                        </div>
                    </div>
                    <div class="doc-rule"></div>
                    <div class="doc-bar">
                        <div class="doc-title">{{ $heading }}</div>
                        <div class="doc-bar-info">{{ $info ?? '' }}</div>
                    </div>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $slot }}</td>
            </tr>
        </tbody>
    </table>
    @if ($autoPrint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
