<?php

namespace App\Policies;

use App\Models\Entrytype;
use App\Models\User;

class EntrytypePolicy
{
    /**
     * Determine whether the user can update the entry type.
     */
    public function update(User $user, Entrytype $entrytype): bool
    {
        return $this->isFirmAdminOf($user, $entrytype);
    }

    /**
     * Determine whether the user can delete the entry type.
     */
    public function delete(User $user, Entrytype $entrytype): bool
    {
        return $this->isFirmAdminOf($user, $entrytype);
    }

    /**
     * Editing and deleting entry types is an administrator function, scoped to the
     * administrator's own firm. (Creating entry types is open to all users.)
     */
    protected function isFirmAdminOf(User $user, Entrytype $entrytype): bool
    {
        return $user->isAdmin() && $user->firm_id === $entrytype->firm_id;
    }
}
