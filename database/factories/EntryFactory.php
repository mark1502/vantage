<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Firm;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Entry>
 */
class EntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firm = Firm::factory();

        return [
            'firm_id' => $firm,
            'file_id' => File::factory(),
            'folder_id' => Folder::factory(),
            'entrytype_id' => Entrytype::factory(),
            'from_contact_id' => Contact::factory(),
            'date1' => fake()->dateTime(),
        ];
    }
}
