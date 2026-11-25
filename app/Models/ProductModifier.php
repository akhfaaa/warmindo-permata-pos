<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductModifier extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'price', 'is_active'];
    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];

    public function recipes()
    {
        return $this->hasMany(ProductRecipe::class, 'modifier_id');
    }

    public function getEstimatedHppAttribute(): float
    {
        return (float) $this->recipes->sum(function ($recipe) {
            return $recipe->quantity_required * ($recipe->ingredient->avg_cost_per_unit ?? 0);
        });
    }
}