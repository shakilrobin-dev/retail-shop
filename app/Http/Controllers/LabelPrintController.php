<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Services\BarcodeService;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LabelPrintController extends Controller
{
    /**
     * Print page for barcode labels. items = "barcodeId:quantity,barcodeId:quantity".
     */
    public function __invoke(Request $request, Settings $settings, BarcodeService $barcodes): View
    {
        $wanted = collect(explode(',', (string) $request->query('items')))
            ->map(fn (string $pair): array => array_map('intval', explode(':', $pair) + [1 => 0]))
            ->filter(fn (array $pair): bool => $pair[0] > 0 && $pair[1] > 0);

        $records = Barcode::query()->with(['product', 'productUnit'])->whereIn('id', $wanted->pluck(0))->get()->keyBy('id');

        $labels = [];

        foreach ($wanted as [$barcodeId, $quantity]) {
            $barcode = $records->get($barcodeId);

            if (! $barcode) {
                continue;
            }

            $label = [
                'name' => $barcode->product->name,
                'unit' => $barcode->productUnit->is_base ? null : $barcode->productUnit->name,
                'price' => money($barcode->productUnit->selling_price),
                'code' => $barcode->code,
                'svg' => $barcodes->svg($barcode->code, 300, 60),
            ];

            for ($i = 0; $i < min(500, $quantity); $i++) {
                $labels[] = $label;
            }
        }

        [$columns, $rows] = array_map('intval', explode('x', (string) $settings->get('label_a4_layout')) + [1 => 8]);
        [$labelWidth, $labelHeight] = array_map('intval', explode('x', (string) $settings->get('label_size')) + [1 => 25]);

        return view('print.labels', [
            'labels' => $labels,
            'output' => $settings->get('label_output'),
            'columns' => max(1, $columns),
            'rows' => max(1, $rows),
            'labelWidth' => max(20, $labelWidth),
            'labelHeight' => max(15, $labelHeight),
            'shopName' => $settings->bool('label_show_shop_name') ? $settings->get('shop_name') : null,
            'showPrice' => $settings->bool('label_show_price'),
            'autoPrint' => ! $request->boolean('preview'),
        ]);
    }
}
