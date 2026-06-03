<?php

namespace App\Policies;

use App\Models\Entry;
use App\Models\User;

class EntryPolicy
{
    /**
     * Determine whether the user can view the entry.
     */
    public function view(User $user, Entry $entry): bool
    {
        return $this->belongsToSameFirm($user, $entry);
    }

    /**
     * Determine whether the user can update the entry.
     */
    public function update(User $user, Entry $entry): bool
    {
        return $this->belongsToSameFirm($user, $entry);
    }

    /**
     * Determine whether the user can delete the entry.
     */
    public function delete(User $user, Entry $entry): bool
    {
        return $this->belongsToSameFirm($user, $entry);
    }

    /**
     * An entry may only be acted on by users belonging to the same firm.
     */
    protected function belongsToSameFirm(User $user, Entry $entry): bool
    {
        return $user->firm_id === $entry->firm_id;
    }
}
