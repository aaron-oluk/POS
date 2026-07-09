<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
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
            'supply_date' => '2026-07-08',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20, 'unit_cost' => 0.95],
            ],
        ]);

        $response->assertRedirect();
        $this->assertEquals(30, $product->fresh()->stock);
        $this->assertEquals(0.95, (float) $product->fresh()->cost);
        $this->assertDatabaseHas('purchases', ['supplier_id' => $supplier->id, 'reference_no' => 'INV-1001', 'total' => 19.00]);
        $this->assertSame('2026-07-08', Purchase::first()->supply_date->format('Y-m-d'));
        $this->assertDatabaseHas('purchase_items', ['product_id' => $product->id, 'quantity' => 20, 'unit_cost' => 0.95]);

        // "Amount Paid" left blank defaults to paid-in-full, matching the pre-payment-tracking behavior.
        $purchase = Purchase::first();
        $this->assertEquals(19.00, (float) $purchase->amount_paid);
        $this->assertEquals(0, $purchase->balance_due);
        $this->assertSame('paid', $purchase->payment_status);
    }

    public function test_a_purchase_can_be_recorded_as_partially_paid_on_credit(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $supplier = Supplier::create(['name' => 'Highland Coffee Roasters']);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Espresso', 'sku' => 'COF-001',
            'price' => 3.50, 'cost' => 0.80, 'stock' => 10, 'icon' => '☕',
        ]);

        $this->actingAs($manager)->post('/purchases', [
            'supplier_id' => $supplier->id,
            'supply_date' => '2026-07-08',
            'amount_paid' => 5,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20, 'unit_cost' => 0.95],
            ],
        ])->assertRedirect();

        $purchase = Purchase::first();
        $this->assertEquals(5, (float) $purchase->amount_paid);
        $this->assertEquals(14, $purchase->balance_due);
        $this->assertSame('partial', $purchase->payment_status);

        // Settle the remaining balance later, on a different date.
        $this->actingAs($manager)->put("/purchases/{$purchase->id}/pay", ['amount' => 14])
            ->assertRedirect();

        $purchase->refresh();
        $this->assertEquals(19, (float) $purchase->amount_paid);
        $this->assertEquals(0, $purchase->balance_due);
        $this->assertSame('paid', $purchase->payment_status);
    }

    public function test_supplier_payment_cannot_exceed_the_outstanding_balance(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $supplier = Supplier::create(['name' => 'Highland Coffee Roasters']);
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Espresso', 'sku' => 'COF-001',
            'price' => 3.50, 'cost' => 0.80, 'stock' => 10, 'icon' => '☕',
        ]);

        $this->actingAs($manager)->post('/purchases', [
            'supplier_id' => $supplier->id,
            'supply_date' => '2026-07-08',
            'amount_paid' => 0,
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 1]],
        ]);

        $purchase = Purchase::first();

        $this->actingAs($manager)->put("/purchases/{$purchase->id}/pay", ['amount' => 100])
            ->assertRedirect();

        $this->assertEquals(0, (float) $purchase->fresh()->amount_paid);
    }

    public function test_cashier_cannot_access_purchases(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'active' => true]);

        $this->actingAs($cashier)->get('/purchases')->assertForbidden();
    }
}
