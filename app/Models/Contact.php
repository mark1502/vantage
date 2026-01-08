<?php

namespace App\Models;

use App\Models\Firm;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['firm_id'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function firm() {
        return $this->belongsTo(Firm::class);
    }
}
