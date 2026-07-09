<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    protected $fillable = [
        'purchase_id', 'user_id', 'method', 'amount', 'paid_on', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * The staff representative who recorded/made this payment to the supplier.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function methodLabel(string $method): string
    {
        return ['cash' => 'Cash', 'bank' => 'Bank Transfer', 'mobile_money' => 'Mobile Money'][$method] ?? ucfirst($method);
    }
}
