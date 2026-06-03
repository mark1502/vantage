<?php

namespace App\Policies;

use App\Models\Filetype;
use App\Models\User;

class FiletypePolicy
{
    /**
     * Determine whether the user can view the filetype.
     */
    public function view(User $user, Filetype $filetype): bool
    {
        return $this->belongsToSameFirm($user, $filetype);
    }

    /**
     * Determine whether the user can update the filetype.
     */
    public function update(User $user, Filetype $filetype): bool
    {
        return $this->belongsToSameFirm($user, $filetype);
    }

    /**
     * Determine whether the user can delete the filetype.
     */
    public function delete(User $user, Filetype $filetype): bool
    {
        return $this->belongsToSameFirm($user, $filetype);
    }

    /**
     * A filetype may only be acted on by users belonging to the same firm.
     */
    protected function belongsToSameFirm(User $user, Filetype $filetype): bool
    {
        return $user->firm_id === $filetype->firm_id;
    }
}
