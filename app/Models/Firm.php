<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Firm extends Model
{
    use Billable, HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class)
            ->withPivot('activated_at', 'expires_at')
            ->withTimestamps();
    }

    public function hasDocumentBasePath(): bool
    {
        return ! empty($this->document_base_path);
    }

    public function syncSubscriptionQuantity(): void
    {
        if (! $this->subscribed('default')) {
            return;
        }

        $seatCount = Contact::where('firm_id', $this->id)
            ->firmMembers()
            ->current()
            ->count();

        $this->subscription('default')->updateQuantity($seatCount);
    }

    public function fileCount(): int
    {
        return File::where('firm_id', $this->id)->count();
    }

    public function fileLimit(): ?int
    {
        return $this->subscribed('default') ? null : 10;
    }

    public function canCreateFiles(): bool
    {
        $limit = $this->fileLimit();

        return $limit === null || $this->fileCount() < $limit;
    }
}
