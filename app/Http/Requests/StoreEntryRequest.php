<?php

namespace App\Http\Requests;

use App\Models\ContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Used by both `store` (file route binding) and `update` (entry route
     * binding); the caller must belong to the firm that owns the bound record.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($entry = $this->route('entry')) {
            return $user->can('update', $entry);
        }

        if ($file = $this->route('file')) {
            // The shared reserved (non-file-specific) file is writable by any firm;
            // the new entry is stamped with the caller's firm_id, so it stays isolated.
            if ((int) (is_object($file) ? $file->id : $file) === (int) config('documents.reserved_file_id')) {
                return true;
            }

            return $user->can('update', $file);
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $firmId = $this->user()->firm_id;

        $firmContact = Rule::exists('contacts', 'id')->where('firm_id', $firmId);
        $firmEntrytype = Rule::exists('entrytypes', 'id')->where('firm_id', $firmId);
        $firmEntry = Rule::exists('entries', 'id')->where('firm_id', $firmId);

        return ['formtype' => 'string|max:20|nullable',
            'file_id' => 'numeric|integer|nullable',
            'folder_id' => ['numeric', 'integer', 'required', Rule::exists('folders', 'id')],
            'input_time' => 'boolean|nullable',
            'hide_date2_prompt' => 'boolean|nullable',
            'entrytype_id' => ['numeric', 'integer', 'required', $firmEntrytype],
            'from_contact_id' => ['numeric', 'integer', 'required', $firmContact],
            'to_contact_id' => ['numeric', 'integer', 'nullable', $firmContact],
            'note' => 'string|max:5000|nullable',
            'date1' => 'date_format:Y-m-d H:i:s|required',
            'date2' => 'date_format:Y-m-d H:i:s|nullable',
            'all_day' => 'boolean|nullable',
            'date_response_expected' => 'date_format:Y-m-d H:i:s|nullable',
            'was_a_response' => 'in:N,P,F|nullable',
            'was_response_to' => ['integer', 'numeric', 'nullable', $firmEntry],
            'is_a_response' => 'in:N,P,F',
            'is_response_to' => ['integer', 'numeric', 'nullable', $firmEntry],
            'amount' => 'numeric|nullable',
            'pending_contact_roles' => 'array|nullable',
            'pending_contact_roles.*.contact_id' => ['required', 'integer', $firmContact],
            'pending_contact_roles.*.role' => ['required', 'string', 'max:50', Rule::in(array_keys(ContactRole::ROLE_LABELS))],
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
