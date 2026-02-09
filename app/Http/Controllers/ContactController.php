<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Contact;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;



class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $show = $request->show ?? 10;

        $contacts = Contact::query()                // main query for contacts
        ->select('id', 'title', 'last_name', 'first_name', 'middle_name', 'srjr', 'esqphd', 'company', 'business_title', 
                'display_name', 'display_last_first', 'address', 'email', 'email_alt',
                'home_phone', 'work_phone', 'cell_phone', 'fax_phone', 'other_phone', 'note', 'display_name', 'display_last_first')
        ->when($request->query('search'), function($query, $search) {       // if there is a search query, filter by display_last_first
            $query->where('display_last_first', 'like', $search . '%');
        })
        ->where('firm_id', $request->user()->firm_id)
        ->where('is_firm_member', '=', false)
        ->orderBy('display_last_first')
        ->paginate($show)->onEachSide(2)
        ->withQueryString();

        $contactIds = $contacts->pluck('id');                               // collect the IDs of the contacts for later use 

        $files = File::select(                                      // query for files - where the contact is either 'from' or 'to' in an entry
            'files.id',
            'files.name',
            'entries.from_contact_id',
            'entries.to_contact_id'
        )
        ->distinct()
        ->join('entries', 'entries.file_id', '=', 'files.id')
        ->where(function ($query) use ($contactIds) {
            $query->whereIn('entries.from_contact_id', $contactIds)
                ->orWhereIn('entries.to_contact_id', $contactIds);
        })
        ->get();

        $filesByContact = collect();                                    // initialize a collection to hold files by contact

        foreach ($files as $file) {                                 // loop through each file               
            if ($file->from_contact_id) {                               // if the file has a 'from_contact_id', add it to the collection
                $filesByContact->push([
                    'contact_id' => $file->from_contact_id,
                    'file' => $file,
                ]);
            }
            if ($file->to_contact_id) {                                 // if the file has a 'to_contact_id', add it to the collection
                $filesByContact->push([
                    'contact_id' => $file->to_contact_id,
                    'file' => $file,
                ]);
            }
        }
            
        $contacts->transform(function ($contact) use ($filesByContact) {    // transform each contact to include its files
            $contact->files = $filesByContact
                ->where('contact_id', $contact->id)
                ->pluck('file')
                ->unique('id')                  // prevent duplicates
                ->sortBy('name')                // sort alphabetically by name
                ->map(function ($cf) {          // map to a simpler array structure, just keeping id and name
                    return [
                        'id' => $cf->id,
                        'name' => $cf->name,
                    ];
                })
                ->values();                     // reindex keys for clean array

            return $contact;
        });
    
        // dd($contacts);

        return Inertia::render('Contacts/Index', compact(['contacts']) );
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
    {   $checkvals = [];
        $checkvals['title'] = ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.', 'Co.'])];

        if( $request->title === 'Co.' ) {
            $checkvals['company'] = 'required|max:255';
            $checkvals['first_name'] = 'nullable|max:255';
            $checkvals['last_name'] = 'nullable|max:255';
        }
        else {
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
        $checkvals['display_name'] = ['max:255', Rule::unique('contacts')->where('firm_id', $request->user()->firm_id) ];
        $checkvals['display_last_first'] = 'max:255';

        $validatedVals = $request->validate( $checkvals, [
            'display_name' => 'Each name in your contact list must be unique, and this name is already in your list.  To distinguish this contact, try using a middle initial or appending a number in parentheses to the last name.',
        ]);

        $validatedVals['firm_id'] = $request->user()->firm_id;      // add the firm_id to the validated values

        $contact = Contact::create($validatedVals);                 // create the contact with the validated values

        return redirect( route( 'contacts.index', [ 'page' => $request->current_page, 'show' => $request->show ] ) );
    }


    public function show(Contact $contact)
    {
        //
    }


    public function edit(Contact $contact)
    {   return Inertia::render('Contacts/Edit', [               //only pass these values to the front end
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
            ],
        ]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Contact  $contact
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Contact $contact)
    {   $checkvals = [];
        $checkvals['title'] = ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.', 'Co.'])];

        if( $request->title === 'Co.' ) {
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
        if( $request->display_name != $contact->display_name ) {    // if the name has changed, confirm it is still unique in the table for this firm
            $checkvals['display_name'] = ['max:255', Rule::unique('contacts')->where('firm_id', $request->user()->firm_id) ];
        } else {
            $checkvals['display_name'] = 'max:255';
        }        
        $checkvals['display_last_first'] = 'max:255';

        $validatedVals = $request->validate( $checkvals, [
            'display_name' => 'Each name in your contact list must be unique, and this name is already in your list.  To distinguish this contact, try using a middle initial or appending a number in parentheses to the last name.',
        ]);

        $contact->update($validatedVals);  // update the contact with the validated values

        return redirect( route( 'contacts.index', ['page' => $request->current_page, 'show' => $request->show]) );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Contact  $contact
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Contact $contact)
    {
        // dd($contact);
        $contact->delete();
        return redirect( route( 'contacts.index', ['page' => $request->page, 'show' => $request->show]))
            ->with('success', 'Contact deleted successfully.');

    }

/*
    public function make_display_name($request) {   // NOT USED 
        if($request->title === 'Co.') {
            $nameback = $request->company;
        }
        else {
            $nameback = trim($request->first_name);
            $nameback .= $request->middle_name ? ' ' . trim($request->middle_name) : '';
            $nameback .= ' ' . trim($request->last_name);
            $nameback .= $request->srjr ? ', ' . trim($request->srjr) : '';
            $nameback .= $request->esqphd ? ', ' . trim($request->esqphd) : '';    
        }

        return $nameback;
    }


    public function make_display_last_first($request) {   // NOT USED
        if($request->title === 'Co.') {
            $nameback = $request->company;
        }
        else {
            $nameback = trim($request->last_name) . ', ' . trim($request->first_name);
            $nameback .= $request->middle_name ? ' ' . trim($request->middle_name) : '';
            $nameback .= $request->srjr ? ', ' . trim($request->srjr) : '';
            $nameback .= $request->esqphd ? ', ' . trim($request->esqphd) : '';    
        }

        return $nameback;
    }
*/
    
    public function test() {
        dd('got here');
    }

} // end class
