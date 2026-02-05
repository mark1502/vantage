<?php

namespace Database\Seeders;

use App\Models\Entrytype;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class EntrytypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Entrytype::insert([
            [ 'firm_id' => 1, 'folder_id' => 1, 'name' => 'Letter', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 1, 'name' => 'Email', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 1, 'name' => 'Fax', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 1, 'name' => 'Text Message', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 2, 'name' => 'Complaint', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 2, 'name' => 'Answer', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 2, 'name' => 'Motion to Compel', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 2, 'name' => 'Response to Motion to Compel', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 2, 'name' => 'Motion for Summary Judgment', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 2, 'name' => 'Response to Motion for Summary Judgment', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 3, 'name' => 'Interrogatories', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 3, 'name' => 'Answers to Interrogatores', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 3, 'name' => 'Request for Production', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 3, 'name' => 'Response to Request for Production', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 4, 'name' => 'Social Security Records', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 4, 'name' => 'Insurance Records', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 4, 'name' => 'Education Records', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 5, 'name' => 'Memo', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 6, 'name' => 'Meeting', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 6, 'name' => 'Hearing', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 6, 'name' => 'Deposition', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 6, 'name' => 'Trial', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 6, 'name' => 'Deadline', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 6, 'name' => 'Deadline - Discovery', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 6, 'name' => 'Deadline - Statute of Limitations', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 7, 'name' => 'To-Do', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 8, 'name' => 'Telephoned', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 8, 'name' => 'Please Call', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 8, 'name' => 'Returning Your Call', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 8, 'name' => 'Will Call Again', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 8, 'name' => 'Urgent', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 9, 'name' => 'Hospital Records', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 9, 'name' => 'Patient Records', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 9, 'name' => 'Treatment Records', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 10, 'name' => 'Medical Bill', 'created_at' => now(), 'updated_at' => now() ],
            [ 'firm_id' => 1, 'folder_id' => 11, 'name' => 'Case Cost', 'created_at' => now(), 'updated_at' => now() ],
        ]);
    }
}
