<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasUuids;

    protected $fillable = ['category_id', 'name', 'price', 'is_available'];
    protected $casts = ['price' => 'decimal:2', 'is_available' => 'boolean'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function recipes()
    {
        return $this->hasMany(ProductRecipe::class, 'product_id');
    }

    public function getEstimatedHppAttribute(): float
    {
        return (float) $this->recipes->sum(function ($recipe) {
            return $recipe->quantity_required * ($recipe->ingredient->avg_cost_per_unit ?? 0);
        });
    }
}