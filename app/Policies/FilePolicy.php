<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;

class FilePolicy
{
    /**
     * Determine whether the user can view the file.
     */
    public function view(User $user, File $file): bool
    {
        return $this->belongsToSameFirm($user, $file);
    }

    /**
     * Determine whether the user can update the file.
     */
    public function update(User $user, File $file): bool
    {
        return $this->belongsToSameFirm($user, $file);
    }

    /**
     * Determine whether the user can delete the file.
     */
    public function delete(User $user, File $file): bool
    {
        return $this->belongsToSameFirm($user, $file);
    }

    /**
     * A file may only be acted on by users belonging to the same firm.
     */
    protected function belongsToSameFirm(User $user, File $file): bool
    {
        return $user->firm_id === $file->firm_id;
    }
}
