<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;  // set to true to disable authorization
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['formtype' => 'string|max:20|nullable',
            'file_id' => 'numeric|integer|nullable',
            'folder_id' => 'numeric|integer|required',
            'input_time' => 'boolean|nullable',
            'hide_date2_prompt' => 'boolean|nullable',
            'entrytype_id' => 'numeric|integer|required',
            'from_contact_id' => 'numeric|integer|required',
            'to_contact_id' => 'numeric|integer|nullable',
            'note' => 'string|max:5000|nullable',
            'date1' => 'date_format:Y-m-d H:i:s|required',
            'date2' => 'date_format:Y-m-d H:i:s|nullable',
            'all_day' => 'boolean|nullable',
            'date_response_expected' => 'date_format:Y-m-d H:i:s|nullable',
            'was_a_response' => 'in:N,P,F|nullable',
            'was_response_to' => 'integer|numeric|nullable',
            'is_a_response' => 'in:N,P,F',
            'is_response_to' => 'integer|numeric|nullable',
            'amount' => 'numeric|nullable',
            'pending_contact_roles' => 'array|nullable',
            'pending_contact_roles.*.contact_id' => 'required|integer',
            'pending_contact_roles.*.role' => 'required|string',
            'pending_contact_roles.*.role_label' => 'nullable|string|max:255',
            'linked_document_path' => ['nullable', 'string', 'max:500', 'not_regex:/\.\./'],
        ];
    }

    public function messages(): array
    {
        return ['file_id' => 'Invalid File ID',
            'folder_id' => 'Invalid Folder ID',
            'entrytype_id' => 'Entry Type Not Found',
            'date1' => 'Invalid Date',
            'from_contact_id' => 'Contact Not Found',
            'to_contact_id' => 'Invalid contact identification.',
            'is_a_response' => 'Invalid Entry Response',
        ];
    }
}
