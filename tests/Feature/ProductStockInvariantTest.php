<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockInvariantTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $stock = 5): Product
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

    public function test_a_product_cannot_be_created_with_negative_stock(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->makeProduct(-1);
    }

    public function test_a_products_stock_cannot_be_updated_to_negative(): void
    {
        $product = $this->makeProduct(5);

        $this->expectException(\RuntimeException::class);

        $product->update(['stock' => -1]);
    }

    public function test_decrementing_stock_below_zero_is_blocked(): void
    {
        $product = $this->makeProduct(2);

        $this->expectException(\RuntimeException::class);

        $product->decrement('stock', 5);
    }
}
