<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosModifierCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_applies_modifier_price_deltas_to_the_line_total(): void
    {
        Setting::current();
        $user = User::factory()->create(['active' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Latte',
            'sku' => 'COF-004',
            'price' => 5.50,
            'cost' => 1.20,
            'stock' => 10,
            'icon' => '☕',
        ]);

        $milkGroup = ModifierGroup::create(['name' => 'Milk Type', 'multiple' => false]);
        $oatMilk = $milkGroup->options()->create(['name' => 'Oat Milk', 'price_delta' => 0.65]);
        $milkGroup->products()->attach($product->id);

        $response = $this->actingAs($user)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'qty' => 2, 'modifier_option_ids' => [$oatMilk->id]]],
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 6.15, 'total' => 12.30]);
        $this->assertDatabaseHas('order_item_modifiers', ['modifier_option_id' => $oatMilk->id, 'name' => 'Oat Milk', 'price_delta' => 0.65]);
        $this->assertEquals(8, $product->fresh()->stock);
    }

    public function test_manager_can_create_a_modifier_group_with_options_and_products(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Latte', 'sku' => 'COF-004',
            'price' => 5.50, 'cost' => 1.20, 'stock' => 10, 'icon' => '☕',
        ]);

        $response = $this->actingAs($manager)->post('/modifiers', [
            'name' => 'Size',
            'multiple' => false,
            'options' => [
                ['name' => 'Regular', 'price_delta' => 0],
                ['name' => 'Large', 'price_delta' => 0.75],
            ],
            'product_ids' => [$product->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('modifier_groups', ['name' => 'Size']);
        $this->assertDatabaseHas('modifier_options', ['name' => 'Large', 'price_delta' => 0.75]);
        $this->assertDatabaseHas('modifier_group_product', ['product_id' => $product->id]);
    }
}
