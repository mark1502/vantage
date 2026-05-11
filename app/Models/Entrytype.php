<?php

namespace App\Models;

use App\Models\Entry;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Entrytype extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'faux_deleted' => 'boolean',
        ];
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }
}
