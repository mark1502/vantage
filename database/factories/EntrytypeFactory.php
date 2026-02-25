<?php

namespace Database\Factories;

use App\Models\Firm;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Entrytype>
 */
class EntrytypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firm_id' => Firm::factory(),
            'folder_id' => Folder::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
