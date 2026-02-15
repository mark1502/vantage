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

    public function contactRoles()
    {
        return $this->hasMany(ContactRole::class);
    }

    // Helper to get the client contact role
    public function client()
    {
        return $this->hasOne(ContactRole::class)->where('is_client', true);
    }

    // Helper to get the assigned attorney contact role
    public function assignedAttorney()
    {
        return $this->hasOne(ContactRole::class)
            ->where('is_attorney', true)
            ->where('is_client', true);
    }

    // Helper to get opposing counsel contact role
    public function opposingCounsel()
    {
        return $this->hasOne(ContactRole::class)
            ->where('is_attorney', true)
            ->where('is_client', false);
    }

    // Many-to-many relationship with pivot data
    public function contactsWithRoles()
    {
        return $this->belongsToMany(Contact::class, 'contact_roles')
            ->withPivot(['role_id', 'is_client', 'is_attorney'])
            ->withTimestamps();
    }
}
