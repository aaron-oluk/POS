<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Rescales every stored monetary value (product prices/costs, order totals,
 * order line items) when the store's active currency changes, so numbers
 * always live natively in the currently selected currency rather than
 * needing a live multiply at every display site.
 */
class CurrencyConverter
{
    /**
     * @param  float  $oldRate  units of the previous currency per 1 USD
     * @param  float  $newRate  units of the new currency per 1 USD
     * @param  int  $decimals  decimal precision to round to for the new currency
     */
    public static function convertAll(float $oldRate, float $newRate, int $decimals = 2): void
    {
        if ($oldRate <= 0 || abs($newRate - $oldRate) < 1e-9) {
            return;
        }

        $ratio = $newRate / $oldRate;

        DB::transaction(function () use ($ratio, $decimals) {
            static::convertProducts($ratio, $decimals);
            static::convertOrders($ratio, $decimals);
        });
    }

    public static function convertProducts(float $ratio, int $decimals = 2): void
    {
        Product::query()->each(function (Product $product) use ($ratio, $decimals) {
            $product->update([
                'price' => round((float) $product->price * $ratio, $decimals),
                'cost' => round((float) $product->cost * $ratio, $decimals),
            ]);
        });
    }

    public static function convertOrders(float $ratio, int $decimals = 2): void
    {
        Order::query()->each(function (Order $order) use ($ratio, $decimals) {
            $order->update([
                'subtotal' => round((float) $order->subtotal * $ratio, $decimals),
                // A percentage discount is unitless; only a fixed-amount discount is a currency value.
                'discount_value' => $order->discount_type === 'fixed'
                    ? round((float) $order->discount_value * $ratio, $decimals)
                    : $order->discount_value,
                'discount_amount' => round((float) $order->discount_amount * $ratio, $decimals),
                'tax' => round((float) $order->tax * $ratio, $decimals),
                'tip' => round((float) $order->tip * $ratio, $decimals),
                'total' => round((float) $order->total * $ratio, $decimals),
            ]);
        });

        OrderItem::query()->each(function (OrderItem $item) use ($ratio, $decimals) {
            $item->update([
                'unit_price' => round((float) $item->unit_price * $ratio, $decimals),
                'total' => round((float) $item->total * $ratio, $decimals),
            ]);
        });
    }
}
