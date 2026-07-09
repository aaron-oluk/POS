<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::all();
        $products = Product::all();
        $staff = User::all();
        $taxRate = (float) Setting::current()->tax_rate;

        $paymentMethods = ['cash', 'card', 'mobile'];
        $statuses = ['completed', 'completed', 'completed', 'completed', 'pending', 'pending', 'refunded', 'cancelled'];

        for ($i = 0; $i < 50; $i++) {
            $customer = $customers->random();
            $cashier = $staff->random();
            $numItems = rand(1, 4);
            $itemLines = [];
            $subtotal = 0;

            for ($j = 0; $j < $numItems; $j++) {
                $product = $products->random();
                $qty = rand(1, 3);
                $lineTotal = round($product->price * $qty, 2);
                $subtotal += $lineTotal;
                $itemLines[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $tax = round($subtotal * ($taxRate / 100), 2);
            $tip = rand(0, 1) ? round($subtotal * 0.18, 2) : 0;
            $total = round($subtotal + $tax + $tip, 2);
            $status = $statuses[array_rand($statuses)];

            $date = now()
                ->subDays(intdiv($i, 3))
                ->setTime(rand(8, 19), rand(0, 59));

            // ~1 in 6 orders is split across two payment methods, so the split-payment
            // feature (and its reporting) has real demo data from the very first run.
            $isSplit = $status === 'completed' && rand(1, 6) === 1;
            if ($isSplit) {
                $methodA = $paymentMethods[array_rand($paymentMethods)];
                do {
                    $methodB = $paymentMethods[array_rand($paymentMethods)];
                } while ($methodB === $methodA);
                $amountA = round($total * (rand(30, 70) / 100), 2);
                $amountB = round($total - $amountA, 2);
                $paymentBreakdown = [$methodA => $amountA, $methodB => $amountB];
            } else {
                $paymentBreakdown = [$paymentMethods[array_rand($paymentMethods)] => $total];
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'user_id' => $cashier->id,
                'subtotal' => $subtotal,
                'discount_type' => null,
                'discount_value' => 0,
                'discount_amount' => 0,
                'tax' => $tax,
                'tip' => $tip,
                'total' => $total,
                'payment_method' => $isSplit ? 'split' : array_key_first($paymentBreakdown),
                'status' => $status,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            foreach ($paymentBreakdown as $method => $amount) {
                OrderPayment::create([
                    'order_id' => $order->id,
                    'method' => $method,
                    'amount' => $amount,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }

            foreach ($itemLines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'product_icon' => $line['product']->icon,
                    'unit_price' => $line['product']->price,
                    'quantity' => $line['qty'],
                    'total' => $line['total'],
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
        }
    }
}
