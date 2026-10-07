<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreContactRequest extends FormRequest
{
    /**
     * Firm scoping is done by the unique rule and firm_id stamping;
     * ContactController::update keeps its own policy authorization.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCompany = $this->input('title') === 'Co.';

        return [
            'title' => ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.', 'Co.'])],
            'company' => $isCompany ? 'required|max:255' : 'nullable|max:255',
            'first_name' => $isCompany ? 'nullable|max:255' : 'required|max:255',
            'last_name' => $isCompany ? 'nullable|max:255' : 'required|max:255',
            'middle_name' => 'nullable|max:255',
            'srjr' => 'nullable|max:255',
            'esqphd' => 'nullable|max:255',
            'business_title' => 'nullable|max:255',
            'address' => 'nullable|max:255',
            'email' => 'nullable|email|max:255',
            'email_alt' => 'nullable|email|max:255',
            'work_phone' => 'nullable|max:255',
            'cell_phone' => 'nullable|max:255',
            'home_phone' => 'nullable|max:255',
            'fax_phone' => 'nullable|max:255',
            'other_phone' => 'nullable|max:255',
            'note' => 'nullable|max:1000',
            'display_name' => ['required', 'max:255', $this->displayNameUniqueRule()],
            'display_last_first' => 'max:255',
        ];
    }

    protected function displayNameUniqueRule(): Unique
    {
        return Rule::unique('contacts')->where('firm_id', $this->user()->firm_id);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.unique' => 'The names in your contact list must be unique, and the name '.$this->input('display_name').' is already in your contact list.  To distinguish this contact, try using a middle initial or appending a number in parentheses to the last name.',
        ];
    }
}
