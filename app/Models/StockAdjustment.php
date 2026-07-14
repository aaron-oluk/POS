<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'type', 'reason', 'quantity', 'stock_before', 'stock_after', 'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function reasonLabel(string $reason): string
    {
        return ['purchase' => 'Purchase', 'waste' => 'Waste', 'damage' => 'Damage', 'theft' => 'Theft', 'recount' => 'Recount', 'other' => 'Other'][$reason] ?? ucfirst($reason);
    }
}
