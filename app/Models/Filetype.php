<?php

namespace App\Models;

use App\Models\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Filetype extends Model
{
    use HasFactory;

    protected $hidden = ['firm_id'];

    public function files()
    {
        return $this->hasMany(File::class);
    }

}
