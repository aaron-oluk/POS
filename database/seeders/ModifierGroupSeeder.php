<?php

namespace Database\Seeders;

use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Setting;
use App\Support\CurrencyConverter;
use App\Support\CurrencyDetector;
use Illuminate\Database\Seeder;

class ModifierGroupSeeder extends Seeder
{
    public function run(): void
    {
        $milkGroup = ModifierGroup::create(['name' => 'Milk Type', 'multiple' => false]);
        $milkGroup->options()->createMany([
            ['name' => 'Whole Milk', 'price_delta' => 0],
            ['name' => 'Oat Milk', 'price_delta' => 0.65],
            ['name' => 'Almond Milk', 'price_delta' => 0.65],
            ['name' => 'Skim Milk', 'price_delta' => 0],
        ]);

        $sizeGroup = ModifierGroup::create(['name' => 'Size', 'multiple' => false]);
        $sizeGroup->options()->createMany([
            ['name' => 'Regular', 'price_delta' => 0],
            ['name' => 'Large', 'price_delta' => 0.75],
        ]);

        $extrasGroup = ModifierGroup::create(['name' => 'Extras', 'multiple' => true]);
        $extrasGroup->options()->createMany([
            ['name' => 'Extra Shot', 'price_delta' => 0.90],
            ['name' => 'Vanilla Syrup', 'price_delta' => 0.60],
            ['name' => 'Caramel Syrup', 'price_delta' => 0.60],
            ['name' => 'Whipped Cream', 'price_delta' => 0.50],
        ]);

        $milkDrinks = Product::whereIn('name', ['Cappuccino', 'Latte', 'Mocha', 'Flat White', 'Chai Latte', 'Matcha Latte'])->get();
        $sizeableDrinks = Product::whereIn('name', ['Espresso', 'Americano', 'Cappuccino', 'Latte', 'Mocha', 'Cold Brew', 'Flat White', 'Green Tea', 'Chai Latte', 'Earl Grey', 'Matcha Latte'])->get();
        $espressoDrinks = Product::whereIn('name', ['Americano', 'Cappuccino', 'Latte', 'Mocha', 'Flat White'])->get();

        $milkGroup->products()->sync($milkDrinks->pluck('id'));
        $sizeGroup->products()->sync($sizeableDrinks->pluck('id'));
        $extrasGroup->products()->sync($espressoDrinks->pluck('id'));

        // Price deltas above are authored in plain USD-scale numbers, same as
        // ProductSeeder's prices — rescale them into the detected currency now
        // (SettingSeeder already did this for products, before this seeder ran).
        $settings = Setting::current();
        CurrencyConverter::convertModifierOptions((float) $settings->exchange_rate, CurrencyDetector::decimalsFor($settings->currency));
    }
}
