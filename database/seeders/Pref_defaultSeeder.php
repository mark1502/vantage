<?php

namespace Database\Seeders;

use App\Models\Pref_default;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class pref_defaultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pref_default::insert([
            [   'name' => 'event_bg', 
                'prompt' => 'Select Event Background Color:',
                'setting' => '#fff68f', 
                'created_at' => now(), 
                'updated_at' => now()
            ],
            [   'name' => 'event_text', 
                'prompt' => 'Select Event Text Color:',
                'setting' => '#000000', 
                'created_at' => now(), 
                'updated_at' => now()
            ],
        ]);
    }
}
