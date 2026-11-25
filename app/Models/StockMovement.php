<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasUuids;

    protected $fillable = [
        'ingredient_id', 'type', 'quantity_change',
        'cost_per_unit', 'reference_id', 'notes',
    ];
    protected $casts = [
        'quantity_change' => 'decimal:2',
        'cost_per_unit' => 'decimal:2',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}