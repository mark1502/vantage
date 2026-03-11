<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreViewRequest;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Entry;
use App\Models\File;
use App\Models\Folder;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ViewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // dd($request);

        // if( $request->header('X-Custom-Refresh') ) dd($request);

        $user = Auth::user();
        $firm_id = $user->firm_id;                                                              // the firm_id

        $refresh = $request->header('X-Custom-Refresh') ?? 'full';                              // if refresh flag is set in the header, otherwise full

        $show = $request->query('show') ?? 15;                                                  // how many rows to show, else 15

        $view = $request->query('view') ?? 'memos';                                             // which view, else 'memos'
        $view_for = $request->query('view_for') ?? $user->contact->member_initials;             // view_for initials, else authenticated user initials
        // dd($view_for);
        $from_to = $request->query('from_to') ?? 'to';                                          // from or to, else 'to'
        $read = $request->query('read') ?? 'unread';                                            // read, unread or both, else 'unread'

        $starting = $request->query('starting') ?? null;
        $ending = $request->query('ending') ?? null;

        $entries = Entry::query();                                                              // start the entries query

        $entries->where('firm_id', $firm_id);                                                   // All views - only include entries where firm_id matches the user firm_id

        if ($view === 'memos') {                                                               // if view is memos
            $viewfolder_id = 5;
            $entries->where('folder_id', 5);                                                  // entries where folder_id === 5 (memos)

            if ($view_for !== '****') {                                                        // if view is for a specific user ( not **** )
                $for = $this->getContactFor($view_for, $firm_id);                             // get the id

                if ($from_to === 'to') {                                                       // if showing entries to
                    $entries->where('to_contact_id', $for->id);                               // then where to_contact_id === $for->id
                } elseif ($from_to === 'from') {                                              // else if showing entries from
                    $entries->where('from_contact_id', $for->id);                             // then where from_contact_id === $for->id
                } elseif ($from_to === 'both') {                                              // else if both (from or to)
                    $entries->where(function ($query) use ($for) {
                        $query->where('to_contact_id', $for->id)
                            ->orWhere('from_contact_id', $for->id);
                    });
                }
            }

            if ($read === 'unread') {
                $entries->whereNull('date2');
            }                            // if 'unread', filter where date2 === null
            elseif ($read === 'read') {
                $entries->whereNotNull('date2');
            }                      // else if 'read', filter where date2 === !null
            // else if 'both' then don't filter on date2

            $entries = $entries                                                                 // because this is a "view", include the file name and the contacts from & to
                ->with('file:id,name')
                ->with('contact_from:id,display_last_first')
                ->with('contact_to:id,display_last_first')
                ->with(['response' => ['response_to'],                                        // the response entry showing what this is responsive to (includes contact name for the view)
                    'responses_received' => function ($query) {                            // the response entries showing entries responding to this entry
                        $query->orderBy('response_type', 'asc')
                            ->orderBy('response_date', 'asc');
                    },
                ])
                ->orderBy('date1')
                ->paginate($show)                                                             // paginate with show value
                ->withQueryString();

        } elseif ($view === 'phone') {
            $viewfolder_id = 8;
            $entries->where('folder_id', 8);                                                  // entries where folder_id === 8 (phone)

            if ($view_for !== '****') {                                                        // if view is for a specific user ( not **** )
                $for = $this->getContactFor($view_for, $firm_id);                             // get the id

                $entries->where('to_contact_id', $for->id);                                   // if showing to, then where to_contact_id === $for->id
            }

            if ($read === 'unread') {
                $entries->whereNull('date2');
            }                            // if 'unread', filter where date2 = null
            elseif ($read === 'read') {
                $entries->whereNotNull('date2');
            }                      // else if 'read', filter where date2 = !null

            $entries = $entries                                                                 // because this is a "view", include the file name and the contacts from & to
                ->with('file:id,name')
                ->with('contact_from:id,display_last_first')
                ->with('contact_to:id,display_last_first')
                ->with(['response' => ['response_to'],                                        // the response entry showing what this is responsive to (includes contact name for the view)
                    'responses_received' => function ($query) {                            // the response entries showing entries responding to this entry
                        $query->orderBy('response_type', 'asc')
                            ->orderBy('response_date', 'asc');
                    },
                ])
                ->orderBy('date1')
                ->paginate($show)
                ->withQueryString();

        } elseif ($view === 'todo') {
            $viewfolder_id = 7;
            $entries->where('folder_id', 7);                                                  // entries where folder_id === 7 (todo)

            if ($view_for !== '****') {                                                        // if view is for a specific user ( not **** )
                $for = $this->getContactFor($view_for, $firm_id);                             // get the id

                $entries->where('from_contact_id', $for->id);                                   // if showing to, then where to_contact_id === $for->id
            }

            if ($read === 'unread') {
                $entries->whereNull('date2');
            }                            // if 'unread', filter where date2 = null
            elseif ($read === 'read') {
                $entries->whereNotNull('date2');
            }                      // else if 'read', filter where date2 = !null

            $entries = $entries                                                                 // because this is a "view", include the file name and the contacts from & to
                ->with('file:id,name')
                ->with('contact_from:id,display_last_first,member_initials')
                ->with('contact_to:id,display_last_first')
                ->with(['response' => ['response_to'],                                        // the response entry showing what this is responsive to (includes contact name for the view)
                    'responses_received' => function ($query) {                            // the response entries showing entries responding to this entry
                        $query->orderBy('response_type', 'asc')
                            ->orderBy('response_date', 'asc');
                    },
                ])
                ->orderBy('date1')
                ->paginate($show)
                ->withQueryString();
        } elseif ($view === 'events') {
            $viewfolder_id = 6;
            $entries->where('folder_id', 6);                                                  // entries where folder_id === 6 (events)

            if ($view_for !== '****') {                                                        // if view is for a specific user ( not **** )
                $for = $this->getContactFor($view_for, $firm_id);                             // get the id

                $entries->where('from_contact_id', $for->id);                                   // if showing to, then where to_contact_id === $for->id
            }

            if ($starting !== null) {
                $entries->where('date1', '>=', $starting);
            }

            if ($ending !== null) {
                $ending = $ending.' 23:59:00';
                $entries->where('date1', '<=', $ending);
            }
            // if( $read === 'unread' ) $entries->whereNull( 'date2' );                            // if 'unread', filter where date2 = null
            // else if( $read === 'read' ) $entries->whereNotNull( 'date2' );                      // else if 'read', filter where date2 = !null

            $entries = $entries                                                                 // because this is a "view", include the file name and the contacts from & to
                ->with('file:id,name')
                ->with('contact_from:id,display_last_first,member_initials')
                ->with('contact_to:id,display_last_first,member_initials')
            // do I need responses for events?
                ->with(['response' => ['response_to'],                                        // the response entry showing what this is responsive to (includes contact name for the view)
                    'responses_received' => function ($query) {                            // the response entries showing entries responding to this entry
                        $query->orderBy('response_type', 'asc')
                            ->orderBy('response_date', 'asc');
                    },
                ])
                ->orderBy('date1')
                ->paginate($show)
                ->withQueryString();
        } elseif ($view === 'timeline') {
            if ($view_for !== '****') {                                                        // if view is for a specific user ( not **** )
                $for = $this->getContactFor($view_for, $firm_id);                             // get the id

                // $entries->where( 'from_contact_id', $for->id );                                   // if showing to, then where to_contact_id === $for->id
                if ($from_to === 'to') {                                                       // if showing entries to
                    $entries->where('to_contact_id', $for->id);                               // then where to_contact_id === $for->id
                } elseif ($from_to === 'from') {                                              // else if showing entries from
                    $entries->where('from_contact_id', $for->id);                             // then where from_contact_id === $for->id
                } elseif ($from_to === 'both') {                                              // else if both (from or to)
                    $entries->where(function ($query) use ($for) {
                        $query->where('to_contact_id', $for->id)
                            ->orWhere('from_contact_id', $for->id);
                    });
                }

            }

            if ($starting !== null) {
                $entries->where('date1', '>=', $starting);
            }

            if ($ending !== null) {
                $ending = $ending.' 23:59:00';
                $entries->where('date1', '<=', $ending);
            }
            // if( $read === 'unread' ) $entries->whereNull( 'date2' );                            // if 'unread', filter where date2 = null
            // else if( $read === 'read' ) $entries->whereNotNull( 'date2' );                      // else if 'read', filter where date2 = !null

            $viewfolder_id = 0;  // NOTE: folder 0 for timeline view!

            $entries = $entries                                                                 // because this is a "view", include the file name and the contacts from & to
                ->with('file:id,name')
                ->with('contact_from:id,display_last_first,member_initials')
                ->with('contact_to:id,display_last_first,member_initials')
                ->with(['response' => ['response_to'],                                        // the response entry showing what this is responsive to (includes contact name for the view)
                    'responses_received' => function ($query) {                            // the response entries showing entries responding to this entry
                        $query->orderBy('response_type', 'asc')
                            ->orderBy('response_date', 'asc');
                    },
                ])
                ->orderBy('date1')
                ->paginate($show)
                ->withQueryString();
        } elseif ($view === 'due') {
            $entries->where('expecting_response', true);                                        // get entries expecting a response

            if ($view_for !== '****') {                                                        // if view is for a specific user ( not **** )
                $for = $this->getContactFor($view_for, $firm_id);                             // get the id

                if ($from_to === 'to') {                                                       // if showing entries expected to
                    $entries->where('from_contact_id', $for->id);                             // then where from_contact_id === $for->id
                } elseif ($from_to === 'from') {                                              // else if showing entries expected from
                    $entries->where('to_contact_id', $for->id);                               // then where to_contact_id === $for->id
                } elseif ($from_to === 'both') {                                              // else if both (from or to)
                    $entries->where(function ($query) use ($for) {
                        $query->where('to_contact_id', $for->id)
                            ->orWhere('from_contact_id', $for->id);
                    });
                }
            }

            $viewfolder_id = 1;                                                                 // Note: viewfolder_id doesn't matter for due view, but set this anyhow

            $entries = $entries                                                                 // because this is a "view", include the file name and the contacts from & to
                ->with('file:id,name')
                ->with('contact_from:id,display_last_first,member_initials')
                ->with('contact_to:id,display_last_first,member_initials')
                ->with(['response' => ['response_to'],                                        // the response entry showing what this is responsive to (includes contact name for the view)
                    'responses_received' => function ($query) {                              // the response entries showing entries responding to this entry
                        $query->orderBy('response_type', 'asc')
                            ->orderBy('response_date', 'asc');
                    },
                ])
                ->orderBy('date_response_expected')                                           // order by the date a response is expected
                ->paginate($show)
                ->withQueryString();
        } // End if $view

        return Inertia::render('Views/Index',
            ['view' => $view,
                'entries' => $entries,
                'view_folder_id' => $viewfolder_id,
                'initials' => $view_for,
                'from_to' => $from_to,
                'read' => $read,
                'folders' => $this->getFirmFolders($firm_id, $refresh),
                'firm_members' => $this->getFirmMembers($firm_id, $refresh),
                'attorneys' => $this->getAttorneys($firm_id, $refresh),
                'file_contacts' => $this->getFileContacts_fake($user->id, $refresh), // send a fake array to avoid problem in EntryForm.vue (where contact lookup is done on the client)
                'expecting_response' => $this->getExpectingResponse(),
                'contact_role_ids' => [],
                'role_options' => ContactRole::ROLE_LABELS,
            ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreViewRequest $request)
    {   // dd($request);

        $entry = new Entry;

        $entry->firm_id = $request->user()->firm_id;                                                    // user's firm_id
        $entry->file_id = $request->file_id;                                                    // the file id
        $entry->folder_id = $request->folder_id;                                                        // the folder id
        $entry->entrytype_id = $request->entrytype_id;                                                  // the entrytype id

        $entry->date1 = $request->date1;                                                                // date1
        $entry->date2 = empty($request->date2) ? null : $request->date2;                                // date2 - if empty, use null

        $entry->from_contact_id = $request->from_contact_id;                                            // from_contact_id

        if ($entry->folder_id === 6) {                                                                 // if this is an event folder entry
            $entry->to_contact_id = $entry->from_contact_id;                                            // - copy from_contact_id to the to_contact_id

            $entry->date_response_expected = $entry->date1;                                             // - copy the date into date_response_expected
            $entry->on_calendar = true;                                                                 // - mark it as on_calendar
        } else {                                                                                        // ELSE, this is not an events folder entry
            $entry->to_contact_id = empty($request->to_contact_id) ? null : $request->to_contact_id;    // to_contact_id - if empty, use null
            // NOTE: change later to put FILE for empty to contact (To: FILE) - actually, maybe use a system id

            $entry->date_response_expected = $request->date_response_expected;
            if (! empty($request->date_response_expected)) {
                $entry->expecting_response = true;
            }         // if date_response_expected, expecting_response is true

            $entry->on_calendar = false;
            $entry->all_day = false;
        }

        $entry->note = $request->note;                                                                  // the note

        if (isset($request->amount)) {                                                                 // if there's an amount
            $entry->amount = empty($request->amount) ? null : $request->amount;                         // the amount - or null if empty
        }

        $entry->save();                                                                                 // save the entry

        // Handle pending contact roles
        $this->savePendingContactRoles($request, $entry->file_id);

        // if this is a response to another entry, handle it
        if ($request->is_a_response != 'N' && ! empty($request->is_response_to)) {
            $this->handleThisResponse($request->is_a_response, $entry->id, $request->is_response_to, $entry->date1, $create = true);
        }

        // /return redirect('/files/' . $entry->file_id . '/entries?page=' . $request->current_page . '&show=' . $request->show . '&filepart=' . $request->filepart);

        // don't need to return from adding to a view, because onSuccess does a refresh
        // return redirect('/views?view=' . $request->view);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreViewRequest $request, Entry $entry)
    {
        if ($entry->firm_id == $request->user()->firm_id) {                                                // if user firm_id matches the entry firm_id
            // $entry->file_id = $request->file_id;                                                 // the file id (not on update)
            // $entry->folder_id = $request->folder_id;                                                     // the folder id (not on update)
            $entry->entrytype_id = $request->entrytype_id;                                                  // the entrytype id

            $entry->date1 = $request->date1;                                                                // date1
            $entry->date2 = empty($request->date2) ? null : $request->date2;                                // date2 - if empty, use null

            $entry->from_contact_id = $request->from_contact_id;                                            // from_contact_id

            if ($entry->folder_id === 6) {                                                                 // if this is an event folder entry
                $entry->to_contact_id = $entry->from_contact_id;                                            // - copy from_contact_id to the to_contact_id

                $entry->date_response_expected = $entry->date1;                                             // - copy the date into date_response_expected
                $entry->on_calendar = true;                                                                 // - mark it as on_calendar
            } else {                                                                                        // ELSE, this is not an events folder entry
                $entry->to_contact_id = empty($request->to_contact_id) ? null : $request->to_contact_id;    // to_contact_id - if empty, use null
                // NOTE: change later to put FILE for empty to contact (To: FILE) - actually, maybe use a system id

                $entry->date_response_expected = $request->date_response_expected;
                if (! empty($request->date_response_expected)) {
                    $entry->expecting_response = true;
                }         // if date_response_expected, expecting_response is true

                $entry->on_calendar = false;
                $entry->all_day = false;
            }

            $entry->note = $request->note;                                                                  // the note

            if (isset($request->amount)) {                                                                 // if there's an amount
                $entry->amount = empty($request->amount) ? null : $request->amount;                         // the amount - or null if empty
            }

            $entry->save();                                                                                 // save the entry

            // Handle pending contact roles
            $this->savePendingContactRoles($request, $entry->file_id);

            if ($request->is_a_response === 'N') {
                $this->handleIsNoResponse($entry->id);
            } elseif ($request->is_a_response !== 'N' && ! empty($request->is_response_to)) {
                $this->handleThisResponse($request->is_a_response, $entry->id, $request->is_response_to, $entry->date1, $create = true);
            }
        } // end if entry_>firm_id === $request->user()->firm_id
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function savePendingContactRoles(Request $request, $file_id)
    {
        if (! empty($request->pending_contact_roles) && is_array($request->pending_contact_roles)) {
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

    public function getFirmFolders($thefirmid, $refresh = 'full')
    {
        $folders = [];
        if ($refresh === 'full') {
            $folders = Folder::query()
                ->where('id', '>', '0')                        // get all the folders with their entrytypes
                ->with(['entrytypes' => function ($query) use ($thefirmid) {
                    $query->where('firm_id', $thefirmid)->orderBy('name');
                }])
                ->get();
        }

        return $folders;
    }

    public function getFirmMembers($thefirmid, $refresh = 'full')
    {
        $firm_members = [];
        if ($refresh === 'full') {                                    // if not reloading just the entries, then do this query
            $firm_members = Contact::query()
                ->select('display_last_first', 'id', 'account_status', 'firm_role', 'member_initials')
                ->where('firm_id', $thefirmid)
                ->where('is_firm_member', true)
                ->where('account_status', 'A')
                ->orderBy('display_last_first')
                ->get();
        }

        return $firm_members;
    }

    public function getAttorneys($thefirmid, $refresh = 'full')
    {
        $attorneys = [];
        if ($refresh === 'full') {
            $attorneys = Contact::query()
                ->select('display_last_first', 'id', 'account_status', 'firm_role', 'member_initials')
                ->where('firm_id', $thefirmid)
                ->where('is_firm_member', true)
                ->where('account_status', 'A')
                ->where('firm_role', 'Attorney')
                ->orderBy('display_last_first')
                ->get();
        }

        return $attorneys;
    }

    public function getFileContacts_fake($user_id, $refresh = 'full')
    {
        $file_contacts = [];

        if ($refresh === 'full') {
            $file_contacts = DB::table('contacts')->select('id', 'display_last_first')       // just get the authenticated user for the fake array
                ->where('id', $user_id)
                ->get();
        }

        return $file_contacts;
    }

    public function getExpectingResponse($refresh = 'full')
    {
        $expecting_entries = [];
        if ($refresh === 'full') {
            $expecting_entries = Entry::query()
                ->select('id', 'date1', 'from_contact_id', 'folder_id', 'entrytype_id', 'file_id')
                ->with(['contact_from:id,display_last_first'])
            // ->where('file_id', $file_id)                                         // get for a "view", so don't filter on the file_id
                ->where('expecting_response', true)
                ->orderBy('file_id')
                ->orderBy('date1')
                ->get();
        }

        return $expecting_entries;
    }

    public function getContactFor($view_for, $firm_id)
    {
        $theContact = Contact::select('id')                                                    // get the id
            ->where('firm_id', $firm_id)
            ->where('member_initials', $view_for)
            ->first();

        return $theContact;
    }

    public function handleIsNoResponse($entry_id_in)
    {
        $found_response = Response::where('entry_id', $entry_id_in)->first();       // look for a response from this entry

        if ($found_response) {                                                    // if a response was found

            if ($found_response->response_type === 'F') {                           // if this had been a full response, so update the related entry to again expect a response

                $related_entry = Entry::where('id', $found_response->response_to)->first();

                if ($related_entry && $related_entry->date_response_expected) {    // found the related entry and it was expecting a response by a date
                    $related_entry->expecting_response = true;                      // so, now it is expecting a response again
                    $related_entry->save();
                }
            }

            $found_response->delete();      // now, delete response from this entry, because it is now not a response
        }
    }

    public function handleThisResponse($response_type, $entry_from, $response_to, $response_date, $create = false)
    {
        if ($create === false) {                                                       // if create is false, this is not a new entry - look for a response record
            $found_response = Response::where('entry_id', $entry_from)->first();
        }

        if ($create === true || ($create === false && empty($found_response))) {     // if this is for a new entry being created or an existing one without a found response

            $new_response = new Response;                                               // create a new response for this entry

            $new_response->entry_id = $entry_from;                                      // entry_id that the response is from
            $new_response->response_to = $response_to;                                  // entry_id that the response is to
            $new_response->response_date = $response_date;                              // response date
            $new_response->response_type = $response_type;                              // Full or Partial response

            $new_response->save();
        } elseif ($create == false) {                                                // else if this is an existing entry which has a found response
            if ($found_response->response_to == $response_to) {                        // if found response is for the same related, then update it

                $hold_type = $found_response->response_type;                            // hold the found response type

                $found_response->response_date = $response_date;
                $found_response->response_type = $response_type;                        // Full or Partial response
                $found_response->save();

                if ($hold_type == 'F' && $response_type == 'P') {                      // And, if the prior response was full, but now it's partial, update the related entry to expect a response

                    $related_entry = Entry::where('id', $response_to)->first();

                    if ($related_entry) {
                        $related_entry->expecting_response = true;
                        $related_entry->on_calendar = true;
                        $related_entry->all_day = true;
                        $related_entry->save();
                    }
                } // end if was F, but now P
            } elseif ($found_response->response_to != $response_to) {                 // else if the found response was to a different related entry

                if ($found_response->response_type == 'F') {                            // if the found response was a 'Full Response', reset the prior related entry to again expect a response

                    $prior_entry = Entry::where('id', $found_response->response_to)->first();  // get the prior related entry and reset it to expecting

                    if ($prior_entry && $prior_entry->date_response_expected && $prior_entry->expecting_response === false) {   // prior entry has response date, but not expecting a response
                        $prior_entry->expecting_response = true;
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
        if ($response_type == 'F') {

            $related_entry = Entry::where('id', $response_to)->first();

            if ($related_entry) {
                $related_entry->expecting_response = false;

                if ($related_entry->folder_id != 6) {  // the related entry was not an event, so take it off the calendar
                    $related_entry->on_calendar = false;
                    $related_entry->all_day = false;
                }

                $related_entry->save();
            }
        }

    } // end handleThisResponse function

}
