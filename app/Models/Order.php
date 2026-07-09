<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'subtotal', 'discount_type', 'discount_value',
        'discount_amount', 'tax', 'tip', 'total', 'payment_method', 'status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax' => 'decimal:2',
            'tip' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function getOrderNumberAttribute(): string
    {
        return 'ORD-'.str_pad((string) (999 + $this->id), 4, '0', STR_PAD_LEFT);
    }

    public static function paymentLabel(string $method): string
    {
        return ['cash' => 'Cash', 'card' => 'Card', 'mobile' => 'Mobile Pay', 'split' => 'Split'][$method] ?? ucfirst($method);
    }

    /**
     * "Cash" / "Card" for a single method, or "Cash + Card" for a split order.
     * Relies on $payments already being eager-loaded to avoid N+1 queries.
     */
    public function getPaymentSummaryAttribute(): string
    {
        if ($this->payment_method !== 'split') {
            return static::paymentLabel($this->payment_method);
        }

        return $this->payments->pluck('method')->map(fn ($m) => static::paymentLabel($m))->implode(' + ');
    }
}
