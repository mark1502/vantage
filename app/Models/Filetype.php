<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filetype extends Model
{
    use BelongsToFirm, HasFactory;

    protected $hidden = ['firm_id'];

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /**
     * Whether any file (open or closed, in any firm) references this filetype.
     */
    public function isInUse(): bool
    {
        return File::withoutGlobalScope('firm')
            ->where('filetype_id', $this->id)
            ->exists();
    }
}
