<?php

namespace Database\Seeders;

use App\Models\Firm;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class FirmSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Firm::create([
            'name' => 'Vantage System Firm',
            'email' => 'markkowit@gmail.com',
            'subscription_status' => false,
        ]);
    }
}
