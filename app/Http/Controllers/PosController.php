<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\OrderPayment;
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
            'products' => Product::with('category', 'modifierGroups.options')->orderBy('name')->get(),
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
            'items.*.modifier_option_ids' => ['nullable', 'array'],
            'items.*.modifier_option_ids.*' => ['integer', 'exists:modifier_options,id'],
            'discount_type' => ['nullable', 'in:percent,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tip_percent' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:cash,card,mobile'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        $settings = Setting::current();

        if ($settings->self_checkout_enabled && collect($data['payments'])->contains('method', 'cash')) {
            abort(422, 'Cash payments are not available in self-checkout mode.');
        }

        return DB::transaction(function () use ($data, $settings, $request) {
            $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $optionIds = collect($data['items'])->flatMap(fn ($i) => $i['modifier_option_ids'] ?? [])->unique();
            $modifierOptions = ModifierOption::whereIn('id', $optionIds)->get()->keyBy('id');

            $subtotal = 0;
            $lines = [];

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (! $product || $product->stock < $item['qty']) {
                    abort(422, "Not enough stock for {$product?->name}.");
                }

                $selectedModifiers = collect($item['modifier_option_ids'] ?? [])
                    ->map(fn ($id) => $modifierOptions->get($id))
                    ->filter();
                $unitPrice = round((float) $product->price + $selectedModifiers->sum(fn ($o) => (float) $o->price_delta), 2);

                $lineTotal = round($unitPrice * $item['qty'], 2);
                $subtotal += $lineTotal;

                $lines[] = [
                    'product' => $product,
                    'qty' => $item['qty'],
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                    'modifiers' => $selectedModifiers,
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

            // Card/mobile charge exactly what's allocated to them (no "change" concept);
            // only cash can overshoot, with the excess returned to the customer as change.
            $nonCashTotal = round(collect($data['payments'])->where('method', '!=', 'cash')->sum('amount'), 2);
            if ($nonCashTotal > $total + 0.01) {
                abort(422, 'Card/mobile payment amounts exceed the order total.');
            }

            $cashGiven = round(collect($data['payments'])->where('method', 'cash')->sum('amount'), 2);
            $remainingForCash = round($total - $nonCashTotal, 2);

            if ($remainingForCash > 0.01 && $cashGiven < $remainingForCash - 0.01) {
                abort(422, 'Insufficient payment amount.');
            }

            $cashApplied = $remainingForCash > 0 ? min($cashGiven, $remainingForCash) : 0;
            $changeDue = round($cashGiven - $cashApplied, 2);

            $appliedByMethod = [];
            foreach ($data['payments'] as $payment) {
                if ($payment['method'] === 'cash') {
                    continue;
                }
                $appliedByMethod[$payment['method']] = ($appliedByMethod[$payment['method']] ?? 0) + round($payment['amount'], 2);
            }
            if ($cashApplied > 0) {
                $appliedByMethod['cash'] = $cashApplied;
            }

            $methodsUsed = array_keys($appliedByMethod);
            $paymentMethod = count($methodsUsed) === 1 ? $methodsUsed[0] : 'split';

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
                'payment_method' => $paymentMethod,
                'status' => 'completed',
            ]);

            foreach ($appliedByMethod as $method => $amount) {
                OrderPayment::create([
                    'order_id' => $order->id,
                    'method' => $method,
                    'amount' => $amount,
                ]);
            }

            foreach ($lines as $line) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'product_icon' => $line['product']->icon,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['qty'],
                    'total' => $line['total'],
                ]);

                foreach ($line['modifiers'] as $option) {
                    OrderItemModifier::create([
                        'order_item_id' => $orderItem->id,
                        'modifier_option_id' => $option->id,
                        'name' => $option->name,
                        'price_delta' => $option->price_delta,
                    ]);
                }

                $line['product']->decrement('stock', $line['qty']);
            }

            $order->load('items.modifiers', 'customer', 'cashier', 'payments');

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
                        'modifiers' => $i->modifiers->pluck('name'),
                    ]),
                    'subtotal' => (float) $order->subtotal,
                    'discount_amount' => (float) $order->discount_amount,
                    'tax' => (float) $order->tax,
                    'tax_name' => $settings->tax_name,
                    'tip' => (float) $order->tip,
                    'total' => (float) $order->total,
                    'payment_method' => $order->payment_summary,
                    'payments' => $order->payments->map(fn ($p) => [
                        'method' => Order::paymentLabel($p->method),
                        'amount' => (float) $p->amount,
                    ]),
                    'change_due' => $changeDue,
                ],
                'remaining_stock' => $lines ? collect($lines)->mapWithKeys(fn ($l) => [$l['product']->id => $l['product']->fresh()->stock]) : [],
            ]);
        });
    }
}
