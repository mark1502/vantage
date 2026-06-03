<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Filetype extends Model
{
    use BelongsToFirm, HasFactory;

    protected $hidden = ['firm_id'];

    public function files()
    {
        return $this->hasMany(File::class);
    }
}
