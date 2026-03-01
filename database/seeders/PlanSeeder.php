<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Plan::insert([
            [
                'name' => 'Vantage Standard Subscription',
                'slug' => 'standard',
                'stripe_product_id' => 'prod_U46ORXNxf1F9bP',
                'stripe_price_monthly' => 'price_1T626oRa3uhe8u1pbkk4ecGe',
                'stripe_price_quarterly' => 'price_1T626KRa3uhe8u1phAARGpGF',
                'stripe_price_yearly' => 'price_1T5yBkRa3uhe8u1paouuucbu',
                'description' => 'Standard per-user subscription with monthly, quarterly, and yearly billing options.',
                'base_price_monthly' => 1500,
                'base_price_quarterly' => 3600,
                'base_price_yearly' => 12000,
                'max_users' => null,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
