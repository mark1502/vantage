<?php

namespace Database\Seeders;

use App\Models\File;
use Illuminate\Database\Seeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class FileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        File::insert([
            ['name' => 'Reserved File 1 - Not File Specific',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'Reserved File 2',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'Reserved File 3',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'Reserved File 4',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'Reserved File 5',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'Reserved File 6',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'Reserved File 7',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'Reserved File 8',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'Reserved File 9',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['name' => 'Reserved File 10',
                'firm_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
