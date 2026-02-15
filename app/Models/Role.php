<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['firm_id'];

    public function firm()
    {
        return $this->belongsTo(Firm::class);
    }

    public function contactRoles()
    {
        return $this->hasMany(ContactRole::class);
    }
}
