<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default roles for firm 1 (system firm)
        // These will be copied to new firms when they register
        Role::insert([
            ['name' => 'Client', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Opposing Party', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Attorney', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Opposing Counsel', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Witness', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Expert Witness', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Co-Counsel', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Guardian', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Insurance Adjuster', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Court Reporter', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mediator', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other', 'firm_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
