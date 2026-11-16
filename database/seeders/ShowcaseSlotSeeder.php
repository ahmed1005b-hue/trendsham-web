<?php

namespace Database\Seeders;

use App\Models\ShowcaseSlot;
use Illuminate\Database\Seeder;

class ShowcaseSlotSeeder extends Seeder
{
    public function run(): void
    {
        // الأسعار أمثلة فقط، يضبطها المدير لاحقاً
        $slots = [
            ['key' => 'home_top',    'name' => 'أعلى الرئيسية (بعد بانر العروض)', 'position' => 10, 'price_per_day' => 20000],
            ['key' => 'home_mid',    'name' => 'منتصف الرئيسية',                  'position' => 20, 'price_per_day' => 12000],
            ['key' => 'home_bottom', 'name' => 'أسفل الرئيسية',                   'position' => 30, 'price_per_day' => 6000],
        ];

        foreach ($slots as $s) {
            // Seed defaults only when a slot is first created; preserve admin edits on later seeding.
            ShowcaseSlot::firstOrCreate(['key' => $s['key']], $s + ['is_active' => true]);
        }
    }
}
