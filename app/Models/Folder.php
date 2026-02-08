<?php

namespace App\Models;

use App\Models\Entrytype;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Folder extends Model
{
    use HasFactory;

    protected $guarded = [];
    
    public function entrytypes() {
        return $this->hasMany(Entrytype::class);
    }

}
