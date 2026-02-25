<?php

namespace Database\Factories;

use App\Models\Firm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $lastName = fake()->lastName();
        $firstName = fake()->firstName();

        return [
            'firm_id' => Firm::factory(),
            'title' => fake()->title(),
            'last_name' => $lastName,
            'first_name' => $firstName,
            'display_name' => $firstName.' '.$lastName,
            'display_last_first' => $lastName.', '.$firstName,
        ];
    }
}
