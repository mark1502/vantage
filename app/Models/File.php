<?php

namespace App\Models;

use App\Models\Firm;
use App\Models\Entry;
use App\Models\Contact;
use App\Models\Filetype;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class File extends Model
{
    use HasFactory;

    // protected $dates = ['deleted_at'];  No more softDeletes

    protected $guarded = [];

    protected $hidden = ['firm_id'];

    public function firm() {
        return $this->belongsTo( Firm::class );
    }
    
    public function contact() {
        return $this->belongsTo( Contact::class );
    }

    public function contacts() {
        return $this->hasMany( Contact::class );
    }

    public function filetype() {
        return $this->belongsTo( Filetype::class );
    }

    public function entries() {
        return $this->hasMany( Entry::class );
    }
}
