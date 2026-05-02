<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreViewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;  // changed to true to disable for now
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['formtype' => 'string|max:20|nullable',
            'file_id' => 'required|integer',
            'folder_id' => 'numeric|integer|required',
            'input_time' => 'boolean',
            'hide_date2_prompt' => 'boolean',
            'entrytype_id' => 'numeric|integer|nullable',
            'from_contact_id' => 'numeric|integer|required',
            'to_contact_id' => 'numeric|integer|nullable',
            'note' => 'string|max:5000|nullable',
            'date1' => 'date_format:Y-m-d H:i:s|required',
            'date2' => 'date_format:Y-m-d H:i:s|nullable',
            'all_day' => 'boolean|nullable',
            'date_response_expected' => 'date_format:Y-m-d H:i:s|nullable',
            'was_a_response' => 'in:N,P,F',
            'was_response_to' => 'integer|numeric|nullable',
            'is_a_response' => 'in:N,P,F',
            'is_response_to' => 'integer|numeric|nullable',
            'amount' => 'numeric|nullable',
            'current_page' => 'numeric|integer|nullable',
            'show' => 'numeric|integer|nullable',
            'filepart' => 'string|max:20|nullable',
            'comeback' => 'boolean',
            'view' => 'string|max:20|nullable',
            'view_for' => 'string|max:20|required',
            'viewpage' => 'numeric|integer|required',
            'viewshow' => 'numeric|integer|nullable',
            'read' => 'string|max:20|required',
            'from_to' => 'string|max:20|required',
            'new_entrytype_added' => 'boolean',
            'new_contact_added' => 'boolean',
            'pending_contact_roles' => 'array|nullable',
            'pending_contact_roles.*.contact_id' => 'required|integer',
            'pending_contact_roles.*.role' => 'required|string',
            'pending_contact_roles.*.role_label' => 'nullable|string|max:255',
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
