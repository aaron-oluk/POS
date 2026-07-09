<?php

namespace App\Support;

use App\Models\CashRegisterSession;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use Illuminate\Support\Facades\DB;

/**
 * Rescales every stored monetary value (product prices/costs, order totals,
 * order line items, modifier price deltas, purchase costs, cash register
 * float/counts) when the store's active currency changes, so numbers always
 * live natively in the currently selected currency rather than needing a
 * live multiply at every display site.
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
            static::convertModifierOptions($ratio, $decimals);
            static::convertPurchases($ratio, $decimals);
            static::convertCashRegisterSessions($ratio, $decimals);
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

    public static function convertModifierOptions(float $ratio, int $decimals = 2): void
    {
        ModifierOption::query()->each(function (ModifierOption $option) use ($ratio, $decimals) {
            $option->update(['price_delta' => round((float) $option->price_delta * $ratio, $decimals)]);
        });
    }

    public static function convertPurchases(float $ratio, int $decimals = 2): void
    {
        Purchase::query()->each(function (Purchase $purchase) use ($ratio, $decimals) {
            $purchase->update([
                'total' => round((float) $purchase->total * $ratio, $decimals),
                'amount_paid' => round((float) $purchase->amount_paid * $ratio, $decimals),
            ]);
        });

        PurchaseItem::query()->each(function (PurchaseItem $item) use ($ratio, $decimals) {
            $item->update([
                'unit_cost' => round((float) $item->unit_cost * $ratio, $decimals),
                'total' => round((float) $item->total * $ratio, $decimals),
            ]);
        });

        PurchasePayment::query()->each(function (PurchasePayment $payment) use ($ratio, $decimals) {
            $payment->update(['amount' => round((float) $payment->amount * $ratio, $decimals)]);
        });
    }

    public static function convertCashRegisterSessions(float $ratio, int $decimals = 2): void
    {
        CashRegisterSession::query()->each(function (CashRegisterSession $session) use ($ratio, $decimals) {
            $session->update([
                'opening_float' => round((float) $session->opening_float * $ratio, $decimals),
                'expected_cash' => $session->expected_cash !== null ? round((float) $session->expected_cash * $ratio, $decimals) : null,
                'counted_cash' => $session->counted_cash !== null ? round((float) $session->counted_cash * $ratio, $decimals) : null,
                'discrepancy' => $session->discrepancy !== null ? round((float) $session->discrepancy * $ratio, $decimals) : null,
            ]);
        });
    }
}
