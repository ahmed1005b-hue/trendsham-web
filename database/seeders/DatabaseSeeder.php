<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'first_name' => 'مدير',
            'last_name' => 'النظام',
            'phone' => '0999999999',
            'email' => 'admin@trendsham.com',
            'password' => Hash::make('Admin@12345'),
            'role' => 'admin',
            'phone_verified_at' => now(),
        ]);

        $this->call([
            CategorySeeder::class,
            ShowcaseSlotSeeder::class,
        ]);
    }
}
