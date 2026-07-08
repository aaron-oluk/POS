<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\CurrencyConverter;
use App\Support\CurrencyDetector;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $currency = CurrencyDetector::detect();

        Setting::create([
            'id' => 1,
            'store_name' => 'Nexus Coffee & Co.',
            'phone' => '+1 (555) 123-4567',
            'email' => 'hello@nexuscoffee.com',
            'address' => '123 Main Street, Downtown',
            'currency' => $currency['code'],
            'currency_symbol' => $currency['symbol'],
            'exchange_rate' => $currency['rate'],
            'timezone' => CurrencyDetector::detectTimezone(),
            'dark_mode' => true,
            'compact_mode' => false,
            'sound_effects' => true,
            'receipt_header' => "Nexus Coffee & Co.\n123 Main Street, Downtown\n+1 (555) 123-4567",
            'receipt_footer' => "Thank you for visiting!\nFollow us @nexuscoffee\nwww.nexuscoffee.com",
            'paper_size' => '80mm (Thermal)',
            'font_size' => 'Medium',
            'show_qr' => true,
            'auto_print' => false,
            'cash_enabled' => true,
            'card_enabled' => true,
            'mobile_enabled' => true,
            'gift_cards_enabled' => true,
            'split_payment_enabled' => true,
            'tip_options' => '15, 18, 20, 25',
            'default_tip' => '18%',
            'prompt_tips' => true,
            'tax_rate' => 8.5,
            'tax_name' => 'Sales Tax',
            'tax_included' => false,
            'round_tax' => true,
        ]);

        // Seed prices are authored in plain USD-scale numbers (e.g. Espresso 3.50);
        // rescale them into the detected currency now, before any orders are seeded.
        CurrencyConverter::convertProducts($currency['rate'], CurrencyDetector::decimalsFor($currency['code']));
    }
}
