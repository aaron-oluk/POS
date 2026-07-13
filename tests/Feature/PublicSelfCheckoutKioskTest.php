<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSelfCheckoutKioskTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_page_is_not_found_when_self_checkout_is_disabled(): void
    {
        Setting::current()->update(['self_checkout_enabled' => false]);

        $this->get('/self-checkout')->assertNotFound();
    }

    public function test_kiosk_page_is_reachable_without_logging_in_when_enabled(): void
    {
        Setting::current()->update(['self_checkout_enabled' => true]);

        $this->get('/self-checkout')->assertOk();
    }

    public function test_guest_can_complete_a_card_checkout_at_the_kiosk(): void
    {
        Setting::current()->update(['self_checkout_enabled' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Espresso', 'sku' => 'COF-001',
            'price' => 3.50, 'cost' => 0.80, 'stock' => 10, 'icon' => '☕',
        ]);

        $response = $this->postJson('/self-checkout/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'card', 'amount' => 3.50]],
        ])->assertOk();

        $this->assertDatabaseCount('orders', 1);
        $order = Order::first();
        $this->assertSame(User::kiosk()->id, $order->user_id);
        $this->assertNull($order->customer_id);
        $this->assertSame('Self-Checkout Kiosk', $response->json('order.cashier'));
    }

    public function test_kiosk_checkout_rejects_cash(): void
    {
        Setting::current()->update(['self_checkout_enabled' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Espresso', 'sku' => 'COF-001',
            'price' => 3.50, 'cost' => 0.80, 'stock' => 10, 'icon' => '☕',
        ]);

        $this->postJson('/self-checkout/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 5]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_kiosk_checkout_is_not_found_when_self_checkout_is_disabled(): void
    {
        Setting::current()->update(['self_checkout_enabled' => false]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Espresso', 'sku' => 'COF-001',
            'price' => 3.50, 'cost' => 0.80, 'stock' => 10, 'icon' => '☕',
        ]);

        $this->postJson('/self-checkout/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'card', 'amount' => 3.50]],
        ])->assertNotFound();
    }

    public function test_kiosk_system_user_is_excluded_from_staff_management(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);

        $response = $this->actingAs($manager)->get('/staff');

        $response->assertOk();
        $response->assertDontSee(User::kiosk()->name);
    }
}
