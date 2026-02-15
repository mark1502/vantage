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

    // Ensure only one client per file
    protected static function booted()
    {
        // static::saving(function ($contactRole) {
        //     if ($contactRole->is_client) {
        //         // Check if another contact is already the client for this file
        //         $existingClient = static::where('file_id', $contactRole->file_id)
        //             ->where('is_client', true)
        //             ->where('is_attorney', false)      // added so client can be added after attorney, because our atty is_client && is_attorney (atty for client)
        //             ->where('id', '!=', $contactRole->id)
        //             ->exists();

        //         if ($existingClient) {
        //             throw new \Exception('A file can only have one client.');
        //         }
        //     }
        // });
    }
}
