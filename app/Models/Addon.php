<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Addon extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function firms(): BelongsToMany
    {
        return $this->belongsToMany(Firm::class)
            ->withPivot('activated_at', 'expires_at')
            ->withTimestamps();
    }
}
