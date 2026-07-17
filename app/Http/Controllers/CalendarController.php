<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Entry;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // NOTE: the event data is retrieved in the get_events() function below, which is called from the calendar component.
        // This function retrieves and renders the other data used by the calendar

        $firm_id = $request->user()->firm_id;                       // set the firm_id

        $firm_members = Contact::select('id', 'display_last_first', 'member_initials')  // get the active firm members (from contacts)
            ->where('firm_id', $firm_id)
            ->where('is_firm_member', true)
            ->where('account_status', 'A')
            ->orderBy('display_last_first')
            ->get();

        $event_types = Entrytype::select('id', 'name')              // get all event types for the firm
            ->where('firm_id', $firm_id)
            ->where('folder_id', 6)
            ->orderBy('name', 'asc')
            ->get();

        $bg_colors = Preference::where('firm_id', $firm_id)         // get the bg event colors for the firm
            ->where('name', 'event_bg')
            ->get();

        $text_colors = Preference::where('firm_id', $firm_id)       // get the text event colors for the firm
            ->where('name', 'event_text')
            ->get();

        $hover_placement = Preference::where('user_id', $request->user()->id)   // get the user's hover placement preference
            ->where('name', 'event_hover_placement')
            ->value('setting') ?? 'upper_right';

        return Inertia::render('Calendar/Index',
            ['firm_members' => $firm_members,      // render the calendar
                'event_types' => $event_types,
                'bg_colors' => $bg_colors,
                'text_colors' => $text_colors,
                'hover_placement' => $hover_placement,
                'initial_view' => $request->query('user') ? 'timeGridDay' : null,
                'initial_user' => $request->query('user') ? (int) $request->query('user') : null,
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $firmId = $request->user()->firm_id;
        $reservedFileId = config('documents.reserved_file_id');

        $verified = $request->validate(
            ['formtype' => 'string|max:20|nullable',
                'action' => 'string|max:10|nullable',
                'file_id' => ['numeric', 'integer', 'required', Rule::exists('files', 'id')->where(function ($query) use ($firmId, $reservedFileId) {
                    $query->where('firm_id', $firmId)           // the caller's own file, or
                        ->orWhere('id', $reservedFileId);       // the shared reserved (non-file-specific) file
                })],
                'folder_id' => ['numeric', 'integer', 'required', Rule::exists('folders', 'id')],
                'entry_id' => ['numeric', 'integer', 'nullable', Rule::exists('entries', 'id')->where('firm_id', $firmId)],
                'entrytype_id' => ['numeric', 'integer', 'required', Rule::exists('entrytypes', 'id')->where('firm_id', $firmId)],
                'from_contact_id' => ['numeric', 'integer', 'required', Rule::exists('contacts', 'id')->where('firm_id', $firmId)],
                'note' => 'string|max:5000|nullable',
                'all_day' => 'boolean',
                // 'fileSpecific' => 'boolean',
            ],
            [
                'file_id' => 'Related file is not specified',
                'folder_id' => 'Invalid Folder ID',
                'entrytype_id' => 'Event type is required',
                'from_contact_id' => 'This field is required',
            ]);

        if ($request->all_day == false) {     // if not allDay, validate datetime format
            $verifiedDates = $request->validate([
                'date1' => 'date_format:Y-m-d H:i:s|required',
                'date2' => 'date_format:Y-m-d H:i:s|nullable',
            ]);
        } else {                              // else, validate date format
            $verifiedDates = $request->validate([
                'date1' => 'date_format:Y-m-d|required',
                'date2' => 'date_format:Y-m-d|nullable',
            ]);
        }

        if ($request->action === 'add') {
            $event = new Entry;
            $event->firm_id = $request->user()->firm_id;    // user's firm_id
            $event->file_id = $request->file_id;            // the file id
            $event->folder_id = $request->folder_id;
            $event->entrytype_id = $request->entrytype_id;
            $event->from_contact_id = $request->from_contact_id;
            $event->to_contact_id = $request->from_contact_id;  // copy same contact to both fields
            $event->note = $request->note;

            $event->date1 = $request->date1;
            $event->date2 = $request->date2;

            $event->on_calendar = true;
            $event->all_day = $request->all_day;
            $event->save();

        } elseif ($request->action === 'edit' && $request->entry_id) {
            $event = Entry::findOrFail($request->entry_id);
            $this->authorize('update', $event);             // 403 if the entry belongs to another firm
            $event->file_id = $request->file_id;            // the file id
            $event->entrytype_id = $request->entrytype_id;
            $event->from_contact_id = $request->from_contact_id;
            $event->to_contact_id = $request->from_contact_id;  // copy same contact to both fields
            $event->note = $request->note;

            $event->date1 = $request->date1;
            $event->date2 = $request->date2;

            $event->on_calendar = true;
            $event->all_day = $request->all_day;

            $event->save();

        } elseif ($request->action === 'delete' && $request->entry_id) {
            $event = Entry::findOrFail($request->entry_id);
            $this->authorize('delete', $event);             // 403 if the entry belongs to another firm
            $event->delete();
        }

        // return to_route('calendar.index');
        // return Inertia::render('Calendar/Index');

    } // end function

    public function get_events(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'start' => 'required|date',
            'end' => 'required|date',
            'user1' => 'required|integer|min:1',
            'include_due' => 'nullable|in:true,false,1,0',
            'due_to' => 'nullable|in:true,false,1,0',
            'due_from' => 'nullable|in:true,false,1,0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user_filter = (int) $request->query('user1');          // 1 = everyone, else a firm-member contact id
        $include_due = $request->boolean('include_due');
        $due_to = $request->boolean('due_to');
        $due_from = $request->boolean('due_from');

        $firm_id = $request->user()->firm_id;

        $firm_member_ids = Contact::where('firm_id', $firm_id)   // get a collection of active user ids
            ->where('is_firm_member', true)
            ->where('account_status', 'A')
            ->pluck('id');

        // get all firm color preferences at once
        $all_colors = Preference::select('id', 'user_id', 'firm_id', 'name', 'setting')
            ->where('firm_id', $firm_id)
            ->whereIn('name', ['event_bg', 'event_text'])
            ->get();

        $events = Entry::query()
            ->where('firm_id', $firm_id)
            ->where(function ($q) use ($request, $user_filter, $include_due, $due_to, $due_from) {

                // Calendar events (folder_id = 6): filter by date1 range
                $q->where(function ($q2) use ($request, $user_filter) {
                    $q2->where('folder_id', 6)
                        ->whereBetween('date1', [$request->query('start'), $request->query('end')]);

                    if ($user_filter !== 1) {
                        $q2->where('to_contact_id', $user_filter);
                    }
                });

                // Due dates: conditionally included
                if ($include_due) {
                    $q->orWhere(function ($q2) use ($request, $user_filter, $due_to, $due_from) {
                        $q2->where('expecting_response', true)
                            ->whereBetween('date_response_expected', [$request->query('start'), $request->query('end')]);

                        if ($user_filter !== 1) {
                            $q2->where(function ($q3) use ($user_filter, $due_to, $due_from) {
                                if ($due_to && $due_from) {
                                    $q3->where('from_contact_id', $user_filter)
                                        ->orWhere('to_contact_id', $user_filter);
                                } elseif ($due_to) {
                                    $q3->where('from_contact_id', $user_filter);
                                } elseif ($due_from) {
                                    $q3->where('to_contact_id', $user_filter);
                                }
                            });
                        }
                    });
                }
            })
            ->with([
                'file:id,name,date_closed',
                'contact_to:id,display_last_first,member_initials,user_id',
                'contact_from:id,display_last_first,member_initials,user_id',
                'entrytype:id,name',
            ])
            ->get();

        $events_back = [];

        foreach ($events as $event) {
            $missing = [];
            if ($event->to_contact_id && ! $event->contact_to) {
                $missing[] = 'to-contact';
            }
            if ($event->from_contact_id && ! $event->contact_from) {
                $missing[] = 'from-contact';
            }
            if (! $event->entrytype) {
                $missing[] = 'entrytype';
            }
            if (! $event->file) {
                $missing[] = 'file';
            }
            if ($missing !== []) {
                Log::warning('get_events: entry '.$event->id.' (firm '.$firm_id.') has missing related records: '.implode(', ', $missing));
            }

            if ($event->folder_id == 6) {
                // Calendar event: use to_contact for color and title
                $color_owner_id = $event->contact_to->user_id ?? null;
                $color_bg = $all_colors->where('user_id', $color_owner_id)->where('name', 'event_bg')->first();
                $color_text = $all_colors->where('user_id', $color_owner_id)->where('name', 'event_text')->first();

                $type_name = $event->entrytype?->name ?? '(missing event type)';
                $is_sol = $type_name === 'Deadline - Statute of Limitations';
                $e_title = '('.($event->contact_to?->member_initials ?? '??').') '.$type_name.' - '.$event->note;

                $events_back[] = [
                    'id' => $event->id,
                    'start' => $event->date1,
                    'end' => $event->date2,
                    'title' => $e_title,
                    'allDay' => $event->all_day,
                    'editable' => ! $is_sol,
                    'backgroundColor' => $color_bg->setting ?? '#fff68f',
                    'textColor' => $color_text->setting ?? '#000000',
                    'extendedProps' => [
                        'is_due_date' => false,
                        'is_sol' => $is_sol,
                        'file_id' => $event->file_id,
                        'file_name' => $event->file ? $event->file->name.($event->file->date_closed ? ' (closed)' : '') : '(missing file)',
                        'entrytype_id' => $event->entrytype_id,
                        'event_for' => $event->to_contact_id,
                        'note' => $event->note,
                    ],
                ];
            } else {
                // it's a 'due date'
                // Due date colors: red background with white text for all users
                $type_name = $event->entrytype?->name ?? '(missing event type)';

                if ($user_filter !== 1) {                                           // if the calendar is for a particular user
                    if ($due_to && $due_from) {       // calendar for a user, determine due to or from that user
                        $e_title = $event->from_contact_id == $user_filter ? 'Response to' : 'Response from';
                    } else {                                                                // else, determine based on the filter
                        $e_title = $due_to ? 'Response to' : 'Response from';
                    }

                    $e_title .= ' ('.($event->contact_from?->member_initials ?? '??').') due concerning: '.date('m/d/Y', strtotime($event->date1)).' - '.$type_name.' - '.$event->note;
                } else {                            // else, the calendar is not specific to a user
                    if ($firm_member_ids->contains($event->from_contact_id) && $firm_member_ids->contains($event->to_contact_id)) {  // if firm members are in from and to, just say response due
                        $e_title = 'Response due concerning: '.date('m/d/Y', strtotime($event->date1)).' - '.$type_name.' - '.$event->note;
                    } else {                                                                                                            // else, just 1 firm member so determine if it's from or to
                        $e_title = $firm_member_ids->contains($event->from_contact_id)
                            ? 'Response due to ('.($event->contact_from?->member_initials ?? '??')
                            : 'Response due from ('.($event->contact_to?->member_initials ?? '??');
                        $e_title .= ') concerning: '.date('m/d/Y', strtotime($event->date1)).' - '.$type_name.' - '.$event->note;
                    }
                }

                $events_back[] = [
                    'id' => $event->id,
                    'start' => $event->date_response_expected,
                    'end' => null,
                    'title' => $e_title,
                    'allDay' => true,
                    'editable' => false,
                    'backgroundColor' => '#82181a', // '#9f0712',
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'is_due_date' => true,
                        'file_id' => $event->file_id,
                        'file_name' => $event->file ? $event->file->name.($event->file->date_closed ? ' (closed)' : '') : '(missing file)',
                        'entrytype_id' => $event->entrytype_id,
                        'event_for' => $event->to_contact_id,
                        'note' => $event->note,
                    ],
                ];
            }
        }

        return response()->json($events_back);
    } // end function

    public function event_placement(Request $request)
    {
        $verified = $request->validate(
            ['formtype' => 'string|max:20|nullable',
                'action' => 'string|max:10|nullable',
                'entry_id' => 'numeric|integer|required',
                'all_day' => 'boolean',
            ]);

        if ($request->all_day == false) {     // if not allDay, validate datetime format
            $verifiedDates = $request->validate([
                'date1' => 'date_format:Y-m-d H:i:s|required',
                'date2' => 'date_format:Y-m-d H:i:s|nullable',
            ]);
        } else {                              // else, validate date format
            $verifiedDates = $request->validate([
                'date1' => 'date_format:Y-m-d|required',
                'date2' => 'date_format:Y-m-d|nullable',
            ]);
        }

        $event = Entry::where('id', $request->entry_id)->first();

        if ($event && $event->id == $request->entry_id && $event->firm_id == $request->user()->firm_id) {    // if retrieved the correct event, and it's for the correct law firm, then make the changes
            $event->all_day = $request->all_day;
            $event->date1 = $request->date1;

            if ($request->action == 'move') {
                $event->date2 = $request->date2 != null ? $request->date2 : null;

                if ($request->all_day == false && $event->date2 == null) {    // if not allDay and end time is NULL, then set end time to 1 hour after start time
                    $event->date2 = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($event->date1)));
                }
            } elseif ($request->action == 'resize') {
                $event->date2 = $request->date2;
            }

            $event->save();
        }
    } // end function

    public function lookup_file(Request $request)
    {
        $files_found = File::query()
            ->select('id', 'name')
            ->where('firm_id', $request->user()->firm_id)
            ->where('name', 'like', $request->search.'%')
            ->whereNull('date_closed')  // only open files
            ->orderBy('name')
            ->simplePaginate(8);
        // ->withQueryString();

        return $files_found;
    }

    public function add_new_event_type(Request $request)
    {
        $verified = $request->validate(
            ['name' => 'required|string|max:255',
                'id' => 'nullable|numeric|integer',
                'folder_id' => 'nullable|numeric|integer',
                'isChosen' => 'boolean|nullable',
                'chosen_name' => 'nullable|string|max:255',
                'lookup' => 'boolean|nullable',
            ]);

        if ($request->name != $request->chosen_name) {
            $new_entrytype = new Entrytype;
            $new_entrytype->firm_id = $request->user()->firm_id;
            $new_entrytype->folder_id = 6;  // 6 for Events folder
            $new_entrytype->name = $request->name;
            $new_entrytype->save();
        } else {
            $new_entrytype = Entrytype::where('firm_id', $request->user()->firm_id)
                ->where('folder_id', 6)
                ->where('name', $request->name)
                ->first();
        }

        $event_types = Entrytype::select('id', 'name')
            ->where('firm_id', $request->user()->firm_id)
            ->where('folder_id', 6)
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('Calendar/Index', [
            'event_types' => fn () => $event_types,
            'new_event_type' => $new_entrytype,
        ]);
    }
} // end class
