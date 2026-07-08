<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            ['Admin', 'admin', 'admin@pos.com', null, true, '#e8a838', 'admin01'],
            ['Sarah Mitchell', 'admin', 'sarah@pos.com', '+1 (555) 111-2233', true, '#e8a838', 'cashier42'],
            ['Jake Thompson', 'barista', 'jake@pos.com', '+1 (555) 222-3344', true, '#34d399', 'barista88'],
            ['Maria Santos', 'cashier', 'maria@pos.com', '+1 (555) 333-4455', true, '#60a5fa', 'cashier55'],
            ['David Kim', 'barista', 'david@pos.com', '+1 (555) 444-5566', true, '#f87171', 'barista33'],
            ['Emily Chen', 'cashier', 'emily@pos.com', '+1 (555) 555-6677', false, '#a78bfa', 'cashier77'],
            ['Ryan O\'Brien', 'barista', 'ryan@pos.com', '+1 (555) 666-7788', true, '#fb923c', 'barista22'],
        ];

        foreach ($staff as [$name, $role, $email, $phone, $active, $color, $seed]) {
            User::create([
                'name' => $name,
                'role' => $role,
                'email' => $email,
                'phone' => $phone,
                'active' => $active,
                'color' => $color,
                'avatar_seed' => $seed,
                'password' => 'password',
            ]);
        }
    }
}
