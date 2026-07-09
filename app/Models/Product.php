<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'sku', 'barcode', 'price', 'cost', 'stock', 'icon', 'description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    /**
     * Stock can never go negative. Checkout and stock-adjustment flows
     * already validate this with a friendly error before they touch the
     * database, but `increment`/`decrement` bypass `saving` (they only fire
     * `updating`) — guarding both events here is the last line of defense
     * against any code path, present or future, persisting a negative count.
     */
    protected static function booted(): void
    {
        $guardNonNegativeStock = function (self $product) {
            if ($product->stock < 0) {
                throw new \RuntimeException("Product #{$product->id} stock cannot go negative (attempted {$product->stock}).");
            }
        };

        static::saving($guardNonNegativeStock);
        static::updating($guardNonNegativeStock);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function modifierGroups()
    {
        return $this->belongsToMany(ModifierGroup::class, 'modifier_group_product');
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'out';
        }

        return $this->stock <= 15 ? 'low' : 'ok';
    }
}
