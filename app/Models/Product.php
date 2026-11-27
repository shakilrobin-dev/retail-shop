<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['sku', 'name', 'name_bn', 'category_id', 'brand_id', 'unit_id', 'measure_unit', 'measure_per_unit', 'measure_price', 'compatible_models', 'description', 'cost_price', 'min_selling_price', 'vat_rate', 'reorder_level', 'warranty_days', 'is_serialized', 'image_path', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'measure_per_unit' => 'decimal:6',
            'measure_price' => 'decimal:2',
            'min_selling_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'warranty_days' => 'integer',
            'is_serialized' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<ProductUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * @return HasOne<ProductUnit, $this>
     */
    public function baseUnit(): HasOne
    {
        return $this->hasOne(ProductUnit::class)->where('is_base', true);
    }

    /**
     * @return HasMany<Barcode, $this>
     */
    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class);
    }

    /**
     * @return HasMany<ProductStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Stock shown as packs + pieces, e.g. "3 Box + 4 Piece".
     * Uses the biggest pack unit. Needs the "units" relation loaded.
     */
    public function stockBreakdown(float $baseQuantity): string
    {
        $baseUnit = $this->units->firstWhere('is_base', true);
        $baseName = $baseUnit?->localName() ?? '';
        $pack = $this->units->where('is_base', false)->sortByDesc('quantity')->first();

        if (! $pack || (float) $pack->quantity <= 1 || $baseQuantity < (float) $pack->quantity) {
            return shop_digits($baseQuantity + 0).' '.$baseName;
        }

        $packs = (int) floor($baseQuantity / (float) $pack->quantity);
        $rest = round($baseQuantity - ($packs * (float) $pack->quantity), 3);
        $text = shop_digits($packs).' '.$pack->localName();

        return $rest > 0 ? $text.' + '.shop_digits($rest + 0).' '.$baseName : $text;
    }

    /**
     * Sold by a measurement such as sqft (e.g. tiles)?
     */
    public function isMeasured(): bool
    {
        return filled($this->measure_unit) && (float) $this->measure_per_unit > 0;
    }

    /**
     * Whole base units (pieces) needed to cover a measurement, e.g. 500 sqft of 1.77 sqft tiles = 283 pieces.
     */
    public function piecesForMeasure(float $measure): int
    {
        if (! $this->isMeasured() || $measure <= 0) {
            return 0;
        }

        return (int) ceil(round($measure / (float) $this->measure_per_unit, 6));
    }

    /**
     * Measurement covered by a number of base units, e.g. 283 pieces = 500.91 sqft.
     */
    public function measureForPieces(float $pieces): float
    {
        return round($pieces * (float) $this->measure_per_unit, 3);
    }

    /**
     * The unit people see: the measurement for tiles (sqft), otherwise the base unit (piece, kg, bag...).
     * Needs the "units" relation loaded for products without a measurement.
     */
    public function unitLabel(): string
    {
        return $this->isMeasured() ? (string) $this->measure_unit : (string) $this->units->firstWhere('is_base', true)?->localName();
    }

    /**
     * Stock as people count it: "500.91 sqft (28 Carton + 3 Piece)" for tiles, "3 Box + 4 Piece" otherwise.
     * Needs the "units" relation loaded.
     */
    public function stockLabel(float $baseQuantity): string
    {
        if (! $this->isMeasured()) {
            return $this->stockBreakdown($baseQuantity);
        }

        return shop_digits($this->measureForPieces($baseQuantity) + 0).' '.$this->measure_unit.' ('.$this->stockBreakdown($baseQuantity).')';
    }

    /**
     * Name in the current language.
     */
    public function localName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logExcept(['image_path'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
