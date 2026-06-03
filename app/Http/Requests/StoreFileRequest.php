<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $firmId = $this->user()->firm_id;

        return [
            'name' => 'required|max:255',
            'summary' => 'nullable|max:5000',
            'date_sol' => 'nullable|date_format:Y-m-d',
            'date_opened' => 'nullable|date_format:Y-m-d',
            'date_filed' => 'nullable|date_format:Y-m-d',
            'date_closed' => 'nullable|date_format:Y-m-d',
            'date_archived' => 'nullable|date_format:Y-m-d',
            'court_filed' => 'nullable|max:255',
            'docket_number' => 'nullable|max:255',
            'file_number' => 'nullable|max:255',
            'referred_by' => 'nullable|max:255',
            'referral_amount' => 'nullable|max:255',
            'fee_arrangement' => 'nullable|max:255',
            'fee_amount' => 'nullable|max:255',
            'final_disposition' => 'nullable|max:255',
            'filetype_id' => ['nullable', 'integer', Rule::exists('filetypes', 'id')->where('firm_id', $firmId)],
            'attorney_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('firm_id', $firmId)],
            'client_contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('firm_id', $firmId)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'File name is required.',
            'attorney_id.required' => 'Assigned attorney is required.',
            'attorney_id.exists' => 'The selected attorney is not valid for your firm.',
            'client_contact_id.required' => 'Client is required.',
            'client_contact_id.exists' => 'The selected client is not valid for your firm.',
            'filetype_id.exists' => 'The selected file type is not valid for your firm.',
        ];
    }
}
