<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    /**
     * Determine whether the user can view the contact.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $this->belongsToSameFirm($user, $contact);
    }

    /**
     * Determine whether the user can update the contact.
     */
    public function update(User $user, Contact $contact): bool
    {
        return $this->belongsToSameFirm($user, $contact);
    }

    /**
     * Determine whether the user can delete the contact.
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $this->belongsToSameFirm($user, $contact);
    }

    /**
     * A contact may only be acted on by users belonging to the same firm.
     */
    protected function belongsToSameFirm(User $user, Contact $contact): bool
    {
        return $user->firm_id === $contact->firm_id;
    }
}
