<?php

namespace App\Http\Controllers;

use App\Models\ContactRole;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactRoleController extends Controller
{
    /**
     * Get contact IDs that have roles for a given file.
     */
    public function getContactRoleIds(Request $request, File $file)
    {
        if ($file->firm_id !== $request->user()->firm_id) {
            abort(403, 'Unauthorized');
        }

        return response()->json(
            ContactRole::where('file_id', $file->id)->pluck('contact_id')->unique()->values()->toArray()
        );
    }

    /**
     * Store a newly created contact role for a file.
     */
    public function store(Request $request)
    {
        $firmId = $request->user()->firm_id;

        $validated = $request->validate([
            'file_id' => ['required', 'integer', Rule::exists('files', 'id')->where('firm_id', $firmId)],
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('firm_id', $firmId)],
            'role' => 'required|string|in:'.implode(',', array_keys(ContactRole::ROLE_LABELS)),
            'role_label' => 'nullable|string|max:255',
        ], [
            'contact_id.exists' => 'You can only select contacts from your firm.',
        ]);

        ContactRole::firstOrCreate(
            [
                'file_id' => $validated['file_id'],
                'contact_id' => $validated['contact_id'],
                'role' => $validated['role'],
            ],
            [
                'role_label' => $validated['role_label'] ?? ContactRole::ROLE_LABELS[$validated['role']] ?? $validated['role'],
            ]
        );

        return back()->with('success', 'Contact role added successfully.');
    }

    /**
     * Update the specified contact role.
     */
    public function update(Request $request, ContactRole $contactRole)
    {
        $file = File::findOrFail($contactRole->file_id);

        // Ensure user has access to this file
        if ($file->firm_id !== $request->user()->firm_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'role' => 'required|string|in:'.implode(',', array_keys(ContactRole::ROLE_LABELS)),
            'role_label' => 'nullable|string|max:255',
        ]);

        $contactRole->update($validated);

        return back()->with('success', 'Contact role updated successfully.');
    }

    /**
     * Remove the specified contact role from storage.
     */
    public function destroy(ContactRole $contactRole, Request $request)
    {
        $file = File::findOrFail($contactRole->file_id);

        // Ensure user has access to this file
        if ($file->firm_id !== $request->user()->firm_id) {
            abort(403, 'Unauthorized');
        }

        $contactRole->delete();

        return back()->with('success', 'Contact removed from file.');
    }
}
