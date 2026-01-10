<?php

namespace App\Models;

use App\Models\Entry;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Model;

class Entrytype extends Model
{
    use HasFactory;

    public function entries() {
        return $this->hasMany(Entry::class);
    }

    public function folder() {
        return $this->belongsTo(Folder::class);
    }

}
