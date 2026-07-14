<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $stock = 10): Product
    {
        $category = Category::create(['name' => 'Coffee']);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Espresso',
            'sku' => 'COF-001',
            'price' => 3.50,
            'cost' => 0.80,
            'stock' => $stock,
            'icon' => '☕',
        ]);
    }

    public function test_manager_can_record_a_decrease_adjustment_with_a_reason(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $product = $this->makeProduct(10);

        $response = $this->actingAs($manager)->post('/stock-management', [
            'product_id' => $product->id,
            'type' => 'decrease',
            'reason' => 'waste',
            'quantity' => 3,
            'notes' => 'Dropped tray of pastries',
        ]);

        $response->assertRedirect();
        $this->assertEquals(7, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $product->id,
            'type' => 'decrease',
            'reason' => 'waste',
            'quantity' => 3,
            'stock_before' => 10,
            'stock_after' => 7,
        ]);
    }

    public function test_decrease_cannot_exceed_current_stock(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $product = $this->makeProduct(2);

        $this->actingAs($manager)->post('/stock-management', [
            'product_id' => $product->id,
            'type' => 'decrease',
            'reason' => 'theft',
            'quantity' => 5,
        ]);

        $this->assertEquals(2, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_adjustments', 0);
    }

    public function test_cashier_cannot_access_stock_adjustments(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'active' => true]);

        $this->actingAs($cashier)->get('/stock-management')->assertForbidden();
    }
}
