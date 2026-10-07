<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateContactRequest extends StoreContactRequest
{
    protected function displayNameUniqueRule(): Unique
    {
        return Rule::unique('contacts')
            ->where('firm_id', $this->user()->firm_id)
            ->ignore($this->route('contact'));
    }
}
