<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_checkout_succeeds_when_self_checkout_is_disabled(): void
    {
        Setting::current()->update(['self_checkout_enabled' => false]);
        $user = User::factory()->create(['active' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Espresso', 'sku' => 'COF-001',
            'price' => 3.50, 'cost' => 0.80, 'stock' => 10, 'icon' => '☕',
        ]);

        $this->actingAs($user)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 5]],
        ])->assertOk();
    }

    public function test_cash_checkout_is_rejected_when_self_checkout_is_enabled(): void
    {
        Setting::current()->update(['self_checkout_enabled' => true]);
        $user = User::factory()->create(['active' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Espresso', 'sku' => 'COF-001',
            'price' => 3.50, 'cost' => 0.80, 'stock' => 10, 'icon' => '☕',
        ]);

        $this->actingAs($user)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 5]],
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_card_checkout_still_succeeds_when_self_checkout_is_enabled(): void
    {
        Setting::current()->update(['self_checkout_enabled' => true]);
        $user = User::factory()->create(['active' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Espresso', 'sku' => 'COF-001',
            'price' => 3.50, 'cost' => 0.80, 'stock' => 10, 'icon' => '☕',
        ]);

        $this->actingAs($user)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'card', 'amount' => 3.50]],
        ])->assertOk();
    }

    public function test_a_split_payment_including_cash_is_rejected_in_self_checkout_mode(): void
    {
        Setting::current()->update(['self_checkout_enabled' => true]);
        $user = User::factory()->create(['active' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Espresso', 'sku' => 'COF-001',
            'price' => 10, 'cost' => 2, 'stock' => 10, 'icon' => '☕',
        ]);

        $this->actingAs($user)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [
                ['method' => 'card', 'amount' => 5],
                ['method' => 'cash', 'amount' => 5],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_manager_can_toggle_self_checkout_from_settings(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        Setting::current();

        $this->actingAs($manager)->put('/settings/payment', [
            'tip_options' => '15, 18, 20',
            'default_tip' => '18%',
            'self_checkout_enabled' => '1',
        ])->assertRedirect();

        $this->assertTrue((bool) Setting::current()->fresh()->self_checkout_enabled);
    }
}
