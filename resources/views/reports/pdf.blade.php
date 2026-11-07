<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $document['title'] }}</title>
    @include('reports.styles')
    <style>
        body { font-family: hindsiliguri; }
        .rpt-signatures { page-break-inside: avoid; }
    </style>
</head>
<body>
    {{-- Running header from page 2, footer with page numbers on every page. --}}
    <htmlpageheader name="running">
        <table class="rpt rpt-running" width="100%"><tr>
            <td>{{ $document['letterhead']['name'] }} — {{ $document['title'] }}</td>
            <td style="text-align: right">{{ $document['period'] }}</td>
        </tr></table>
    </htmlpageheader>
    <htmlpagefooter name="footer">
        <table class="rpt rpt-footer" width="100%" style="margin-top: 0"><tr>
            <td width="45%">{{ $document['letterhead']['footer_note'] }}</td>
            <td width="30%" style="text-align: center">{{ $document['reference'] }}</td>
            <td width="25%" style="text-align: right">{{ __('Page :page of :total', ['page' => '{PAGENO}', 'total' => '{nbpg}']) }}</td>
        </tr></table>
    </htmlpagefooter>
    <sethtmlpageheader name="running" value="on" show-this-page="0" />
    <sethtmlpagefooter name="footer" value="on" />

    @include('reports.document', ['document' => $document, 'pdf' => true])
</body>
</html>
