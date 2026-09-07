<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandardConsultItem extends Model
{
    protected $fillable = ['standard_consult_id', 'product_id', 'kind', 'qty'];

    protected $casts = ['qty' => 'decimal:2'];

    public function standardConsult(): BelongsTo
    {
        return $this->belongsTo(StandardConsult::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
