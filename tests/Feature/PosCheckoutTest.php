<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_an_order_and_decrements_stock(): void
    {
        Setting::current();
        $user = User::factory()->create(['active' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Espresso',
            'sku' => 'COF-001',
            'price' => 3.50,
            'cost' => 0.80,
            'stock' => 10,
            'icon' => '☕',
        ]);

        $response = $this->actingAs($user)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('order.items.0.qty', 2);

        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'status' => 'completed', 'payment_method' => 'cash']);
        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 2]);
        $this->assertEquals(8, $product->fresh()->stock);
    }

    public function test_checkout_rejects_insufficient_stock(): void
    {
        $user = User::factory()->create(['active' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Espresso',
            'sku' => 'COF-001',
            'price' => 3.50,
            'cost' => 0.80,
            'stock' => 1,
            'icon' => '☕',
        ]);

        $this->actingAs($user)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_supports_split_payment_across_multiple_methods(): void
    {
        Setting::current();
        $user = User::factory()->create(['active' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Espresso',
            'sku' => 'COF-001',
            'price' => 10,
            'cost' => 2,
            'stock' => 10,
            'icon' => '☕',
        ]);

        // 2 x $10 = $20 subtotal, no tax configured by default seed path here (Setting::current() defaults tax_rate 8.5,
        // but no category override) — just assert the order is created as a split payment covering the total.
        $response = $this->actingAs($user)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payments' => [
                ['method' => 'card', 'amount' => 15],
                ['method' => 'cash', 'amount' => 10],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'order.payments');

        $order = \App\Models\Order::first();
        $this->assertSame('split', $order->payment_method);
        $this->assertDatabaseHas('order_payments', ['order_id' => $order->id, 'method' => 'card', 'amount' => 15]);
        $this->assertDatabaseCount('order_payments', 2);
    }
}
