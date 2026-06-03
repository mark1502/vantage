<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrytype extends Model
{
    use BelongsToFirm, HasFactory;

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
