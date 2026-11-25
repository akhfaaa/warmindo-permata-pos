<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'unit', 'current_stock', 'avg_cost_per_unit', 'min_stock_alert'];
    protected $casts = [
        'current_stock' => 'decimal:2',
        'avg_cost_per_unit' => 'decimal:2',
        'min_stock_alert' => 'decimal:2',
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }
}