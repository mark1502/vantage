<?php

namespace Database\Seeders;

use App\Models\Firm;
use Illuminate\Database\Seeder;

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
        ]);

        Firm::create([
            'name' => 'Vantage Sample Firm 1',
            'email' => 'sample_1@wayopened.com',
        ]);

        Firm::create([
            'name' => 'Vantage Sample Firm 2',
            'email' => 'sample_2@wayopened.com',
        ]);

        Firm::create([
            'name' => 'Vantage Sample Firm 3',
            'email' => 'sample_3@wayopened.com',
        ]);

        Firm::create([
            'name' => 'Vantage Sample Firm 4',
            'email' => 'sample_4@wayopened.com',
        ]);
    }
}
