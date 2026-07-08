<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
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
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'status' => $status,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

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
