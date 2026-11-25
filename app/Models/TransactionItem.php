<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id', 'product_id', 'quantity', 'unit_price',
        'modifiers_json', 'item_hpp_snapshot', 'notes',
    ];
    protected $casts = [
        'unit_price' => 'decimal:2',
        'modifiers_json' => 'array',
        'item_hpp_snapshot' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}