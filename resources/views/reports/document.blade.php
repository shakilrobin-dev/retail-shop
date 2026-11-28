{{--
    One official report document. Used on screen (inside the paper preview) and by mPDF.
    Layout uses tables only, so mPDF draws it the same as the browser.
    On screen, cells with a link in the row's '_links' open the invoices behind them (see ReportIndex).
    @var array $document
    @var bool $pdf
--}}
@php
    use App\Services\ReportDocument;
    $pdf ??= false;
    $head = $document['letterhead'];
    $logo = $pdf ? ($head['logo_path'] && is_file($head['logo_path']) ? $head['logo_path'] : null) : $head['logo_url'];
    $columns = $document['columns'];
    $linked = function (string $text, ?array $link) use ($pdf): string|\Illuminate\Support\HtmlString {
        if ($pdf || ! $link) {
            return $text;
        }

        $action = isset($link['sale']) ? 'viewInvoice('.(int) $link['sale'].')' : 'openInvoices('.\Illuminate\Support\Js::from($link).')';

        return new \Illuminate\Support\HtmlString('<button type="button" class="rpt-link" wire:click="'.e($action).'">'.e($text).'</button>');
    };
@endphp
<div class="rpt">
    <table class="rpt-letterhead">
        <tr>
            @if ($logo)
                <td class="rpt-logo"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <div class="rpt-shop">{{ $head['name'] }}</div>
                @if ($head['tagline'])<div class="rpt-tagline">{{ $head['tagline'] }}</div>@endif
                @if ($head['address'])<div class="rpt-small">{{ $head['address'] }}</div>@endif
            </td>
            <td class="rpt-contact">
                @if ($head['phone'])<div>{{ __('Phone') }}: {{ $head['phone'] }}</div>@endif
                @if ($head['bin'])<div>{{ __('VAT BIN') }}: {{ $head['bin'] }}</div>@endif
                <div>{{ __('Branch') }}: {{ $document['branch'] }}</div>
            </td>
        </tr>
    </table>
    <div class="rpt-rule"></div>

    <div class="rpt-title">{{ $document['title'] }}</div>
    @if ($document['subject'] ?? null)<div class="rpt-subject">{{ $document['subject'] }}</div>@endif
    <div class="rpt-period">{{ $document['period'] }}</div>
    @if (! empty($document['filters']))
        <div class="rpt-filters">{{ __('Filtered by') }}: {{ implode('  ·  ', $document['filters']) }}</div>
    @endif

    <table class="rpt-meta">
        <tr>
            <td><div class="rpt-meta-label">{{ __('Reference') }}</div>{{ $document['reference'] }}</td>
            <td><div class="rpt-meta-label">{{ __('Branch') }}</div>{{ $document['branch'] }}</td>
            <td><div class="rpt-meta-label">{{ __('Prepared by') }}</div>{{ $document['generated_by'] }}</td>
            <td><div class="rpt-meta-label">{{ __('Printed on') }}</div>{{ $document['generated_at'] }}</td>
        </tr>
    </table>

    @if ($columns)
        <table class="rpt-table">
            <thead>
                <tr>
                    <th class="rpt-sl">{{ __('SL') }}</th>
                    @foreach ($columns as $column)
                        <th class="{{ $column['align'] === 'right' ? 'rpt-r' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $serial = 0; @endphp
                @forelse ($document['rows'] as $row)
                    @php $style = $row['_style'] ?? null; @endphp
                    @if ($style === 'group' || $style === 'item')
                        <tr class="rpt-row-{{ $style }}"><td class="rpt-sl"></td><td colspan="{{ count($columns) }}">{{ $row['_label'] }}</td></tr>
                    @elseif ($style === 'subtotal')
                        <tr class="rpt-row-subtotal">
                            <td class="rpt-sl"></td>
                            @foreach ($columns as $column)
                                @php $value = $row[$column['key']] ?? null; @endphp
                                <td class="{{ $column['align'] === 'right' ? 'rpt-r rpt-num' : '' }}">{{ $value === null ? '' : (is_string($value) ? $value : ReportDocument::display($value, $column['format'])) }}</td>
                            @endforeach
                        </tr>
                    @else
                        @php $serial++; @endphp
                        <tr class="{{ $serial % 2 === 0 ? 'rpt-even' : '' }}">
                            <td class="rpt-sl">{{ shop_digits($serial) }}</td>
                            @foreach ($columns as $column)
                                <td class="{{ $column['align'] === 'right' ? 'rpt-r rpt-num' : '' }}">{{ $linked(ReportDocument::display($row[$column['key']] ?? null, $column['format']), $row['_links'][$column['key']] ?? null) }}</td>
                            @endforeach
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="{{ count($columns) + 1 }}" class="rpt-empty">{{ __('No records for this period.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($document['totals'] && $document['rows'])
                <tfoot>
                    <tr>
                        <td class="rpt-sl"></td>
                        @foreach ($columns as $column)
                            @php $value = $document['totals'][$column['key']] ?? null; @endphp
                            <td class="{{ $column['align'] === 'right' ? 'rpt-r rpt-num' : '' }}">{{ $value === null ? '' : $linked(is_string($value) ? $value : ReportDocument::display($value, $column['format']), $document['totals']['_links'][$column['key']] ?? null) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
        <div class="rpt-count">{{ __('Total records: :count', ['count' => shop_digits(collect($document['rows'])->whereNull('_style')->count())]) }}</div>
    @endif

    @foreach ($document['sections'] as $section)
        <table class="rpt-statement">
            @if ($section['title'])
                <tr><td colspan="2" class="rpt-section">{{ $section['title'] }}</td></tr>
            @endif
            @foreach ($section['lines'] as $line)
                @php [$label, $value, $format, $style] = $line; @endphp
                <tr class="rpt-line-{{ $style ?: 'plain' }}">
                    <td>{{ $label }}</td>
                    <td class="rpt-r rpt-num">{{ $linked(ReportDocument::display($value, $format), $line[4] ?? null) }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach

    @if ($document['notes'])
        <div class="rpt-notes">
            <div class="rpt-notes-title">{{ __('Notes') }}</div>
            @foreach ($document['notes'] as $note)
                <div>{{ shop_digits($loop->iteration) }}. {{ $note }}</div>
            @endforeach
        </div>
    @endif

    @if ($head['signatures'])
        <table class="rpt-signatures">
            <tr>
                @foreach ([__('Prepared by'), __('Checked by'), __('Approved by')] as $role)
                    <td>
                        <div class="rpt-sign-line"></div>
                        <div class="rpt-sign-role">{{ $role }}</div>
                        <div class="rpt-small">{{ $loop->first ? $document['generated_by'] : __('Name & date') }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @unless ($pdf)
        <table class="rpt-footer">
            <tr>
                <td>{{ $head['footer_note'] }}</td>
                <td class="rpt-r">{{ $document['reference'] }}</td>
            </tr>
        </table>
    @endunless
</div>
