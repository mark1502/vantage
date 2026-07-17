<?php

namespace Database\Seeders;

use App\Models\Pref_default;
use Illuminate\Database\Seeder;

class PrefDefaultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pref_default::insert([
            ['name' => 'event_bg',
                'prompt' => 'Select Event Background Color:',
                'setting' => '#fff68f',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'event_text',
                'prompt' => 'Select Event Text Color:',
                'setting' => '#000000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'event_hover_placement',
                'prompt' => 'Event Hover Display Placement:',
                'setting' => 'upper_right',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'file_open_to',
                'prompt' => 'Open Files at:',
                'setting' => 'correspondence',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'file_recent_spot',
                'prompt' => 'Open recent files where I left off:',
                'setting' => 'false',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'theme',
                'prompt' => 'My Default Theme:',
                'setting' => 'light',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
