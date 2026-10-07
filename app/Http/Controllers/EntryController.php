<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\StoreEntryRequest;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Entry;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Filetype;
use App\Models\Firm;
use App\Models\Folder;
use App\Models\RecentFile;
use App\Services\EntryResponseService;
use App\Services\FirmLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntryController extends Controller
{
    public function __construct(
        private EntryResponseService $responses,
        private FirmLookupService $lookups,
    ) {}

    public function index(Request $request, $file_id)
    {
        $firmId = $request->user()->firm_id;
        $reservedFileId = config('documents.reserved_file_id');

        $file = File::where('id', $file_id)                              // get the file record
            ->where(function ($query) use ($firmId, $reservedFileId) {   // caller's own file, or the shared reserved (non-file-specific) file
                $query->where('firm_id', $firmId)
                    ->orWhere('id', $reservedFileId);
            })
            ->with('Filetype')->firstOrFail();                          // with the filetype; 404 if missing or another firm's

        $refresh = $request->header('X-Custom-Refresh') ?? 'full';                  // if refresh flag is set in the header, otherwise full

        $show = min((int) $request->query('show', 15) ?: 15, 50);                   // how many rows to show (capped at 50)
        $filepart = $request->query('filepart', 'correspondence');                  // what file part (folder) to display

        // Validate that the requested folder exists for this file's type
        if (! in_array($filepart, ['all', 'info', 'file_contacts'])) {
            $folderFlag = 'has_'.$filepart;
            if (! $file->filetype->$folderFlag) {
                $filepart = 'correspondence';
            }
        }

        $viewfolder_id = $this->get_folder_info($filepart);                         // get the folder id from the filepart, or -1 for 'info', -2 for file contacts, or 0 'all'

        RecentFile::track(                                                          // track recent-file usage
            auth()->id(),
            $file->id,
            $filepart ?: 'correspondence',
            (int) ($request->query('page') ?: 1),
            (int) ($show ?: 15)
        );

        $entries = Entry::query()                                                   // start building the entry query
            ->where('firm_id', $firmId);                                            // isolate to the caller's firm (essential for the shared reserved file; no-op for normal files)

        if ($viewfolder_id > -1) {                                                 // if folder id > -1, we're requesting entries for a file, so;
            $entries->where('file_id', $file->id);                          // add a filter on file_id
        }

        if ($viewfolder_id > 0) {                                                  // If this is for a specific folder,
            $entries->where('folder_id', $viewfolder_id);                           // add that filter
        }

        $entries = $entries
            ->with(['response' => ['response_to'],                            // the response entry showing what entry this is responsive to
                'responses_received' => function ($query) {                // the response entries showing entries responding to this entry
                    $query->with('entry:id,date1,folder_id', 'entry.folder:id,name')
                        ->orderBy('response_type', 'asc')                 // order by response type (Full or Partial)
                        ->orderBy('response_date', 'asc');
                },
            ])
            ->orderBy('date1')
                // ->leftJoin('contacts', 'entries.from_contact_id', '=', 'contacts.id')    // NOTE: this is how to sort (orderBy) the entries by contact name
                // ->orderBy('contacts.last_name')                                          // to implement sorting on the list
            ->paginate($show)                                                   // paginate by capped show value
            ->withQueryString();

        $assignedAttorney = $file->assignedAttorney;                                // Load the assigned attorney (File.php) from contact_roles

        // Load the client from contact_roles
        $clientContactRole = ContactRole::where('file_id', $file->id)
            ->where('is_file_client', true)
            ->with('contact:id,display_last_first')
            ->first();

        return Inertia::render('Entries/Index',
            ['file' => $file,                                                                            // return file record
                'entries' => $entries,                                                                              // entries
                'view_folder_id' => $viewfolder_id,                                                                 // id of folder being viewed
                'view_folder_name' => $filepart,                                                                     // resolved folder name (may differ from URL if defaulted)
                'expecting_response' => $this->getExpectingResponse($file->id, $firmId),                       // file entries expecting a response
                'firm_members' => $refresh === 'full' ? $this->lookups->firmMembers($firmId) : [],                   // firm members (caller's firm, not the file owner — matters for the shared reserved file)
                'attorneys' => $refresh === 'full' ? $this->lookups->attorneys($firmId) : [],                        // attorneys
                'filetypes' => $this->getFileTypes($firmId, $refresh),                                // all the firm's filetypes (for the file edit and the droplist)
                'folders' => $refresh === 'full' ? $this->lookups->folders($firmId) : [],                            // folders (with their entrytypes)
                'file_contacts' => $this->getFileContacts($file->id, $firmId, $refresh),  // all contacts in this file
                'assigned_attorney_id' => $assignedAttorney?->contact_id,
                'client_name' => $clientContactRole?->contact?->display_last_first ?? '',
                'contact_role_ids' => $this->getContactRoleIds($file->id, $firmId),
                'role_options' => ContactRole::ROLE_LABELS,
                'file_contact_roles' => $this->getFileContactRoles($file->id, $firmId, $refresh),
                'firm_document_base_path' => Firm::find($request->user()->firm_id)?->document_base_path,
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

        if ($entry->folder_id === 6) {                         // if this is an event folder entry
            $entry->to_contact_id = $entry->from_contact_id;    // copy from_contact_id to the to_contact_id

            $entry->date_response_expected = $entry->date1;     // copy the date1 into date_response_expected
            $entry->on_calendar = true;                         // mark it as on_calendar
            $entry->all_day = $request->boolean('all_day');     // set all_day from request
        } else {                                                // ELSE, this is not an events folder entry
            $entry->to_contact_id = empty($request->to_contact_id) ? null : $request->to_contact_id;    // if empty, use null, otherwise use the id
            // NOTE: change later to put FILE for empty to contact (To: FILE)
            $entry->date_response_expected = $request->date_response_expected;
            if (! empty($request->date_response_expected)) {
                $entry->expecting_response = true;
            } // if date_response_expected, expecting_response is true

            $entry->on_calendar = false;
            $entry->all_day = false;
        }

        $entry->note = $request->note;
        $entry->linked_document_path = $request->linked_document_path ?: null;

        if (isset($request->amount)) {
            $entry->amount = empty($request->amount) ? null : $request->amount;
        }

        $respondsToId = $request->filled('is_response_to') ? (int) $request->is_response_to : null;

        DB::transaction(function () use ($entry, $request, $file, $respondsToId) {
            $entry->save();

            $this->responses->savePendingContactRoles($file->id, $request->pending_contact_roles);

            $this->responses->sync($entry, $request->is_a_response, $respondsToId, (string) $entry->date1);
        });

        $filepart = $this->get_folder_info($request->folder_id, 'name');

        return redirect('/files/'.$file->id.'/entries?filepart='.$filepart.'&page='.$request->current_page.'&show='.$request->show);
    }

    public function show($id)
    {
        //
    }

    public function edit(File $file, $entry_id, Request $request)               // NOTE: this might not get called anymore, because the edit is now in the index
    {
        if ($file->id !== config('documents.reserved_file_id')) {               // the shared reserved file is accessible to all firms; the entry below is still firm-scoped
            $this->authorize('view', $file);
        }

        $entry = Entry::query()
            ->where('id', $entry_id)
            ->where('firm_id', $request->user()->firm_id)                       // scope to the caller's firm
            ->with(['contact_from:id,display_last_first',
                'contact_to:id,display_last_first',
                'folder:id,name',
                'entrytype:id,name',
                'response',
                'responses_received',
            ])
            ->firstOrFail();

        $folder = Folder::where('id', $entry->folder_id)->first();

        $entrytypes = Entrytype::query()
            ->select('id', 'name')
            ->where('firm_id', $request->user()->firm_id)
            ->where('folder_id', $folder->id)
            ->get();

        $default_type = $this->setDefaultEntryType($folder->short_name, $request->user()->firm_id);

        if ($entry->response) {                    // if this entry is a response, expecting entries are all the expecting, plus the one this is a response to
            $expecting_entries = Entry::query()
                ->select('id', 'date1', 'from_contact_id', 'entrytype_id')
                ->with(['contact_from:id,display_name', 'entrytype:id,name', 'responses_received'])
                ->where('file_id', $file->id)
                ->where(function ($query) use ($entry) {
                    $query->where('expecting_response', true)
                        ->orWhere('id', $entry->response->response_to);
                })
                ->orderBy('date1')
                ->get();
        } else {                                      // else, a list of all entries expecting a response
            $expecting_entries = Entry::query()
                ->select('id', 'date1', 'from_contact_id', 'entrytype_id')
                ->with(['contact_from:id,display_name', 'entrytype:id,name', 'responses_received'])
                ->where('file_id', $file->id)
                ->where('expecting_response', true)
                ->orderBy('date1')
                ->get();
        }

        return inertia::render('Entries/EntryForm',
            ['editmode' => 'edit',
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

    public function update(StoreEntryRequest $request, $file_id, Entry $entry)
    {
        $routeFileId = $request->route('file');                         // read the {file} route segment directly (param name independent)
        abort_unless((int) $routeFileId === (int) $entry->file_id, 404);    // the route's file must match the entry's file

        $hold_from_id = $entry->from_contact_id;
        $hold_to_id = $entry->to_contact_id;

        $entry->entrytype_id = $request->entrytype_id;                      // set the entrytype

        $entry->date1 = $request->date1;                                    // set date1
        $entry->date2 = empty($request->date2) ? null : $request->date2;    // set date2, if empty, use null

        $entry->from_contact_id = $request->from_contact_id;

        if ($entry->folder_id === 6) {                                                                 // if this is an event folder entry
            $entry->to_contact_id = $entry->from_contact_id;                                            // copy from_contact_id to the to_contact_id

            $entry->date_response_expected = $entry->date1;                                             // copy the date into date_response_expected
            $entry->on_calendar = true;                                                                 // mark it as on_calendar
            $entry->all_day = $request->boolean('all_day');                                             // set all_day from request
        } else {                                                                                        // ELSE, this is not an events folder entry
            $entry->to_contact_id = empty($request->to_contact_id) ? null : $request->to_contact_id;    // if empty, use null, otherwise use the id
            // NOTE: change later to put FILE for empty to contact (To: FILE)
            $entry->date_response_expected = $request->date_response_expected;
        }

        $entry->note = $request->note;
        $entry->linked_document_path = $request->linked_document_path ?: null;

        if (isset($request->amount)) {
            $entry->amount = empty($request->amount) ? null : $request->amount;
        }

        // Firm ownership is enforced upstream by StoreEntryRequest::authorize() (EntryPolicy::update),
        // so any request reaching this point already belongs to the entry's firm.
        $respondsToId = $request->filled('is_response_to') ? (int) $request->is_response_to : null;

        DB::transaction(function () use ($entry, $request, $respondsToId) {
            $entry->save();

            if ($entry->folder_id !== 6) {
                $this->responses->refreshExpecting($entry->id);
            }

            $this->responses->savePendingContactRoles($entry->file_id, $request->pending_contact_roles);

            $this->responses->sync($entry, $request->is_a_response, $respondsToId, (string) $entry->date1);
        });

        // // Check if from_contact changed and cleanup old contact role
        // if ($entry->wasChanged('from_contact_id') && $entry->getOriginal('from_contact_id')) {
        //     $this->cleanupContactRole($entry->file_id, $entry->getOriginal('from_contact_id'));
        // }
        // // Check if to_contact changed and cleanup old contact role
        // if ($entry->wasChanged('to_contact_id') && $entry->getOriginal('to_contact_id')) {
        //     $this->cleanupContactRole($entry->file_id, $entry->getOriginal('to_contact_id'));
        // }

        // if the initial from or to contact is no longer in this entry, check if they're still in the file
        if ($hold_from_id !== $entry->from_contact_id && $hold_from_id !== $entry->to_contact_id) {
            $this->checkInFile($hold_from_id, $entry->file_id);
        }
        if ($hold_to_id !== $entry->from_contact_id && $hold_to_id !== $entry->to_contact_id) {
            $this->checkInFile($hold_to_id, $entry->file_id);
        }

        if ($request->comeback == true) {
            return to_route('entries.index', ['file' => $entry->file_id,
                'page' => $request->current_page,
                'show' => $request->show,
                'filepart' => $request->filepart,
            ]);

            // return redirect('/files/' . $entry->file_id . '/entries?page=' . $request->current_page . '&show=' . $request->show . '&filepart=' . $request->filepart);
        }
    }

    public function destroy(Request $request, $file_id, Entry $entry)
    {
        $this->authorize('delete', $entry);

        $verified = $request->validate(
            ['entry_id' => 'numeric|integer|required',
                'current_page' => 'numeric|integer|required',
                'show' => 'numeric|integer|required',
                'filepart' => 'string|max:20',
            ]);

        if ($request->entry_id == $entry->id) {
            // Capture contacts before deletion for cleanup
            $fromContactId = $entry->from_contact_id;
            $toContactId = $entry->to_contact_id;

            DB::transaction(function () use ($entry) {
                $this->responses->remove($entry);   // delete the response for this entry
                $entry->delete();                   // delete this entry
            });

            // Clean up contact roles for contacts no longer referenced in any entry
            if ($fromContactId) {
                $this->checkInFile($fromContactId, $entry->file_id);
            }
            if ($toContactId && $toContactId !== $fromContactId) {
                $this->checkInFile($toContactId, $entry->file_id);
            }
        }

        // return redirect('/files/' . $entry->file_id . '/entries?page=' . $request->current_page . '&show=' . $request->show . '&filepart=' . $request->filepart);
        return to_route('entries.index', ['file' => $entry->file_id,
            'page' => $request->current_page,
            'show' => $request->show,
            'filepart' => $request->filepart,
        ]);
    }

    public function lookup_contact(Request $request)
    {
        $contacts_found = Contact::query()
            ->select('id', 'display_last_first')
            ->where('firm_id', $request->user()->firm_id)
            ->where('faux_deleted', false)
            ->when($request->firm_only == true, function ($query) {
                $query->where('is_firm_member', '=', true);
            })
            ->where('display_last_first', 'like', $request->search.'%')
            ->orderBy('display_last_first')
            ->paginate(8)
            ->withQueryString();

        return $contacts_found;
    }

    public function get_folder_info($var_in, $what = 'id')
    {
        $sendback = 0;
        $folder_list = ['all', 'correspondence', 'pleadings', 'discovery', 'documents',
            'memos', 'events', 'todo', 'phone', 'medrecs', 'medbills', 'costs'];

        if ($what === 'id') {                           // if what we want back is the folder id
            if ($var_in === 'info') {                       // if 'info', sendback is -1
                $sendback = -1;
            } elseif ($var_in === 'file_contacts') {          // if 'file_contacts', sendback is -2
                $sendback = -2;
            } else {                                          // else, sendback is the position in array
                $sendback = array_search($var_in, $folder_list);
            }
        } elseif ($what === 'name') {                   // else if we want the folder name
            if ($var_in >= 0 && $var_in < 12) {
                $sendback = $folder_list[$var_in];
            } // sendback is folder name
        }

        return $sendback;
    }

    public function new_contact_modal(StoreContactRequest $request)
    {
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

        return response()->json(['added_contact_name' => $contact->display_last_first,
            'added_contact_id' => $contact->id], 200);

    } // end function

    public function toggle_read(Request $request, Entry $entry)
    {
        $this->authorize('update', $entry);

        if ($entry->date2) {
            $entry->date2 = null;
        } else {
            $entry->date2 = date('Y-m-d');
        }

        $entry->save();

        return $entry->date2;
    }

    public function serve_document(Request $request, Entry $entry, ?string $filename = null): BinaryFileResponse|RedirectResponse
    {
        // 1. Authorization: verify user belongs to the same firm as the entry
        if ($entry->firm_id !== $request->user()->firm_id) {
            abort(403);
        }

        // 2. Check entry has a linked document path
        if (empty($entry->linked_document_path)) {
            return redirect()->back()->with('error', 'This entry has no linked document.');
        }

        $firm = $entry->firm;

        // 3. Validate firm has a document base path configured
        if (! $firm || empty($firm->document_base_path)) {
            return redirect()->back()->with('error', 'Your firm has no document storage path configured.');
        }

        // 4. Resolve the firm base path via the shared safety check; rejects paths
        //    that are missing, inside the application directory, or outside the
        //    configured allow-list (defense in depth against a mis-set base).
        $resolvedBasePath = Firm::safeDocumentBasePath($firm->document_base_path);

        if ($resolvedBasePath === null) {
            return redirect()->back()->with('error', 'The firm document storage path is not configured to an allowed location.');
        }

        // 5. Build the full path using the model helper, then resolve it
        $fullPath = $entry->fullDocumentPath();
        $resolvedFullPath = $fullPath ? realpath($fullPath) : false;

        // 6. File existence check (realpath returns false when path does not exist)
        if ($resolvedFullPath === false || ! is_file($resolvedFullPath)) {
            return redirect()->back()->with('error', 'Document not found at the expected path — the file may have been moved or renamed.');
        }

        // 7. Path traversal protection: resolved path must stay within the firm base directory
        $baseWithSep = rtrim($resolvedBasePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($resolvedFullPath, $baseWithSep)) {
            abort(403, 'Access denied: path is outside the firm document directory.');
        }

        // 8. Serve the file inline so browsers open PDFs/images directly;
        //    unsupported types (Word, Excel) will trigger a download automatically
        return response()->file($resolvedFullPath, [
            'Content-Disposition' => 'inline; filename="'.basename($resolvedFullPath).'"',
        ]);
    }

    public function setDefaultEntryType($filepart, $firm_id)
    {
        $default_entrytype = [];
        $default_entrytype['name'] = '';
        $default_entrytype['id'] = 0;
        $default_entrytype['disable_types'] = false;

        if ($filepart == 'correspondence') {
            $found_type = Entrytype::query()
                ->where('firm_id', $firm_id)
                ->where('folder_id', $this->get_folder_info($filepart))
                ->where('name', 'Letter')
                ->first();
            if ($found_type) {
                // dd($found_type);
                $default_entrytype['name'] = $found_type->name;
                $default_entrytype['id'] = $found_type->id;
                $default_entrytype['disable_types'] = false;
            }
        }

        return $default_entrytype;
    }

    // check if a contact is still in a file, and if not, delete the contact_roles entry
    public function checkInFile($contact_id, $file_id)
    {
        $fileAtty = ContactRole::where('file_id', $file_id)->where('is_file_attorney', true)->first();
        $fileClient = ContactRole::where('file_id', $file_id)->where('is_file_client', true)->first();

        $fileAttyContactId = $fileAtty?->contact_id;
        $fileClientContactId = $fileClient?->contact_id;

        if ((int) $contact_id !== (int) $fileAttyContactId && (int) $contact_id !== (int) $fileClientContactId) {
            $exists_from = Entry::where('file_id', $file_id)->where('from_contact_id', $contact_id)->exists();
            $exists_to = Entry::where('file_id', $file_id)->where('to_contact_id', $contact_id)->exists();

            if ($exists_from === false && $exists_to === false) {
                $contactRole = ContactRole::where('file_id', $file_id)
                    ->where('contact_id', $contact_id)
                    ->first();
                if ($contactRole) {
                    $contactRole->delete();
                }
            }
        } // endIf ! file atty or client
    }

    // Get all contacts for a file, only if full refresh
    public function getFileContacts($file_id, $firmId, $refresh = 'full')
    {
        if ($refresh === 'full') {
            $from_contacts = DB::table('entries')->select('from_contact_id')->where('file_id', $file_id)->where('firm_id', $firmId)->distinct();       // get all "from" contact ids for this firm's entries on the file (firm filter is reserved-file safety; no-op for normal files)
            $to_contacts = DB::table('entries')->select('to_contact_id')->where('file_id', $file_id)->where('firm_id', $firmId)->distinct();           // get all "to" contact ids for this firm's entries on the file

            $file_contacts = DB::table('contacts')->select('id', 'display_last_first', 'faux_deleted')
                ->where(function ($query) use ($from_contacts, $to_contacts) {
                    $query->whereIn('id', $from_contacts)
                        ->orWhereIn('id', $to_contacts);
                })
                ->get();
        } else {
            $file_contacts = [];
        }

        return $file_contacts;
    }

    // Get all file types for a firm, only if full refresh
    public function getFileTypes($thefirmid, $refresh = 'full')
    {
        if ($refresh === 'full') {
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
    public function getExpectingResponse($file_id, $firmId)
    {
        $expecting_entries = Entry::query()
            ->select('id', 'date1', 'from_contact_id', 'folder_id', 'entrytype_id', 'file_id')
            ->with('contact_from:id,display_last_first')                                                        // include from contact name
            ->where('file_id', $file_id)                                                                // viewing a file, so filter on case_id
            ->where('expecting_response', true)
            ->where('firm_id', $firmId)                                                                  // reserved-file safety: only this firm's entries (no-op for normal files)
            ->orderBy('file_id')
            ->orderBy('date1')
            ->get();

        return $expecting_entries;
    }

    public function getFileContactRoles($file_id, $firmId, $refresh = 'full')
    {
        if ($refresh === 'full' || $refresh === 'ContactRoles') {
            return ContactRole::where('file_id', $file_id)
                ->whereHas('contact', fn ($q) => $q->where('firm_id', $firmId))    // reserved-file safety: only roles whose contact is in the caller's firm (no-op for normal files)
                ->with(['contact:id,display_last_first'])
                ->get()
                ->map(fn ($cr) => [
                    'id' => $cr->id,
                    'contact_id' => $cr->contact_id,
                    'contact_name' => $cr->contact?->display_last_first ?? '',
                    'role_name' => $cr->role_label ?? (ContactRole::ROLE_LABELS[$cr->role] ?? $cr->role),
                    'role' => $cr->role,
                    'is_file_attorney' => $cr->is_file_attorney,
                    'is_file_client' => $cr->is_file_client,
                ]);
        }

        return [];
    }

    public function getContactRoleIds($file_id, $firmId)
    {
        return ContactRole::where('file_id', $file_id)
            ->whereHas('contact', fn ($q) => $q->where('firm_id', $firmId))    // reserved-file safety: only roles whose contact is in the caller's firm (no-op for normal files)
            ->pluck('contact_id')->unique()->values()->toArray();
    }

    private function cleanupContactRole($file_id, $contact_id)
    {
        // Don't remove protected, file attorney, or file client roles
        $contactRoles = ContactRole::where('file_id', $file_id)
            ->where('contact_id', $contact_id)
            ->where('is_file_attorney', false)
            ->where('is_file_client', false)
            ->get();

        if ($contactRoles->isEmpty()) {
            return;
        }

        // Check if any other entries in this file reference this contact
        $otherEntries = Entry::where('file_id', $file_id)
            ->where(function ($q) use ($contact_id) {
                $q->where('from_contact_id', $contact_id)
                    ->orWhere('to_contact_id', $contact_id);
            })
            ->exists();

        if (! $otherEntries) {
            foreach ($contactRoles as $contactRole) {
                $contactRole->delete();
            }
        }
    }

    public function add_new_entrytype(Request $request): JsonResponse
    {
        $request->validate(
            ['name' => 'required|string|max:255',
                'folder_id' => 'required|numeric|integer|exists:folders,id',
            ],
            [
                'folder_id' => 'Invalid folder identification',
            ]);

        $entrytype = $this->lookups->findOrRestoreEntrytype($request->user()->firm_id, (int) $request->folder_id, trim($request->name));

        return response()->json($entrytype->only('id', 'folder_id', 'name', 'faux_deleted'));
    }
}
