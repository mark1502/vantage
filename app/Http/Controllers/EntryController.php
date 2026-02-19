<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Entry;
use App\Models\Folder;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\File;
use App\Models\Response;
use App\Models\Entrytype;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreEntryRequest;
use App\Models\Filetype;

class EntryController extends Controller
{
    public function index(Request $request, $file_id)
    {        
        $file = File::where('id',$file_id)                              // get the file record
                    ->with('Filetype')->first();                                // with the filetype

        $refresh = $request->header('X-Custom-Refresh') ?? 'full';                  // if refresh flag is set in the header, otherwise full
        $show = $request->query('show');                                            // how many rows to show
        $filepart = $request->query('filepart');                                    // what file part (folder) to display
        $viewfolder_id = $this->get_folder_info( $filepart );                       // get the folder id from the filepart, or -1 for 'info', -2 for file contacts, or 0 'all'

        $entries = Entry::query();                                                  // start building the entry query
        
        if( $viewfolder_id > -1 ) {                                                 // if folder id > -1, we're requesting entries for a file, so;
            $entries->where('file_id', $file->id);                          // add a filter on file_id
        }
        
        if( $viewfolder_id > 0 ) {                                                  // If this is for a specific folder,  
            $entries->where('folder_id', $viewfolder_id);                           // add that filter
        }

        $entries = $entries
                ->with( [ 'response' => ['response_to'],                            // the response entry showing what entry this is responsive to
                          'responses_received' => function($query) {                // the response entries showing entries responding to this entry
                             $query->orderBy('response_type','asc')                 // order by response type (Full or Partial)
                                   ->orderBy('response_date','asc');
                           },
                        ])
                ->orderBy('date1')
                // ->leftJoin('contacts', 'entries.from_contact_id', '=', 'contacts.id')    // NOTE: this is how to sort (orderBy) the entries by contact name
                // ->orderBy('contacts.last_name')                                          // to implement sorting on the list
                ->paginate($show ? $show : 15)                                      // paginate by show value, if available, else 15
                ->withQueryString();

        $assignedAttorney = $file->assignedAttorney;                                // Load the assigned attorney (File.php) from contact_roles

        // Load the client from contact_roles
        $clientContactRole = ContactRole::where('file_id', $file->id)
                        ->where('is_file_client', true)
                        ->with('contact:id,display_last_first')
                        ->first();

        return Inertia::render('Entries/Index',
            [   'file' => $file,                                                                            // return file record
                'entries' => $entries,                                                                              // entries
                'view_folder_id' => $viewfolder_id,                                                                 // id of folder being viewed
                'expecting_response' => $this->getExpectingResponse( $file->id ),                               // file entries expecting a response
                'firm_members' => $this->getFirmMembers( $file->firm_id, $refresh ),                            // firm members
                'attorneys' => $this->getAttorneys( $file->firm_id, $refresh ),                                 // attorneys
                'filetypes' => $this->getFileTypes( $file->firm_id, $refresh ),                         // all the firm's filetypes (for the file edit and the droplist)
                'folders' => $this->getFirmFolders( $file->firm_id, $refresh, $request->new_entrytype_added ),  // folders (with their entrytypes)
                'file_contacts' => $this->getFileContacts( $file->id, $refresh, $request->new_contact_added ),  // all contacts in this file
                'assigned_attorney_id' => $assignedAttorney?->contact_id,
                'client_name' => $clientContactRole?->contact?->display_last_first ?? '',
                'contact_role_ids' => $this->getContactRoleIds($file->id),
                'role_options' => ContactRole::ROLE_LABELS,
                'file_contact_roles' => $this->getFileContactRoles($file->id, $refresh),
            ]);
    }

/* Skip this function, it is not used anymore
public function create(File $file, Request $request)        // NOTE: this might not be called anymore.
    {   dd('THIS IS CALLED');
        $filepart = $request->query('filepart');

        $foldertypes = ['correspondence', 'pleadings', 'discovery', 'documents', 'memos', 
                        'events', 'todo', 'phone', 'medrecs', 'medbills', 'costs',  // 'all', // 'info',
                       ];

        if( in_array($filepart, $foldertypes) ) {  // if the requested filepart is in the list of valid file parts, then proceed

            $folder = Folder::where('short_name', $filepart)->first();

            $entrytypes = Entrytype::query()
                            ->select('id','name')
                            ->where('firm_id', $request->user()->firm_id)
                            ->where('folder_id', $folder->id)
                            ->get();

            $default_type = $this->setDefaultEntryType($filepart, $request->user()->firm_id);

            $expecting_entries = Entry::query()
                                    ->select('id','date1','from_contact_id','entrytype_id')
                                    ->with(['contact_from:id,display_name', 'entrytype:id,name'])
                                    ->where('file_id', $file->id)
                                    ->where('expecting_response', true)
                                    ->orderBy('date1')
                                    ->get();

            return inertia::render( 'Entries/EntryForm', 
                                    [   'editmode' => 'create',
                                        'file' => $file, 
                                        'folder' => $folder, 
                                        'entrytypes' => $entrytypes,
                                        'expecting_entries' => $expecting_entries,
                                        'default_type' => $default_type,
                                        'added_contact_name' => '',  // for modal
                                        'added_contact_id' => 0,    // for modal
                                    ]);
        } // endif in_array - valid filepart    
    } // end function create

*/


    public function store(StoreEntryRequest $request, File $file)
    {   // dd($request);
        // dd( $this->get_folder_info($request->folder_id, 'name') );
        // dd($request);

        $entry = new Entry;

        $entry->firm_id = $request->user()->firm_id;    // user's firm_id
        $entry->file_id = $file->id;            // the file id
        $entry->folder_id = $request->folder_id;        // the folder id
        $entry->entrytype_id = $request->entrytype_id;  // the entrytype id
        
        $entry->date1 = $request->date1;
        $entry->date2 = empty($request->date2) ? null : $request->date2; // if empty, use null

        $entry->from_contact_id = $request->from_contact_id;

        if( $entry->folder_id === 6 ) {                         // if this is an event folder entry
            $entry->to_contact_id = $entry->from_contact_id;    // copy from_contact_id to the to_contact_id

            $entry->date_response_expected = $entry->date1;     // copy the date1 into date_response_expected
            $entry->on_calendar = true;                         // mark it as on_calendar
        } else {                                                // ELSE, this is not an events folder entry
            $entry->to_contact_id = empty($request->to_contact_id) ? null : $request->to_contact_id;    // if empty, use null, otherwise use the id
                                                                                                        // NOTE: change later to put FILE for empty to contact (To: FILE)
            $entry->date_response_expected = $request->date_response_expected;
            if( !empty( $request->date_response_expected ) ) $entry->expecting_response = true; // if date_response_expected, expecting_response is true
                
            $entry->on_calendar = false;
            $entry->all_day = false;
        }

        $entry->note = $request->note;

        if( isset($request->amount) ) {
            $entry->amount = empty($request->amount) ? null : $request->amount;
        }

        $entry->save();

        // Handle pending contact roles
        $this->savePendingContactRoles($request, $file->id);

        // if this is a response to another entry, handle it
        if( $request->is_a_response != "N" && !empty($request->is_response_to) ) {
            $this->handleThisResponse( $request->is_a_response, $entry->id, $request->is_response_to, $entry->date1, $create = true );
        }

        $filepart = $this->get_folder_info($request->folder_id, 'name');

        return redirect('/files/' . $file->id . '/entries?filepart=' . $filepart . '&page=' . $request->current_page . '&show=' . $request->show);
    }
    

    public function show($id)
    {
        //
    }


    public function edit(File $file, $entry_id, Request $request)               // NOTE: this might not get called anymore, because the edit is now in the index
    {
        dd('THIS IS CALLED');
        $entry = Entry::query()
                        ->where('id', $entry_id)
                        ->with(['contact_from:id,display_last_first',
                                'contact_to:id,display_last_first',
                                'folder:id,name', 
                                'entrytype:id,name',
                                'response',
                                'responses_received',
                                ])
                        ->first();

        $folder = Folder::where('id', $entry->folder_id)->first();

        $entrytypes = Entrytype::query()
                        ->select('id','name')
                        ->where('firm_id', $request->user()->firm_id)
                        ->where('folder_id', $folder->id)
                        ->get();

        $default_type = $this->setDefaultEntryType($folder->short_name, $request->user()->firm_id);

        if( $entry->response ) {                    // if this entry is a response, expecting entries are all the expecting, plus the one this is a response to
            $expecting_entries = Entry::query()
                ->select('id','date1','from_contact_id','entrytype_id')
                ->with(['contact_from:id,display_name', 'entrytype:id,name', 'responses'])
                ->where('file_id', $file->id)
                ->where('expecting_response', true)
                ->orWhere('id', $entry->response->response_to)
                ->orderBy('date1')
                ->get();
        }
        else {                                      // else, a list of all entries expecting a response
            $expecting_entries = Entry::query()
                ->select('id','date1','from_contact_id','entrytype_id')
                ->with(['contact_from:id,display_name', 'entrytype:id,name', 'responses_received'])
                ->where('file_id', $file->id)
                ->where('expecting_response', true)
                ->orderBy('date1')
                ->get();
        }

        return inertia::render( 'Entries/EntryForm', 
                                    [   'editmode' => 'edit',
                                        'entry' => $entry,
                                        'file' => $file, 
                                        'folder' => $folder, 
                                        'entrytypes' => $entrytypes,
                                        'expecting_entries' => $expecting_entries,
                                        'default_type' => $default_type,
                                        'added_contact_name' => '',  // for modal
                                        'added_contact_id' => 0,    // for modal
                                    ]);
    }


    public function update( StoreEntryRequest $request, $file_id, Entry $entry )
    {        
        $entry->entrytype_id = $request->entrytype_id;

        $entry->date1 = $request->date1;
        $entry->date2 = empty($request->date2) ? null : $request->date2; // if empty, use null

        $entry->from_contact_id = $request->from_contact_id;

        if( $entry->folder_id === 6 ) {                                                                 // if this is an event folder entry
            $entry->to_contact_id = $entry->from_contact_id;                                            // copy from_contact_id to the to_contact_id

            $entry->date_response_expected = $entry->date1;                                             // copy the date into date_response_expected
            $entry->on_calendar = true;                                                                 // mark it as on_calendar
        } else {                                                                                        // ELSE, this is not an events folder entry
            $entry->to_contact_id = empty($request->to_contact_id) ? null : $request->to_contact_id;    // if empty, use null, otherwise use the id
                                                                                                        // NOTE: change later to put FILE for empty to contact (To: FILE)
            $entry->date_response_expected = $request->date_response_expected;    
            $entry->expecting_response = $this->responseExpectedTF( $entry->id, $request->date_response_expected );
        }

        $entry->note = $request->note;

        if( isset($request->amount) ) {
            $entry->amount = empty($request->amount) ? null : $request->amount;
        }

        if( $entry->firm_id === $request->user()->firm_id) {            // only update this record if the firm_id matches the user firm_id
            $entry->save();

            // Check if from_contact changed and cleanup old contact role
            if ($entry->wasChanged('from_contact_id') && $entry->getOriginal('from_contact_id')) {
                $this->cleanupContactRole($entry->file_id, $entry->getOriginal('from_contact_id'));
            }
            // Check if to_contact changed and cleanup old contact role
            if ($entry->wasChanged('to_contact_id') && $entry->getOriginal('to_contact_id')) {
                $this->cleanupContactRole($entry->file_id, $entry->getOriginal('to_contact_id'));
            }

            // Handle pending contact roles
            $this->savePendingContactRoles($request, $entry->file_id);

            if( $request->is_a_response == "N" ) {
                $this->handleIsNoResponse($entry->id);
            }
            else if( $request->is_a_response != "N" && !empty($request->is_response_to) ) {
                $this->handleThisResponse( $request->is_a_response, $entry->id, $request->is_response_to, $entry->date1, $create = false );
            }
    
            if( $request->comeback == true ) {
                return redirect('/files/' . $entry->file_id . '/entries?page=' . $request->current_page . '&show=' . $request->show . '&filepart=' . $request->filepart);
            }
    
        }
    }


    public function destroy(Request $request, $file_id, Entry $entry)
    {
        // dd($request);

        $verified = $request->validate(            
            [   'entry_id' => 'numeric|integer|required',
                'current_page' => 'numeric|integer|required',
                'show' => 'numeric|integer|required',
                'filepart' => 'string|max:20',
            ]);
        
        if( $request->entry_id == $entry->id ) {
            $this->handleIsNoResponse($entry->id);  // delete the response for this entry
            $entry->delete();                       // delete this entry
        }

        return redirect('/files/' . $entry->file_id . '/entries?page=' . $request->current_page . '&show=' . $request->show . '&filepart=' . $request->filepart);
    }


    public function lookup_contact(Request $request)
    {
        $contacts_found = Contact::query()
                            ->select('id','display_last_first')
                            ->where('firm_id', $request->user()->firm_id)
                                // next, when only looking for firm members, add the needed where clause
                            ->when($request->firm_only == true, function ($query) {
                                $query->where('is_firm_member', '=', true);
                            })

                            // ->where('display_last_first', 'like', '%' . $search . '%')   - less efficient name search
                            ->where('display_last_first', 'like', $request->search . '%')
                            ->orderBy('display_last_first')

                            ->paginate(8)
                            ->withQueryString();

        return $contacts_found;
    }


    public function get_folder_info( $var_in, $what = 'id' )
    {
        $sendback = 0;
        $folder_list = ['all', 'correspondence', 'pleadings', 'discovery', 'documents',
                        'memos','events', 'todo', 'phone', 'medrecs', 'medbills', 'costs'];

        if( $what === 'id' ) {                                          // if what we want back is the folder id
            if( $var_in === 'info' ) $sendback = -1;                        // if 'info', sendback is -1
            else if( $var_in === 'file_contacts' ) $sendback = -2;           // if 'file_contacts', sendback is -2
            else $sendback = array_search( $var_in, $folder_list );         // else, sendback is the position in array
        } else if( $what === 'name' ) {                                 // else if we want the folder name
            if( $var_in >= 0 && $var_in < 12 ) $sendback = $folder_list[ $var_in ]; // sendback is folder name
        }

        return $sendback;
    }


    
    public function contact_add_modal(Request $request) {
        // dd($request);
        $verified1 = $request->validate(
            [   'title' => ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.', 'Co.'])],
                // 'form_for' => Rule::in(['from', 'to']),
            ]);

        if( $request->title === 'Co.' ) {
            $verified2 = $request->validate( [
                'company' => 'required|max:255',
                'first_name' => 'nullable|max:255',
                'last_name' => 'nullable|max:255',
            ]);
        }
        else {
            $verified2 = $request->validate( [
                'company' => 'nullable|max:255',
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
            ]);
        }

        $verified3 = $request->validate( [
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
            'display_name' => ['required', Rule::unique('contacts')->where('firm_id', $request->user()->firm_id) ],
            'display_last_first' => 'max:255',
        ],
        [
            'display_name' => 'The names in your contact list must be unique, and the name ' . $request->display_name . ' is already in your contact list.  To distinguish this contact, try using a middle initial or appending a number in parentheses to the last name.',
        ]);

        // Passed validations, so add new contact
        $contact = new Contact;
        $contact->title = $request->title;
        $contact->first_name = $request->first_name;
        $contact->middle_name = $request->middle_name;
        $contact->last_name = $request->last_name;
        $contact->srjr = $request->srjr;
        $contact->esqphd = $request->esqphd;
        $contact->company = $request->company;
        $contact->business_title = $request->business_title;
        $contact->address = $request->address;
        $contact->email = $request->email;
        $contact->email_alt = $request->email_alt;
        $contact->work_phone = $request->work_phone;
        $contact->cell_phone = $request->cell_phone;
        $contact->home_phone = $request->home_phone;
        $contact->fax_phone = $request->fax_phone;
        $contact->other_phone = $request->other_phone;
        $contact->display_name = $request->display_name;
        $contact->display_last_first = $request->display_last_first;

        $contact->firm_id = $request->user()->firm_id;

        $contact->save();

        return inertia::render('Entries/Index', [ 'added_contact_name' => $contact->display_last_first,
                                                  'added_contact_id' => $contact->id,
                                                  'form_for' => $request->form_for,
                                                ]);
    } // end function


    public function contact_add_modal2(Request $request) {
        // dd($request);
        $verified1 = $request->validate(
            [   'title' => ['required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.', 'Co.'])],
                // 'form_for' => Rule::in(['from', 'to']),
            ]);

        if( $request->title === 'Co.' ) {
            $verified2 = $request->validate( [
                'company' => 'required|max:255',
                'first_name' => 'nullable|max:255',
                'last_name' => 'nullable|max:255',
            ]);
        }
        else {
            $verified2 = $request->validate( [
                'company' => 'nullable|max:255',
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
            ]);
        }

        $verified3 = $request->validate( [
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
            'display_name' => ['required', Rule::unique('contacts')->where('firm_id', $request->user()->firm_id) ],
            'display_last_first' => 'max:255',
        ],
        [
            'display_name' => 'The names in your contact list must be unique, and the name ' . $request->display_name . ' is already in your contact list.  To distinguish this contact, try using a middle initial or appending a number in parentheses to the last name.',
        ]);

        // Passed validations, so add new contact
        $contact = new Contact;
        $contact->title = $request->title;
        $contact->first_name = $request->first_name;
        $contact->middle_name = $request->middle_name;
        $contact->last_name = $request->last_name;
        $contact->srjr = $request->srjr;
        $contact->esqphd = $request->esqphd;
        $contact->company = $request->company;
        $contact->business_title = $request->business_title;
        $contact->address = $request->address;
        $contact->email = $request->email;
        $contact->email_alt = $request->email_alt;
        $contact->work_phone = $request->work_phone;
        $contact->cell_phone = $request->cell_phone;
        $contact->home_phone = $request->home_phone;
        $contact->fax_phone = $request->fax_phone;
        $contact->other_phone = $request->other_phone;
        $contact->display_name = $request->display_name;
        $contact->display_last_first = $request->display_last_first;

        $contact->firm_id = $request->user()->firm_id;

        $contact->save();

        $sendback = [ 'added_contact_name' => $contact->display_last_first,
                      'added_contact_id' => $contact->id
                    ];

        return $sendback;

    
    } // end function


    public function new_contact_modal(Request $request) {
        $verified1 = $request->validate(
            [   'title' => [ 'required', Rule::in(['Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Hon.', 'Co.']) ],
            ]);

        if( $request->title === 'Co.' ) {
            $verified2 = $request->validate( [
                'company' => 'required|max:255',
                'first_name' => 'nullable|max:255',
                'last_name' => 'nullable|max:255',
            ]);
        } else {
            $verified2 = $request->validate( [
                'company' => 'nullable|max:255',
                'first_name' => 'required|max:255',
                'last_name' => 'required|max:255',
            ]);
        }

        $verified3 = $request->validate( [
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
            'display_name' => ['required', Rule::unique('contacts')->where('firm_id', $request->user()->firm_id) ],
            'display_last_first' => 'max:255',
        ],
        [
            'display_name' => 'The names in your contact list must be unique, and the name ' . $request->display_name . 
                              ' is already in your contact list.  To distinguish this contact, try using a middle initial or appending a number in parentheses to the last name.',
        ]);

        // Validations ok, so add new contact

        $contact = new Contact;

        $contact->title = $request->title;
        $contact->first_name = $request->first_name;
        $contact->middle_name = $request->middle_name;
        $contact->last_name = $request->last_name;
        $contact->srjr = $request->srjr;
        $contact->esqphd = $request->esqphd;
        $contact->company = $request->company;
        $contact->business_title = $request->business_title;
        $contact->address = $request->address;
        $contact->email = $request->email;
        $contact->email_alt = $request->email_alt;
        $contact->work_phone = $request->work_phone;
        $contact->cell_phone = $request->cell_phone;
        $contact->home_phone = $request->home_phone;
        $contact->fax_phone = $request->fax_phone;
        $contact->other_phone = $request->other_phone;
        $contact->display_name = $request->display_name;
        $contact->display_last_first = $request->display_last_first;

        $contact->firm_id = $request->user()->firm_id;

        $contact->save();

        return response()->json([ "added_contact_name" => $contact->display_last_first,
                                  "added_contact_id" => $contact->id ], 200);
                                  
    } // end function


    public function toggle_read(Request $request, Entry $entry)
    {
        // dd($request);
        $sendback = '';

        if( $entry ) {
            if( $entry->firm_id === $request->user()->firm_id ) {
                if( $entry->date2 ) $entry->date2 = NULL;
                else $entry->date2 = date("Y-m-d");

                $entry->save();

                $sendback = $entry->date2;
            }
        }

        return $sendback;
    }

    public function setDefaultEntryType($filepart, $firm_id)
    {
        $default_entrytype = [];
        $default_entrytype['name'] = '';
        $default_entrytype['id'] = 0;
        $default_entrytype['disable_types'] = false;      
        
        if( $filepart == 'correspondence' ) {
            $found_type = Entrytype::query()
                            ->where('firm_id', $firm_id)
                            ->where('folder_id', $this->get_folder_info($filepart))
                            ->where('name','Letter')
                            ->first();
            if($found_type) {
                // dd($found_type);
                $default_entrytype['name'] = $found_type->name;
                $default_entrytype['id'] = $found_type->id;
                $default_entrytype['disable_types'] = false;
            }
        }

        return $default_entrytype;
    }

    public function handleIsNoResponse($entry_id_in)
    {
        $found_response = Response::where('entry_id', $entry_id_in)->first();       // look for a response from this entry

        if( $found_response )  {                                                    // if a response was found

            if( $found_response->response_type === 'F') {                           // if this had been a full response, so update the related entry to again expect a response

                $related_entry = Entry::where('id', $found_response->response_to)->first();

                if( $related_entry && $related_entry->date_response_expected ) {    // found the related entry and it was expecting a response by a date
                    $related_entry->expecting_response = true;                      // so, now it is expecting a response again
                    $related_entry->save();
                }
            }

            $found_response->delete();      // now, delete response from this entry, because it is now not a response
        }
    }

    public function handleThisResponse($response_type, $entry_from, $response_to, $response_date, $create = false)
    {
        if( $create === false ) {                                                       // if create is false, this is not a new entry - look for a response record
            $found_response = Response::where('entry_id', $entry_from)->first();
        }

        if( $create === true || ( $create === false && empty($found_response) ) ) {     // if this is for a new entry being created or an existing one without a found response

            $new_response = new Response;                                               // create a new response for this entry

            $new_response->entry_id = $entry_from;                                      // entry_id that the response is from
            $new_response->response_to = $response_to;                                  // entry_id that the response is to
            $new_response->response_date = $response_date;                              // response date
            $new_response->response_type = $response_type;                              // Full or Partial response

            $new_response->save();
        }
        else if( $create == false ) {                                                // else if this is an existing entry which has a found response
            if( $found_response->response_to == $response_to ) {                        // if found response is for the same related, then update it

                $hold_type = $found_response->response_type;                            // hold the found response type

                $found_response->response_date = $response_date;
                $found_response->response_type = $response_type;                        // Full or Partial response
                $found_response->save();

                if( $hold_type == 'F' && $response_type == 'P' ) {                      // And, if the prior response was full, but now it's partial, update the related entry to expect a response
                    
                    $related_entry = Entry::where( 'id', $response_to )->first();

                    if( $related_entry ) {
                        $related_entry->expecting_response = true;
                        $related_entry->on_calendar = true;  // ?? should this always be true??
                        $related_entry->all_day = true;      // ?? is this always correct??
                        $related_entry->save();    
                    }
                } // end if was F, but now P
            } else if( $found_response->response_to != $response_to ) {                 // else if the found response was to a different related entry (this entry now responds to a different entry)

                if( $found_response->response_type == 'F') {                            // if the found response was a 'Full Response', reset the prior related entry to again expect a response

                    $prior_entry = Entry::where( 'id', $found_response->response_to )->first();  // get the prior related entry and reset it to expecting

                    if( $prior_entry && $prior_entry->date_response_expected && $prior_entry->expecting_response === false )  {   // prior entry has response date, but not expecting a response
                        $prior_entry->expecting_response = true;    // so now it is expecting a response again
                        $prior_entry->on_calendar = true;
                        $prior_entry->all_day = true;
                        $prior_entry->save();    
                    }
                } // end if prior response was full

                    // Now update the found response
                $found_response->response_to = $response_to;
                $found_response->response_date = $response_date;
                $found_response->response_type = $response_type;   // Full or Partial response
                $found_response->save();
            } // end else found prior response was NOT to the same related entry
        }

        // Now, if this is a full response, update the related entry
        if( $response_type == 'F') {

            $related_entry = Entry::where( 'id', $response_to )->first();
            
            if ( $related_entry ) {
                $related_entry->expecting_response = false;

                if( $related_entry->folder_id != 6 ) {  // the related entry was not an event, so take it off the calendar
                    $related_entry->on_calendar = false;
                    $related_entry->all_day = false;    
                }

                $related_entry->save();    
            }
        }

    } // end handleThisResponse function

        // Get all contacts for a file, only if full refresh or new contact added
    public function getFileContacts( $file_id, $refresh = 'full', $new_contact_added = false )
    {
        if( $refresh === 'full' || $new_contact_added === true ) {
            $from_contacts = DB::table('entries')->select('from_contact_id')->where('file_id', $file_id)->distinct();       // get all "from" contact ids for this file
            $to_contacts = DB::table('entries')->select('to_contact_id')->where('file_id', $file_id)->distinct();           // get all "to" contact ids for this file
    
            $file_contacts = DB::table('contacts')->select('id','display_last_first')                                               // get all contacts for this file
                                    ->whereIn('id', $from_contacts)
                                    ->orWhereIn('id', $to_contacts)
                                    ->get();
        } else {
            $file_contacts = [];
        }
        return $file_contacts;
    }


        // Get all folders for a firm, only if full refresh or new entrytype added
    public function getFirmFolders( $thefirmid, $refresh = 'full', $new_entrytype_added = false )
    {
        if( $refresh === 'full' || $new_entrytype_added === true ) {                                                // if full refresh or new entrytype added, get folders with their entrytypes
            $folders = Folder::query()
                        ->where('id', '>', '0')                                                                     // get all the folders with their entrytypes for this firm
                        ->with(['entrytypes' => function($query) use ($thefirmid) {
                                                $query->select('id','folder_id','name')->where('firm_id', $thefirmid)->orderBy('name'); }])
                        ->get();
        } else {
            $folders = [];                                                                                          // else, return empty array
        }
        return $folders;
    }

        // Get all firm members for a firm, only if full refresh
    public function getFirmMembers( $thefirmid, $refresh = 'full' ) {
        if( $refresh === 'full' ) {                                    // if full refresh, get all current firm members
            $firm_members = Contact::query()
                                ->select('display_last_first','id','current','firm_role')
                                ->where('firm_id', $thefirmid)
                                ->where('is_firm_member', true)
                                ->where('current', 'C')
                                ->orderBy('display_last_first')
                                ->get();
        } else {                                                      // else, return empty array
            $firm_members = [];
        }
        return $firm_members;    
    }

        // Get all attorneys for a firm, only if full refresh
    public function getAttorneys($thefirmid, $refresh = 'full') {
        if( $refresh === 'full' ) {      
            $attorneys = Contact::query()
                            ->select('display_last_first','id','current','firm_role')
                            ->where('firm_id', $thefirmid)
                            ->where('is_firm_member', true)
                            ->where('current', 'C')   // remove current filter for now, will handle in User later
                            ->where('firm_role', 'Attorney')
                            ->orderBy('display_last_first')
                            ->get();
        } else {
            $attorneys = [];  // else, return empty array
        }
        return $attorneys;
    }

        // Get all file types for a firm, only if full refresh
    public function getFileTypes( $thefirmid, $refresh = 'full' ) {   
        if( $refresh === 'full' ) {
            $filetypes = Filetype::query()
                            ->where('firm_id', $thefirmid)
                            ->orderBy('name')
                            ->get();
        } else {
            $filetypes = [];  // else, return empty array
        }
        return $filetypes;
    }


        // Get all entries for a file that are expecting a response - Note: What about when not viewing a file?
    public function getExpectingResponse( $file_id )
    {
        $expecting_entries = Entry::query()
        ->select('id','date1','from_contact_id', 'folder_id', 'entrytype_id', 'file_id')
        ->with('contact_from:id,display_last_first')                                                        // include from contact name 
        ->where('file_id', $file_id)                                                                // viewing a file, so filter on case_id
        ->where('expecting_response', true)
        ->orderBy('file_id')
        ->orderBy('date1')
        ->get();
        
        return $expecting_entries;
    }


        // return false if no response date is specified, or if the entry already has received a full response
    public function responseExpectedTF( $entry_id, $date_response_expected = null ) {
        if( $date_response_expected == null or empty( $date_response_expected ) ) $sendback = false;            // no response expected date, so false
        else {
            $aResponse = Response::where( 'response_to', $entry_id )->where( 'response_type' , 'F')->first();   // look for a full response
            if( $aResponse ) $sendback = false;                                                                 // full response found, so return false (response expected is false)
            else $sendback = true;                                                                              // no full response found, so return true - a response is still expected
        }

        return $sendback;
    }
    

    public function savePendingContactRoles(Request $request, $file_id)
    {
        if (!empty($request->pending_contact_roles) && is_array($request->pending_contact_roles)) {
            foreach ($request->pending_contact_roles as $pendingRole) {
                ContactRole::firstOrCreate(
                    [
                        'file_id' => $file_id,
                        'contact_id' => $pendingRole['contact_id'],
                        'role' => $pendingRole['role'],
                    ],
                    [
                        'role_label' => $pendingRole['role_label'] ?? ContactRole::ROLE_LABELS[$pendingRole['role']] ?? $pendingRole['role'],
                    ]
                );
            }
        }
    }

    public function getFileContactRoles($file_id, $refresh = 'full')
    {
        if ($refresh === 'full' || $refresh === 'ContactRoles') {
            return ContactRole::where('file_id', $file_id)
                ->with(['contact:id,display_last_first'])
                ->get()
                ->map(fn($cr) => [
                    'id' => $cr->id,
                    'contact_name' => $cr->contact?->display_last_first ?? '',
                    'role_name' => $cr->role_label ?? (ContactRole::ROLE_LABELS[$cr->role] ?? $cr->role),
                    'role' => $cr->role,
                    'is_protected' => $cr->is_protected,
                    'is_file_attorney' => $cr->is_file_attorney,
                    'is_file_client' => $cr->is_file_client,
                ]);
        }
        return [];
    }

    public function getContactRoleIds($file_id)
    {
        return ContactRole::where('file_id', $file_id)->pluck('contact_id')->unique()->values()->toArray();
    }

    private function cleanupContactRole($file_id, $contact_id)
    {
        // Don't remove protected, file attorney, or file client roles
        $contactRoles = ContactRole::where('file_id', $file_id)
            ->where('contact_id', $contact_id)
            ->where('is_protected', false)
            ->where('is_file_attorney', false)
            ->where('is_file_client', false)
            ->get();

        if ($contactRoles->isEmpty()) return;

        // Check if any other entries in this file reference this contact
        $otherEntries = Entry::where('file_id', $file_id)
            ->where(function ($q) use ($contact_id) {
                $q->where('from_contact_id', $contact_id)
                  ->orWhere('to_contact_id', $contact_id);
            })
            ->exists();

        if (!$otherEntries) {
            foreach ($contactRoles as $contactRole) {
                $contactRole->delete();
            }
        }
    }

    public function add_new_entrytype(Request $request)
    {
        $verified = $request->validate(
        [   'name' => 'required|string|max:255',
            'id' => 'nullable|numeric|integer',
            'folder_id' => 'required|numeric|integer',
            'isChosen' => 'boolean|nullable',
            'chosen_name' => 'nullable|string|max:255',
            'lookup' => 'boolean|nullable',
        ],
        [
            'folder_id' => 'Invalid folder identification',
        ]);

        if( $request->name != $request->chosen_name ) {
            $new_entrytype = new Entrytype;
            $new_entrytype->firm_id = $request->user()->firm_id;
            $new_entrytype->folder_id = $request->folder_id;
            $new_entrytype->name = $request->name;
            $new_entrytype->save();
        }

        return Inertia::render('Entries/Index', [
            'folders' => fn() => $this->getFirmFolders( $new_entrytype->firm_id ),      // no 2nd parameter forces a new query
            'new_entrytype' => $new_entrytype, 
        ]);        

    }



    public function testparts()
    {
        return inertia::render('Entries/Testparts');
    }

    public function testtwo()
    {
        return inertia::render('Entries/Testtwo');
    }

}
