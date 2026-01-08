<?php

namespace App\Models;

use App\Models\User;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;

class Firm extends Model
{
    public function users() {
       return $this->hasMany(User::class);
    }

    public function contacts() {
        return $this->hasMany(Contact::class);        
    }



}
