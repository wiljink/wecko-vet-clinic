<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use RecordsActivity;

    public const KINDS = ['product' => 'Product', 'vaccine' => 'Vaccine', 'service' => 'Service'];

    protected $fillable = [
        'kind', 'name', 'description', 'code', 'barcode', 'group_id', 'animal_size',
        'tax_rate', 'mark_up', 'unit_cost_ex_tax', 'list_cost_price',
        'sell_price_ex_tax', 'sell_price_inc_tax', 'dispense_fee', 'dispense_fee_always',
        'discountable', 'list_this_product', 'print_label', 'home_care_note',
        'patient_reminder_type_id', 'regime_id',
        'supplier_id', 'pack_qty', 'qty_on_hand', 'reorder_level', 'max_holding', 'has_expiry',
        'protection', 'certificate_template_id', 'prints_certificate',
        'booster_years', 'booster_months', 'booster_days', 'next_vaccination_note',
        'is_active',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'mark_up' => 'decimal:2',
        'unit_cost_ex_tax' => 'decimal:4',
        'list_cost_price' => 'decimal:4',
        'sell_price_ex_tax' => 'decimal:2',
        'sell_price_inc_tax' => 'decimal:2',
        'dispense_fee' => 'decimal:2',
        'pack_qty' => 'decimal:2',
        'qty_on_hand' => 'decimal:2',
        'reorder_level' => 'decimal:2',
        'max_holding' => 'decimal:2',
        'dispense_fee_always' => 'boolean',
        'discountable' => 'boolean',
        'print_label' => 'boolean',
        'has_expiry' => 'boolean',
        'prints_certificate' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            // Keep the tax-inclusive sell price in step with the ex-tax price.
            $product->sell_price_inc_tax = round(
                (float) $product->sell_price_ex_tax * (1 + (float) $product->tax_rate / 100),
                2,
            );

            if (! $product->code && \App\Models\CompanySetting::current()->auto_generate_product_code) {
                $product->code = strtoupper(\Illuminate\Support\Str::random(8));
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function regime(): BelongsTo
    {
        return $this->belongsTo(RegimeType::class, 'regime_id');
    }

    public function reminderType(): BelongsTo
    {
        return $this->belongsTo(PatientReminderType::class, 'patient_reminder_type_id');
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'certificate_template_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Products consumed each time this vaccine is administered. */
    public function consumables(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'vaccine_consumables', 'vaccine_id', 'product_id')
            ->withPivot('qty')->withTimestamps();
    }

    public function tracksStock(): bool
    {
        return in_array($this->kind, ['product', 'vaccine'], true);
    }

    public function boosterInterval(): array
    {
        return [
            'years' => $this->booster_years,
            'months' => $this->booster_months,
            'days' => $this->booster_days,
        ];
    }

    public function scopeKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function scopeSellable(Builder $query, string $channel): Builder
    {
        // $channel: 'consult' or 'otc'
        return $query->where('is_active', true)
            ->whereIn('list_this_product', ['both', $channel]);
    }

    public function scopeBelowReorder(Builder $query): Builder
    {
        return $query->whereColumn('qty_on_hand', '<', 'reorder_level')
            ->where('reorder_level', '>', 0);
    }
}
