<?php

namespace App\Http\Controllers;

use App\Models\ContactRole;
use App\Models\File;
use App\Models\Contact;
use App\Models\Role;
use Illuminate\Http\Request;

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
            ContactRole::where('file_id', $file->id)->pluck('contact_id')->toArray()
        );
    }

    /**
     * Store a newly created contact role for a file.
     */
    public function store(Request $request)
    {
        $file = File::findOrFail($request->file_id);

        // Ensure user has access to this file
        if ($file->firm_id !== $request->user()->firm_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'file_id' => 'required|integer',
            'contact_id' => 'required|integer',
            'role_id' => 'nullable|integer',
            'is_client' => 'boolean',
            'is_attorney' => 'boolean',
        ]);

        // If role_id is provided, ensure it belongs to the user's firm
        if ($request->role_id) {
            $role = Role::find($request->role_id);
            if ($role->firm_id !== $request->user()->firm_id) {
                return back()->withErrors(['role_id' => 'You can only select roles from your firm.']);
            }
        }

        // Ensure contact belongs to the user's firm
        $contact = Contact::find($request->contact_id);
        if ($contact->firm_id !== $request->user()->firm_id) {
            return back()->withErrors(['contact_id' => 'You can only select contacts from your firm.']);
        }

        try {
            ContactRole::create($validated);

            return back()->with('success', 'Contact role added successfully.');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'A file can only have one client')) {
                return back()->withErrors(['is_client' => 'This file already has a client assigned.']);
            }
            throw $e;
        }
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
            'role_id' => 'nullable|integer|exists:roles,id',
            'is_client' => 'boolean',
            'is_attorney' => 'boolean',
        ]);

        // If role_id is provided, ensure it belongs to the user's firm
        if ($request->role_id) {
            $role = Role::find($request->role_id);
            if ($role->firm_id !== $request->user()->firm_id) {
                return back()->withErrors(['role_id' => 'You can only select roles from your firm.']);
            }
        }

        try {
            $contactRole->update($validated);

            return back()->with('success', 'Contact role updated successfully.');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'A file can only have one client')) {
                return back()->withErrors(['is_client' => 'This file already has a client assigned.']);
            }
            throw $e;
        }
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
