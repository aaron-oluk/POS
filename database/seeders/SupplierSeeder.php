<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['Highland Coffee Roasters', 'Marcus Webb', '+1 (555) 201-3344', 'orders@highlandroasters.com', '412 Roastery Ln, Portland, OR'],
            ['Golden Valley Dairy', 'Priya Anand', '+1 (555) 402-8871', 'sales@goldenvalleydairy.com', '88 Farm Rd, Sonoma, CA'],
            ['Artisan Bakehouse Supply', 'Elena Ruiz', '+1 (555) 733-1290', 'wholesale@artisanbakehouse.com', '19 Flour St, Austin, TX'],
            ['Pacific Paper & Packaging', 'Dan Osei', '+1 (555) 660-4455', 'accounts@pacificpaperpkg.com', '2200 Industrial Way, Seattle, WA'],
        ];

        foreach ($suppliers as [$name, $contact, $phone, $email, $address]) {
            Supplier::create([
                'name' => $name,
                'contact_name' => $contact,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
            ]);
        }
    }
}
