<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $show = min((int) $request->query('show', 10) ?: 10, 50);
        $filter = $request->query('filter', 'current');

        $contacts = Contact::query()
            ->select('id', 'title', 'last_name', 'first_name', 'middle_name', 'srjr', 'esqphd',
                'company', 'business_title', 'display_name', 'display_last_first', 'address',
                'email', 'email_alt', 'home_phone', 'work_phone', 'cell_phone', 'fax_phone',
                'other_phone', 'note', 'faux_deleted')
            ->with('files:id,name')
            ->where('firm_id', $request->user()->firm_id)
            ->where('is_firm_member', '=', false)
            ->when($filter === 'current', fn ($query) => $query->where('faux_deleted', false))
            ->when($filter === 'deleted', fn ($query) => $query->where('faux_deleted', true))
            ->when($request->query('search'), fn ($query, $search) => $query->where('display_last_first', 'like', $search.'%')
            )
            ->orderBy('display_last_first')
            ->paginate($show)->onEachSide(2)
            ->withQueryString();

        return Inertia::render('Contacts/Index', compact('contacts', 'filter'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return inertia::render('Contacts/Create');
    }

    public function store(Request $request)
    {
        $checkvals = [];
        $checkvals['title'] = ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.', 'Co.'])];

        if ($request->title === 'Co.') {
            $checkvals['company'] = 'required|max:255';
            $checkvals['first_name'] = 'nullable|max:255';
            $checkvals['last_name'] = 'nullable|max:255';
        } else {
            $checkvals['company'] = 'nullable|max:255';
            $checkvals['first_name'] = 'required|max:255';
            $checkvals['last_name'] = 'required|max:255';
        }

        $checkvals['middle_name'] = 'nullable|max:255';
        $checkvals['srjr'] = 'nullable|max:255';
        $checkvals['esqphd'] = 'nullable|max:255';
        $checkvals['business_title'] = 'nullable|max:255';
        $checkvals['address'] = 'nullable|max:255';
        $checkvals['email'] = 'nullable|email|max:255';
        $checkvals['email_alt'] = 'nullable|email|max:255';
        $checkvals['work_phone'] = 'nullable|max:255';
        $checkvals['cell_phone'] = 'nullable|max:255';
        $checkvals['home_phone'] = 'nullable|max:255';
        $checkvals['fax_phone'] = 'nullable|max:255';
        $checkvals['other_phone'] = 'nullable|max:255';
        $checkvals['note'] = 'nullable|max:1000';
        $checkvals['display_name'] = ['max:255', Rule::unique('contacts')->where('firm_id', $request->user()->firm_id)];
        $checkvals['display_last_first'] = 'max:255';

        $validatedVals = $request->validate($checkvals, [
            'display_name' => 'Each name in your contact list must be unique, and this name is already in your list.  To distinguish this contact, try using a middle initial or appending a number in parentheses to the last name.',
        ]);

        $validatedVals['firm_id'] = $request->user()->firm_id;      // add the firm_id to the validated values

        $contact = Contact::create($validatedVals);                 // create the contact with the validated values

        return redirect(route('contacts.index', ['page' => $request->current_page, 'show' => $request->show]));
    }

    public function show(Contact $contact)
    {
        //
    }

    public function edit(Contact $contact)
    {
        $this->authorize('view', $contact);

        return Inertia::render('Contacts/Edit', [
            'contact' => [
                'id' => $contact->id,
                'title' => $contact->title,
                'last_name' => $contact->last_name,
                'first_name' => $contact->first_name,
                'middle_name' => $contact->middle_name,
                'srjr' => $contact->srjr,
                'esqphd' => $contact->esqphd,
                'company' => $contact->company,
                'business_title' => $contact->business_title,
                'address' => $contact->address,
                'email' => $contact->email,
                'email_alt' => $contact->email_alt,
                'home_phone' => $contact->home_phone,
                'work_phone' => $contact->work_phone,
                'cell_phone' => $contact->cell_phone,
                'fax_phone' => $contact->fax_phone,
                'other_phone' => $contact->other_phone,
                'primary' => $contact->primary,
                'secondary' => $contact->secondary,
                'note' => $contact->note,
                'display_name' => $contact->display_name,
                'display_last_first' => $contact->display_last_first,
                'faux_deleted' => $contact->faux_deleted,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Contact $contact)
    {
        $this->authorize('update', $contact);

        $checkvals = [];
        $checkvals['title'] = ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.', 'Co.'])];

        if ($request->title === 'Co.') {
            $checkvals['company'] = 'required|max:255';
            $checkvals['first_name'] = 'nullable|max:255';
            $checkvals['last_name'] = 'nullable|max:255';
        } else {
            $checkvals['company'] = 'nullable|max:255';
            $checkvals['first_name'] = 'required|max:255';
            $checkvals['last_name'] = 'required|max:255';
        }

        $checkvals['middle_name'] = 'nullable|max:255';
        $checkvals['srjr'] = 'nullable|max:255';
        $checkvals['esqphd'] = 'nullable|max:255';
        $checkvals['business_title'] = 'nullable|max:255';
        $checkvals['address'] = 'nullable|max:255';
        $checkvals['email'] = 'nullable|email|max:255';
        $checkvals['email_alt'] = 'nullable|email|max:255';
        $checkvals['work_phone'] = 'nullable|max:255';
        $checkvals['cell_phone'] = 'nullable|max:255';
        $checkvals['home_phone'] = 'nullable|max:255';
        $checkvals['fax_phone'] = 'nullable|max:255';
        $checkvals['other_phone'] = 'nullable|max:255';
        $checkvals['note'] = 'nullable|max:1000';
        if ($request->display_name !== $contact->display_name) {    // if the name has changed, confirm it is still unique in the table for this firm
            $checkvals['display_name'] = ['max:255', Rule::unique('contacts')->where('firm_id', $request->user()->firm_id)];
        } else {
            $checkvals['display_name'] = 'max:255';
        }
        $checkvals['display_last_first'] = 'max:255';

        $validatedVals = $request->validate($checkvals, [
            'display_name' => 'Each name in your contact list must be unique, and this name is already in your list.  To distinguish this contact, try using a middle initial or appending a number in parentheses to the last name.',
        ]);

        $contact->update($validatedVals);  // update the contact with the validated values

        return redirect(route('contacts.index', ['page' => $request->current_page, 'show' => $request->show]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Contact $contact)
    {
        $this->authorize('delete', $contact);

        $contact->update(['faux_deleted' => true]);

        return redirect(route('contacts.index', ['page' => $request->page, 'show' => $request->show, 'filter' => $request->filter]))
            ->with('success', 'Contact deleted successfully.');
    }

    public function restore(Request $request, Contact $contact)
    {
        $this->authorize('update', $contact);

        $contact->update(['faux_deleted' => false]);

        return redirect(route('contacts.index', [
            'page' => $request->page,
            'show' => $request->show,
            'filter' => $request->filter,
        ]))->with('success', 'Contact restored successfully.');
    }
} // end class
