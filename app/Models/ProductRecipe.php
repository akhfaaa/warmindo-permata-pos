<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductRecipe extends Model
{
    use HasUuids;

    protected $fillable = ['product_id', 'modifier_id', 'ingredient_id', 'quantity_required'];
    protected $casts = ['quantity_required' => 'decimal:2'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}