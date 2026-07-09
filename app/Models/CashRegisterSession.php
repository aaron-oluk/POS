<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegisterSession extends Model
{
    protected $fillable = [
        'user_id', 'opened_at', 'closed_at', 'opening_float',
        'expected_cash', 'counted_cash', 'discrepancy', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'discrepancy' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cash received on completed orders rung up by this session's cashier
     * between opening and now (or close time once closed).
     */
    public function cashSales(): float
    {
        $end = $this->closed_at ?? now();

        return (float) OrderPayment::where('method', 'cash')
            ->whereHas('order', fn ($q) => $q->where('user_id', $this->user_id)->where('status', 'completed'))
            ->whereBetween('created_at', [$this->opened_at, $end])
            ->sum('amount');
    }
}
