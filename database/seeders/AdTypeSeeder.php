<?php

namespace Database\Seeders;

use App\Models\AdType;
use Illuminate\Database\Seeder;

class AdTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adTypes = [
            [
                'id' => 1,
                'name' => 'Free',
                'description' => 'Basic listing with limited features',
                'price' => 0.00,
                'duration_days' => 30,
                'is_featured' => false,
            ],
            [
                'id' => 2,
                'name' => 'Premium',
                'description' => 'Enhanced listing with better visibility',
                'price' => 29.99,
                'duration_days' => 60,
                'is_featured' => false,
            ],
            [
                'id' => 3,
                'name' => 'Featured',
                'description' => 'Top-tier listing with maximum visibility',
                'price' => 59.99,
                'duration_days' => 90,
                'is_featured' => true,
            ],
        ];

        foreach ($adTypes as $adType) {
            AdType::updateOrCreate(['id' => $adType['id']], $adType);
        }
    }
} 