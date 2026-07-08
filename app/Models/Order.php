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

    public function getOrderNumberAttribute(): string
    {
        return 'ORD-'.str_pad((string) (999 + $this->id), 4, '0', STR_PAD_LEFT);
    }

    public static function paymentLabel(string $method): string
    {
        return ['cash' => 'Cash', 'card' => 'Card', 'mobile' => 'Mobile Pay'][$method] ?? ucfirst($method);
    }
}
