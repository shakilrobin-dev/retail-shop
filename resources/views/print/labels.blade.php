<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Barcode labels') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, 'Hind Siliguri', sans-serif; color: #000; }
        .label { display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; text-align: center; padding: 1mm; }
        .label .shop { font-size: 6pt; font-weight: bold; }
        .label .name { font-size: 7pt; line-height: 1.1; max-height: 2.2em; overflow: hidden; width: 100%; }
        .label svg { width: 100%; height: auto; max-height: 55%; display: block; }
        .label .code { font-family: monospace; font-size: 7pt; letter-spacing: 0.5px; }
        .label .price { font-size: 9pt; font-weight: bold; }
        .toolbar { padding: 12px; background: #f1f5f9; display: flex; gap: 8px; align-items: center; font-size: 14px; }
        .toolbar button { padding: 6px 14px; font-size: 14px; cursor: pointer; }
        @media print { .toolbar { display: none; } }

        @if ($output === 'thermal')
            @page { size: {{ $labelWidth }}mm {{ $labelHeight }}mm; margin: 0; }
            .label { width: {{ $labelWidth }}mm; height: {{ $labelHeight }}mm; page-break-after: always; }
        @else
            @page { size: A4; margin: 8mm; }
            .sheet { display: grid; grid-template-columns: repeat({{ $columns }}, 1fr); grid-auto-rows: calc((297mm - 16mm) / {{ $rows }} - 0.2mm); }
            .label { border: 0.1mm dashed #ccc; }
            @media print { .label { border: none; } }
        @endif
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <span>{{ trans_choice(':count label|:count labels', count($labels), ['count' => count($labels)]) }}</span>
    </div>

    <div class="{{ $output === 'thermal' ? '' : 'sheet' }}">
        @foreach ($labels as $label)
            <div class="label">
                @if ($shopName)<div class="shop">{{ $shopName }}</div>@endif
                <div class="name">{{ $label['name'] }}@if ($label['unit']) ({{ $label['unit'] }})@endif</div>
                {!! $label['svg'] !!}
                <div class="code">{{ $label['code'] }}</div>
                @if ($showPrice)<div class="price">{{ $label['price'] }}</div>@endif
            </div>
        @endforeach
    </div>

    @if ($autoPrint)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
