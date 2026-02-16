<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactRole extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_client' => 'boolean',
        'is_attorney' => 'boolean',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

}
