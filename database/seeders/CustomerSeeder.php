<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            ['Emma', 'Johnson'], ['Liam', 'Williams'], ['Olivia', 'Brown'],
            ['Noah', 'Jones'], ['Ava', 'Garcia'], ['Ethan', 'Miller'],
            ['Sophia', 'Davis'], ['Mason', 'Rodriguez'], ['Isabella', 'Martinez'],
            ['Lucas', 'Hernandez'], ['Mia', 'Lopez'], ['James', 'Wilson'],
        ];

        $colors = ['#e8a838', '#34d399', '#60a5fa', '#f87171', '#a78bfa', '#fb923c', '#2dd4bf', '#f472b6'];

        foreach ($names as $i => [$first, $last]) {
            Customer::create([
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower("{$first}.{$last}@email.com"),
                'phone' => sprintf('+1 (555) %03d-%04d', 100 + $i * 37, (1000 + $i * 123) % 10000),
                'color' => $colors[$i % count($colors)],
                'created_at' => now()->subMonths(rand(1, 11))->subDays(rand(0, 27)),
            ]);
        }
    }
}
