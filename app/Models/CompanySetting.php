<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'coin_denominations' => 'array',
        'tax_rate' => 'decimal:2',
        'default_dispense_fee' => 'decimal:2',
        'default_injection_fee' => 'decimal:2',
        'show_reminders_on_login' => 'boolean',
        'auto_generate_product_code' => 'boolean',
        'auto_generate_barcode' => 'boolean',
        'pos_print_receipt' => 'boolean',
        'display_patients_per_client' => 'boolean',
        'open_discounting' => 'boolean',
    ];

    /** The single settings row, created on first access. */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public static function taxRate(): float
    {
        return (float) static::current()->tax_rate;
    }

    public static function currencySymbol(): string
    {
        return static::current()->currency_symbol ?: '₱';
    }
}
