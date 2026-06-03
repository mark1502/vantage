<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Model;

class Preference extends Model
{
    use BelongsToFirm;

    protected $guarded = [];

    protected $hidden = ['firm_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
