<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreViewRequest;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Entry;
// use App\Models\File;
use App\Services\EntryResponseService;
use App\Services\FirmLookupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ViewController extends Controller
{
    public function __construct(
        private EntryResponseService $responses,
        private FirmLookupService $lookups,
    ) {}

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

        $show = min((int) $request->query('show', 15) ?: 15, 50);                               // how many rows to show, else 15, capped at 50

        $view = $request->query('view') ?? 'memos';                                             // which view, else 'memos'
        $view_for = $request->query('view_for') ?? $user->contact->member_initials;             // view_for initials, else authenticated user initials

        if ($view_for !== '****' && ! $this->getContactFor($view_for, $firm_id)) {
            $view_for = '****';                                                                 // unmatched initials - fall back to showing everyone
        }

        $from_to = $request->query('from_to') ?? 'to';                                          // from or to, else 'to'
        $read = $request->query('read') ?? 'unread';                                            // read, unread or both, else 'unread'

        $starting = $request->query('starting') ?? null;
        $ending = $request->query('ending') ?? null;

        // create the entries query and also receive the $viewfolder_id and the $orderBy
        [$entries, $viewfolder_id, $orderBy] = $this->setEntriesQuery($firm_id, $view, $view_for, $from_to, $read, $starting, $ending);

        // now, exuecute the query and then render the View
        $entries = $entries
            ->with('file:id,name,date_closed')
            ->with('contact_from:id,display_last_first,member_initials')
            ->with('contact_to:id,display_last_first,member_initials')
            ->with(['response' => ['response_to'],
                'responses_received' => function ($query) {
                    $query->orderBy('response_type', 'asc')
                        ->orderBy('response_date', 'asc');
                },
            ])
            ->orderBy($orderBy)
            ->paginate($show)
            ->withQueryString();

        return Inertia::render('Views/Index',
            ['view' => $view,
                'entries' => $entries,
                'view_folder_id' => $viewfolder_id,
                'initials' => $view_for,
                'from_to' => $from_to,
                'read' => $read,
                'folders' => $refresh === 'full' ? $this->lookups->folders($firm_id) : [],
                'firm_members' => $refresh === 'full' ? $this->lookups->firmMembers($firm_id) : [],
                'attorneys' => $refresh === 'full' ? $this->lookups->attorneys($firm_id) : [],
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
        // NOT USED, handled in EntryForm
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreViewRequest $request)
    {   // dd($request);

        $entry = new Entry;

        $entry->firm_id = $request->user()->firm_id;                                                    // user's firm_id
        $entry->file_id = $request->file_id;                                                            // the file id
        $entry->folder_id = $request->folder_id;                                                        // the folder id
        $entry->entrytype_id = $request->entrytype_id;                                                  // the entrytype id

        $entry->date1 = $request->date1;                                                                // date1
        $entry->date2 = empty($request->date2) ? null : $request->date2;                                // date2 - if empty, use null

        $entry->from_contact_id = $request->from_contact_id;                                            // from_contact_id

        if ($entry->folder_id === 6) {                                                                 // if this is an event folder entry
            $entry->to_contact_id = $entry->from_contact_id;                                            // - copy from_contact_id to the to_contact_id

            $entry->date_response_expected = $entry->date1;                                             // - copy the date into date_response_expected
            $entry->on_calendar = true;                                                                 // - mark it as on_calendar
            $entry->all_day = $request->boolean('all_day');                                             // - set all_day from request
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

        $respondsToId = $request->filled('is_response_to') ? (int) $request->is_response_to : null;

        DB::transaction(function () use ($entry, $request, $respondsToId) {
            $entry->save();                                                                             // save the entry

            if ($entry->file_id !== (int) config('documents.reserved_file_id')) {
                $this->responses->savePendingContactRoles($entry->file_id, $request->pending_contact_roles);
            }

            $this->responses->sync($entry, $request->is_a_response, $respondsToId, (string) $entry->date1);
        });

        // /return redirect('/files/' . $entry->file_id . '/entries?page=' . $request->current_page . '&show=' . $request->show . '&filepart=' . $request->filepart);

        // don't need to return from adding to a view, because onSuccess does a refresh
        // return redirect('/views?view=' . $request->view);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Not Used
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // Not used
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreViewRequest $request, Entry $view)
    {
        // Authorization (the caller's firm owns the entry) is enforced upstream by
        // StoreViewRequest::authorize() and the Entry firm global scope on route binding.
        $entry = $view;

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
            $entry->all_day = $request->boolean('all_day');                                             // - set all_day from request
        } else {                                                                                        // ELSE, this is not an events folder entry
            $entry->to_contact_id = empty($request->to_contact_id) ? null : $request->to_contact_id;    // to_contact_id - if empty, use null
            // NOTE: change later to put FILE for empty to contact (To: FILE) - actually, maybe use a system id

            $entry->date_response_expected = $request->date_response_expected;

            $entry->on_calendar = false;
            $entry->all_day = false;
        }

        $entry->note = $request->note;                                                                  // the note

        if (isset($request->amount)) {                                                                 // if there's an amount
            $entry->amount = empty($request->amount) ? null : $request->amount;                         // the amount - or null if empty
        }

        $respondsToId = $request->filled('is_response_to') ? (int) $request->is_response_to : null;

        DB::transaction(function () use ($entry, $request, $respondsToId) {
            $entry->save();                                                                             // save the entry

            if ($entry->folder_id !== 6) {
                $this->responses->refreshExpecting($entry->id);
            }

            if ($entry->file_id !== (int) config('documents.reserved_file_id')) {
                $this->responses->savePendingContactRoles($entry->file_id, $request->pending_contact_roles);
            }

            $this->responses->sync($entry, $request->is_a_response, $respondsToId, (string) $entry->date1);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
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

    /**
     * Build the view-specific portion of the entries query.
     *
     * @return array{0: Builder, 1: int, 2: string}
     */
    public function setEntriesQuery(int $firm_id, string $view, string $view_for, string $from_to, string $read, ?string $starting, ?string $ending): array
    {
        $entries = Entry::query()->where('firm_id', $firm_id);
        $orderBy = 'date1';
        $viewfolder_id = 0;

        if ($view === 'memos') {                                            // Memos query
            $viewfolder_id = 5;
            $entries->where('folder_id', 5);

            if ($view_for !== '****') {
                $for = $this->getContactFor($view_for, $firm_id);
                $this->applyFromToFilter($entries, $from_to, $for->id);
            }

            $this->applyReadFilter($entries, $read);
        } elseif ($view === 'phone') {                                      // Phone message query
            $viewfolder_id = 8;
            $entries->where('folder_id', 8);

            if ($view_for !== '****') {
                $for = $this->getContactFor($view_for, $firm_id);
                $entries->where('to_contact_id', $for->id);
            }

            $this->applyReadFilter($entries, $read);
        } elseif ($view === 'todo') {                                       // Todo query
            $viewfolder_id = 7;
            $entries->where('folder_id', 7);

            if ($view_for !== '****') {
                $for = $this->getContactFor($view_for, $firm_id);
                $entries->where('from_contact_id', $for->id);
            }

            $this->applyReadFilter($entries, $read);
        } elseif ($view === 'events') {                                     // Events query
            $viewfolder_id = 6;
            $entries->where('folder_id', 6);

            if ($view_for !== '****') {
                $for = $this->getContactFor($view_for, $firm_id);
                $entries->where('from_contact_id', $for->id);
            }

            $this->applyDateRangeFilter($entries, $starting, $ending);
        } elseif ($view === 'timeline') {                                   // Office Timeline query
            $viewfolder_id = 0;

            if ($view_for !== '****') {
                $for = $this->getContactFor($view_for, $firm_id);
                $this->applyFromToFilter($entries, $from_to, $for->id);
            }

            $this->applyDateRangeFilter($entries, $starting, $ending);
        } elseif ($view === 'due') {                                        // Due View query
            $viewfolder_id = 1;
            $entries->where('expecting_response', true);
            $orderBy = 'date_response_expected';

            if ($view_for !== '****') {
                $for = $this->getContactFor($view_for, $firm_id);

                if ($from_to === 'to') {
                    $entries->where('from_contact_id', $for->id);
                } elseif ($from_to === 'from') {
                    $entries->where('to_contact_id', $for->id);
                } elseif ($from_to === 'both') {
                    $entries->where(function ($query) use ($for) {
                        $query->where('to_contact_id', $for->id)
                            ->orWhere('from_contact_id', $for->id);
                    });
                }
            }
        }

        return [$entries, $viewfolder_id, $orderBy];
    }

    /**
     * Apply from/to/both contact filter to the query.
     */
    private function applyFromToFilter(Builder $entries, string $from_to, int $contact_id): void
    {
        if ($from_to === 'to') {
            $entries->where('to_contact_id', $contact_id);
        } elseif ($from_to === 'from') {
            $entries->where('from_contact_id', $contact_id);
        } elseif ($from_to === 'both') {
            $entries->where(function ($query) use ($contact_id) {
                $query->where('to_contact_id', $contact_id)
                    ->orWhere('from_contact_id', $contact_id);
            });
        }
    }

    /**
     * Apply read/unread filter to the query.
     */
    private function applyReadFilter(Builder $entries, string $read): void
    {
        if ($read === 'unread') {
            $entries->whereNull('date2');
        } elseif ($read === 'read') {
            $entries->whereNotNull('date2');
        }
    }

    /**
     * Apply starting/ending date range filter to the query.
     */
    private function applyDateRangeFilter(Builder $entries, ?string $starting, ?string $ending): void
    {
        if ($starting !== null) {
            $entries->where('date1', '>=', $starting);
        }

        if ($ending !== null) {
            $entries->where('date1', '<=', $ending.' 23:59:00');
        }
    }

    public function getContactFor($view_for, $firm_id)
    {
        $theContact = Contact::select('id')                                                    // get the id
            ->where('firm_id', $firm_id)
            ->where('member_initials', $view_for)
            ->first();

        return $theContact;
    }
}
