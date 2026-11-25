<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'invoice_number', 'user_id', 'shift_id', 'order_type',
        'payment_method', 'subtotal', 'total_amount', 'cash_received',
        'change_amount', 'status', 'void_reason', 'voided_at',
        'is_offline_synced', 'created_at',
    ];
    protected $casts = [
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'is_offline_synced' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }
}