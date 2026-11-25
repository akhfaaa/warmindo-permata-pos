<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashShift extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'opened_at', 'closed_at', 'opening_cash',
        'expected_cash', 'actual_cash', 'discrepancy', 'notes', 'status',
    ];
    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'discrepancy' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'shift_id');
    }
}