<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_record_a_purchase_and_stock_increments(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $supplier = Supplier::create(['name' => 'Highland Coffee Roasters']);
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

        $response = $this->actingAs($manager)->post('/purchases', [
            'supplier_id' => $supplier->id,
            'reference_no' => 'INV-1001',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20, 'unit_cost' => 0.95],
            ],
        ]);

        $response->assertRedirect();
        $this->assertEquals(30, $product->fresh()->stock);
        $this->assertEquals(0.95, (float) $product->fresh()->cost);
        $this->assertDatabaseHas('purchases', ['supplier_id' => $supplier->id, 'reference_no' => 'INV-1001', 'total' => 19.00]);
        $this->assertDatabaseHas('purchase_items', ['product_id' => $product->id, 'quantity' => 20, 'unit_cost' => 0.95]);
    }

    public function test_cashier_cannot_access_purchases(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'active' => true]);

        $this->actingAs($cashier)->get('/purchases')->assertForbidden();
    }
}
