<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Mark Kowit',
            'email' => 'markkowit@gmail.com',
            'password' => Hash::make('testpass'),
            'user_type' => 'Admin',
            'firm_id' => 1,
            'welcomed' => true,
        ]);

        User::create([
            'name' => 'Mark Kowit',
            'email' => 'mkowit10@gmail.com',
            'password' => Hash::make('testpass'),
            'user_type' => 'Admin',
            'firm_id' => 2,
            'welcomed' => true,
        ]);

    }
}
