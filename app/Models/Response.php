<?php

namespace App\Models;

use App\Models\Entry;
use Illuminate\Database\Eloquent\Model;

class Response extends Model
{
    public $timestamps = false;
    
    public function entry() {
        return $this->belongsTo(Entry::class);
    }

    public function response_to() {
        return $this->belongsTo(Entry::class, 'response_to')
            ->select('id','date1','from_contact_id', 'folder_id', 'entrytype_id', 'expecting_response')
            ->with('contact_from:id,display_last_first');
    }

}
