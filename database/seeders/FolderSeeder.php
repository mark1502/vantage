<?php

namespace Database\Seeders;

use App\Models\Folder;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class FolderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Folder::insert([
            [ 'name' => 'Correspondence', 'input_time' => false, 'date1_prompt' => 'Date:', 'date2_prompt' => 'Sent/Received:', 'hide_date2_prompt' => false, 
            'from_prompt' => 'From:', 'to_prompt' => 'To:', 'hide_to_prompt' => false, 'entrytype_prompt' => 'Type:', 'hide_entrytype_prompt' => false, 'note_prompt' => 'Re:', 
            'responseexpected_prompt' => 'Response expected:', 'hide_responseexpected_prompt' => false, 'hide_isResponsive' => false, 'amount_prompt' => null, 'hide_amount_prompt' => true, 
            'short_name' => 'correspondence', 'created_at' => now(), 'updated_at' => now() ],

            [ 'name' => 'Pleadings', 'input_time' => false, 'date1_prompt' => 'Filed:', 'date2_prompt' => 'Served:', 'hide_date2_prompt' => false, 
            'from_prompt' => 'Filed By:', 'to_prompt' => 'Served To:', 'hide_to_prompt' => false, 'entrytype_prompt' => 'Filing Type:', 'hide_entrytype_prompt' => false, 'note_prompt' => 'Note:', 
            'responseexpected_prompt' => 'Response expected:', 'hide_responseexpected_prompt' => false, 'hide_isResponsive' => false, 'amount_prompt' => null, 'hide_amount_prompt' => true, 
            'short_name' => 'pleadings', 'created_at' => now(), 'updated_at' => now() ],

            [ 'name' => 'Discovery', 'input_time' => false, 'date1_prompt' => 'Served:', 'date2_prompt' => 'Received:', 'hide_date2_prompt' => false, 
            'from_prompt' => 'Served By:', 'to_prompt' => 'Served To:', 'hide_to_prompt' => false, 'entrytype_prompt' => 'Request Type:', 'hide_entrytype_prompt' => false, 'note_prompt' => 'Note:', 
            'responseexpected_prompt' => 'Response expected:', 'hide_responseexpected_prompt' => false, 'hide_isResponsive' => false, 'amount_prompt' => null, 'hide_amount_prompt' => true, 
            'short_name' => 'discovery', 'created_at' => now(), 'updated_at' => now() ],

            [ 'name' => 'Documents', 'input_time' => false, 'date1_prompt' => 'Received:', 'date2_prompt' => 'Dated:', 'hide_date2_prompt' => false, 
            'from_prompt' => 'From:', 'to_prompt' => null, 'hide_to_prompt' => true, 'entrytype_prompt' => 'Document Type:', 'hide_entrytype_prompt' => false, 'note_prompt' => 'Additional Information:', 
            'responseexpected_prompt' => null, 'hide_responseexpected_prompt' => true, 'hide_isResponsive' => false, 'amount_prompt' => null, 'hide_amount_prompt' => true, 
            'short_name' => 'documents', 'created_at' => now(), 'updated_at' => now() ],

            [ 'name' => 'Memos', 'input_time' => false, 'date1_prompt' => 'Date:', 'date2_prompt' => null, 'hide_date2_prompt' => true, 
            'from_prompt' => 'From:', 'to_prompt' => 'To:', 'hide_to_prompt' => false, 'entrytype_prompt' => 'Memo Type:', 'hide_entrytype_prompt' => true, 'note_prompt' => 'Memo:', 
            'responseexpected_prompt' => 'Response expected:', 'hide_responseexpected_prompt' => false, 'hide_isResponsive' => false, 'amount_prompt' => null, 'hide_amount_prompt' => true, 
            'short_name' => 'memos', 'created_at' => now(), 'updated_at' => now() ],

            [ 'name' => 'Events', 'input_time' => true, 'date1_prompt' => 'Start:', 'date2_prompt' => 'End:', 'hide_date2_prompt' => false, 
            'from_prompt' => 'Event For:', 'to_prompt' => null, 'hide_to_prompt' => true, 'entrytype_prompt' => 'Event Type:', 'hide_entrytype_prompt' => false, 'note_prompt' => 'Event:', 
            'responseexpected_prompt' => null, 'hide_responseexpected_prompt' => true, 'hide_isResponsive' => true, 'amount_prompt' => null, 'hide_amount_prompt' => true, 
            'short_name' => 'events', 'created_at' => now(), 'updated_at' => now() ],

            [ 'name' => 'To-Do', 'input_time' => false, 'date1_prompt' => 'Date:', 'date2_prompt' => 'Completed:', 'hide_date2_prompt' => false, 
            'from_prompt' => 'For:', 'to_prompt' => null, 'hide_to_prompt' => true, 'entrytype_prompt' => 'To-Do Type:', 'hide_entrytype_prompt' => true, 'note_prompt' => 'To-Do:', 
            'responseexpected_prompt' => null, 'hide_responseexpected_prompt' => true, 'hide_isResponsive' => true, 'amount_prompt' => null, 'hide_amount_prompt' => true, 
            'short_name' => 'todo', 'created_at' => now(), 'updated_at' => now() ],

            [ 'name' => 'Phone Messages', 'input_time' => true, 'date1_prompt' => 'Call Time:', 'date2_prompt' => null, 'hide_date2_prompt' => true, 
            'from_prompt' => 'Call From:', 'to_prompt' => 'Call For:', 'hide_to_prompt' => false, 'entrytype_prompt' => 'Call Type:', 'hide_entrytype_prompt' => false, 'note_prompt' => 'Message:', 
            'responseexpected_prompt' => 'Response expected:', 'hide_responseexpected_prompt' => false, 'hide_isResponsive' => true, 'amount_prompt' => null, 'hide_amount_prompt' => true, 
            'short_name' => 'phone', 'created_at' => now(), 'updated_at' => now() ],

            [ 'name' => 'Medical Records', 'input_time' => false, 'date1_prompt' => 'Received:', 'date2_prompt' => 'Dated:', 'hide_date2_prompt' => false, 
            'from_prompt' => 'Provider:', 'to_prompt' => null, 'hide_to_prompt' => true, 'entrytype_prompt' => 'Record Type:', 'hide_entrytype_prompt' => false, 'note_prompt' => 'Note:', 
            'responseexpected_prompt' => null, 'hide_responseexpected_prompt' => true, 'hide_isResponsive' => false, 'amount_prompt' => null, 'hide_amount_prompt' => true, 
            'short_name' => 'medrecs', 'created_at' => now(), 'updated_at' => now() ],

            [ 'name' => 'Medical Bills', 'input_time' => false, 'date1_prompt' => 'Bill Date:', 'date2_prompt' => 'Service Date:', 'hide_date2_prompt' => false, 
            'from_prompt' => 'Provider:', 'to_prompt' => null, 'hide_to_prompt' => true, 'entrytype_prompt' => null, 'hide_entrytype_prompt' => true, 'note_prompt' => 'Note:', 
            'responseexpected_prompt' => null, 'hide_responseexpected_prompt' => true, 'hide_isResponsive' => true, 'amount_prompt' => 'Amount:', 'hide_amount_prompt' => false, 
            'short_name' => 'medbills', 'created_at' => now(), 'updated_at' => now() ],
            
            [ 'name' => 'Case Costs', 'input_time' => false, 'date1_prompt' => 'Date Paid:', 'date2_prompt' => null, 'hide_date2_prompt' => true, 
            'from_prompt' => 'Paid To:', 'to_prompt' => null, 'hide_to_prompt' => true, 'entrytype_prompt' => 'Expense Type:', 'hide_entrytype_prompt' => false, 'note_prompt' => 'Note:', 
            'responseexpected_prompt' => null, 'hide_responseexpected_prompt' => true, 'hide_isResponsive' => true, 'amount_prompt' => 'Amount:', 'hide_amount_prompt' => false, 
            'short_name' => 'costs', 'created_at' => now(), 'updated_at' => now() ],
            
        ]);
    }
}
