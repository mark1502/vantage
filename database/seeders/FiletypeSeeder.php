<?php

namespace Database\Seeders;

use App\Models\Filetype;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class FiletypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Filetype::insert([
            [ 'name' => 'Personal Injury', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null, 
            'has_correspondence' => true,
            'has_pleadings' => true,
            'has_discovery' => true,
            'has_documents' => true,
            'has_memos' => true,
            'has_events' => true,
            'has_todo' => true,
            'has_phone' => true,
            'has_medrecs' => true,
            'has_medbills' => true,
            'has_costs' => true,
            'enable_file_SOL' => true,
            'set_as_default' => true
            ],
            [ 'name' => 'Contract', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null, 
            'has_correspondence' => true,
            'has_pleadings' => true,
            'has_discovery' => true,
            'has_documents' => true,
            'has_memos' => true,
            'has_events' => true,
            'has_todo' => true,
            'has_phone' => true,
            'has_medrecs' => false,
            'has_medbills' => false,
            'has_costs' => true,
            'enable_file_SOL' => true,
            'set_as_default' => false
            ],
            [ 'name' => 'Estate Planning', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null, 
            'has_correspondence' => true,
            'has_pleadings' => false,
            'has_discovery' => false,
            'has_documents' => true,
            'has_memos' => true,
            'has_events' => true,
            'has_todo' => true,
            'has_phone' => true,
            'has_medrecs' => false,
            'has_medbills' => false,
            'has_costs' => true,
            'enable_file_SOL' => false,
            'set_as_default' => false
            ],
            [ 'name' => 'Real Estate (non-litigation)', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null, 
            'has_correspondence' => true,
            'has_pleadings' => false,
            'has_discovery' => false,
            'has_documents' => true,
            'has_memos' => true,
            'has_events' => true,
            'has_todo' => true,
            'has_phone' => true,
            'has_medrecs' => false,
            'has_medbills' => false,
            'has_costs' => true,
            'enable_file_SOL' => false,
            'set_as_default' => false
            ]            
        ]);
    }
}
