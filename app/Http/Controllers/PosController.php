<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        return view('pos.index', [
            'categories' => Category::orderBy('name')->pluck('name'),
            'products' => Product::with('category')->orderBy('name')->get(),
            'customers' => Customer::orderBy('first_name')->get(),
            'settings' => Setting::current(),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tip_percent' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:cash,card,mobile'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        $settings = Setting::current();

        return DB::transaction(function () use ($data, $settings, $request) {
            $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $lines = [];

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (! $product || $product->stock < $item['qty']) {
                    abort(422, "Not enough stock for {$product?->name}.");
                }

                $lineTotal = round($product->price * $item['qty'], 2);
                $subtotal += $lineTotal;

                $lines[] = [
                    'product' => $product,
                    'qty' => $item['qty'],
                    'total' => $lineTotal,
                ];
            }
            $subtotal = round($subtotal, 2);

            $discountType = $data['discount_type'] ?? null;
            $discountValue = (float) ($data['discount_value'] ?? 0);
            $discountAmount = 0;
            if ($discountType === 'percent') {
                $discountValue = min(max($discountValue, 0), 100);
                $discountAmount = $subtotal * ($discountValue / 100);
            } elseif ($discountType === 'fixed') {
                $discountAmount = min($discountValue, $subtotal);
            }
            $discountAmount = round($discountAmount, 2);
            $afterDiscount = $subtotal - $discountAmount;

            $tax = 0;
            foreach ($lines as $line) {
                $share = $subtotal > 0 ? $line['total'] / $subtotal : 0;
                $lineAfterDiscount = $line['total'] - ($discountAmount * $share);
                $category = $line['product']->category;
                $rate = $category?->tax_exempt ? 0 : (float) ($category?->tax_rate ?? $settings->tax_rate);
                $tax += $lineAfterDiscount * ($rate / 100);
            }
            $tax = round($tax, 2);

            $tipPercent = (float) ($data['tip_percent'] ?? 0);
            $tip = round($afterDiscount * ($tipPercent / 100), 2);

            $total = round($afterDiscount + $tax + $tip, 2);

            if ($data['payment_method'] === 'cash') {
                $cashReceived = (float) ($data['cash_received'] ?? 0);
                if ($cashReceived < $total) {
                    abort(422, 'Insufficient cash received.');
                }
            }

            $order = Order::create([
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'tax' => $tax,
                'tip' => $tip,
                'total' => $total,
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'product_icon' => $line['product']->icon,
                    'unit_price' => $line['product']->price,
                    'quantity' => $line['qty'],
                    'total' => $line['total'],
                ]);
                $line['product']->decrement('stock', $line['qty']);
            }

            $order->load('items', 'customer', 'cashier');

            return response()->json([
                'order' => [
                    'order_number' => $order->order_number,
                    'created_at' => $order->created_at->toIso8601String(),
                    'customer' => $order->customer?->full_name ?? 'Walk-in Customer',
                    'cashier' => $order->cashier->name,
                    'items' => $order->items->map(fn ($i) => [
                        'name' => $i->product_name,
                        'icon' => $i->product_icon,
                        'qty' => $i->quantity,
                        'unit_price' => (float) $i->unit_price,
                        'total' => (float) $i->total,
                    ]),
                    'subtotal' => (float) $order->subtotal,
                    'discount_amount' => (float) $order->discount_amount,
                    'tax' => (float) $order->tax,
                    'tax_name' => $settings->tax_name,
                    'tip' => (float) $order->tip,
                    'total' => (float) $order->total,
                    'payment_method' => Order::paymentLabel($order->payment_method),
                ],
                'remaining_stock' => $lines ? collect($lines)->mapWithKeys(fn ($l) => [$l['product']->id => $l['product']->fresh()->stock]) : [],
            ]);
        });
    }
}
