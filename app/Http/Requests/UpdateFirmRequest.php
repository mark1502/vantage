<?php

namespace App\Http\Requests;

use App\Models\Firm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $firmId = $this->user()->firm_id;

        return [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => ['required', 'email', 'max:255', Rule::unique('firms', 'email')->ignore($firmId)],
            'document_base_path' => ['nullable', 'string', 'max:500', function ($attribute, $value, $fail) {
                if (empty($value)) {
                    return;
                }

                if (! is_dir($value)) {
                    $fail('The document storage path does not exist or is not accessible by the server.');

                    return;
                }

                if (Firm::safeDocumentBasePath($value) === null) {
                    $fail('The document storage path is not an allowed location. It must be a configured firm document directory and cannot point inside the application server.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Firm name is required.',
            'email.required' => 'Firm email is required.',
            'email.unique' => 'This email is already in use by another firm.',
        ];
    }
}
