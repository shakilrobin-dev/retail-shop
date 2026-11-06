@php
    $width = match ($paper) { '58' => '58mm', 'a4' => '190mm', default => '80mm' };
    $isThermal = $paper !== 'a4';
@endphp
@fonts
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: {!! app()->getLocale() === 'bn' ? "'Hind Siliguri', " : '' !!}Arial, sans-serif; color: #000; font-size: {{ $paper === '58' ? '10px' : '12px' }}; }
    .receipt { width: {{ $width }}; padding: {{ $isThermal ? '2mm 3mm' : '10mm' }}; margin: 0 auto; }
    .center { text-align: center; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .shop { font-size: 1.5em; font-weight: bold; }
    .big { font-size: 1.4em; font-weight: bold; }
    .muted { color: #333; }
    hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
    .cut { border-top: 2px dashed #000; margin: 5mm 0 3mm; text-align: center; font-size: 0.8em; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 0.6mm 0; vertical-align: top; }
    th { text-align: left; border-bottom: 1px dashed #000; }
    .grand td { font-size: 1.3em; font-weight: bold; border-top: 1px dashed #000; padding-top: 1mm; }
    svg { width: 100%; height: auto; max-height: 14mm; }
    @page { size: {{ $isThermal ? $width.' auto' : 'A4' }}; margin: {{ $isThermal ? '0' : '10mm' }}; }
    @media screen { body { background: #e2e8f0; padding: 16px 0; } .receipt { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2); } }
</style>
