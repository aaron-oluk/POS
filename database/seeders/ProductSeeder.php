<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['Espresso', 'Coffee', 3.50, 0.80, 150, 'COF-001', '☕', 'Single shot espresso'],
            ['Americano', 'Coffee', 4.00, 0.90, 120, 'COF-002', '☕', 'Espresso with hot water'],
            ['Cappuccino', 'Coffee', 5.50, 1.20, 100, 'COF-003', '☕', 'Espresso, steamed milk, foam'],
            ['Latte', 'Coffee', 5.50, 1.20, 95, 'COF-004', '☕', 'Espresso with steamed milk'],
            ['Mocha', 'Coffee', 6.00, 1.50, 80, 'COF-005', '☕', 'Chocolate espresso drink'],
            ['Cold Brew', 'Coffee', 5.00, 1.00, 60, 'COF-006', '🧊', '16hr steeped cold coffee'],
            ['Flat White', 'Coffee', 5.50, 1.20, 70, 'COF-007', '☕', 'Double shot, microfoam'],
            ['Green Tea', 'Tea', 3.50, 0.60, 200, 'TEA-001', '🍵', 'Japanese green tea'],
            ['Chai Latte', 'Tea', 5.00, 1.00, 90, 'TEA-002', '🍵', 'Spiced tea with milk'],
            ['Earl Grey', 'Tea', 3.50, 0.50, 180, 'TEA-003', '🍵', 'Bergamot black tea'],
            ['Matcha Latte', 'Tea', 5.50, 1.30, 45, 'TEA-004', '🍵', 'Ceremonial grade matcha'],
            ['Croissant', 'Pastries', 3.50, 0.90, 30, 'PST-001', '🥐', 'Butter croissant, flaky'],
            ['Blueberry Muffin', 'Pastries', 4.00, 1.00, 25, 'PST-002', '🧁', 'Fresh blueberry muffin'],
            ['Cinnamon Roll', 'Pastries', 4.50, 1.20, 18, 'PST-003', '🍩', 'Warm cinnamon roll with icing'],
            ['Turkey Club', 'Sandwiches', 8.50, 3.50, 15, 'SND-001', '🥪', 'Turkey, bacon, lettuce, tomato'],
            ['Caprese Panini', 'Sandwiches', 7.50, 2.80, 12, 'SND-002', '🥪', 'Mozzarella, tomato, basil'],
            ['Chicken Avocado', 'Sandwiches', 9.00, 3.80, 10, 'SND-003', '🥪', 'Grilled chicken, avocado'],
            ['Tiramisu', 'Desserts', 7.00, 2.00, 8, 'DSR-001', '🍰', 'Classic Italian tiramisu'],
            ['Cheesecake', 'Desserts', 6.50, 1.80, 12, 'DSR-002', '🍰', 'New York style cheesecake'],
            ['Orange Juice', 'Drinks', 4.50, 1.20, 40, 'DRK-001', '🍊', 'Freshly squeezed OJ'],
            ['Lemonade', 'Drinks', 4.00, 0.80, 50, 'DRK-002', '🍋', 'House-made lemonade'],
            ['Iced Chocolate', 'Drinks', 5.50, 1.40, 35, 'DRK-003', '🍫', 'Chocolate milk blended with ice'],
            ['Tumbler 16oz', 'Merchandise', 18.00, 6.00, 50, 'MRH-001', '🥤', 'Insulated tumbler, branded'],
            ['Tote Bag', 'Merchandise', 15.00, 4.50, 30, 'MRH-002', '👜', 'Canvas tote, logo print'],
        ];

        $categoryIds = Category::pluck('id', 'name');

        foreach ($products as [$name, $category, $price, $cost, $stock, $sku, $icon, $desc]) {
            Product::create([
                'category_id' => $categoryIds[$category],
                'name' => $name,
                'price' => $price,
                'cost' => $cost,
                'stock' => $stock,
                'sku' => $sku,
                'icon' => $icon,
                'description' => $desc,
            ]);
        }
    }
}
