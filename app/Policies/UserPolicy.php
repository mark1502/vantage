<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the target user.
     */
    public function view(User $user, User $target): bool
    {
        return $this->isFirmAdminOf($user, $target);
    }

    /**
     * Determine whether the user can update the target user.
     */
    public function update(User $user, User $target): bool
    {
        return $this->isFirmAdminOf($user, $target);
    }

    /**
     * Determine whether the user can delete the target user.
     */
    public function delete(User $user, User $target): bool
    {
        return $this->isFirmAdminOf($user, $target);
    }

    /**
     * User management is restricted to administrators acting within their own firm.
     */
    protected function isFirmAdminOf(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->firm_id === $target->firm_id;
    }
}
