<?php

namespace App\Models;

use App\Models\File;
use App\Models\Folder;
use App\Models\Contact;
use App\Models\Response;
use App\Models\Entrytype;
use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    public function folder() { 
        return $this->belongsTo( Folder::class );  
    }

    public function casefile() { 
        return $this->belongsTo( File::class );
    }

    public function entrytype() { 
        return $this->belongsTo( Entrytype::class );  
    }

    public function contact_from() { 
        return $this->belongsTo( Contact::class, 'from_contact_id' );  
    }

    public function contact_to() { 
        return $this->belongsTo( Contact::class, 'to_contact_id' );  
    }

    public function response() { 
        return $this->hasOne( Response::class ); 
    }
    
    // public function response_to() { return $this->hasOne(Entry::class );}  ?? probably not needed

    public function responses_received() { 
        return $this->hasMany( Response::class, 'response_to' ); 
    }
}
