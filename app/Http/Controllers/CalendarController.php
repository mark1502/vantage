<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Entry;
use App\Models\Entrytype;
use App\Models\File;
use App\Models\Preference;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // NOTE: the event data is retrieved int the get_events() function below, which is called from the calendar component.

        $firm_id = $request->user()->firm_id;

        $firm_members = Contact::select('id', 'display_last_first', 'member_initials')
            ->where('firm_id', $firm_id)
            ->where('is_firm_member', true)
            ->where('account_status', 'A')
            ->orderBy('display_last_first')
            ->get();

        $event_types = Entrytype::select('id', 'name')
            ->where('firm_id', $firm_id)
            ->where('folder_id', 6)
            ->orderBy('name', 'asc')
            ->get();

        $bg_colors = Preference::where('firm_id', $firm_id)
            ->where('name', 'event_bg')
            ->get();

        $text_colors = Preference::where('firm_id', $firm_id)
            ->where('name', 'event_text')
            ->get();

        return Inertia::render('Calendar/Index', ['firm_members' => $firm_members,
            'event_types' => $event_types,
            'bg_colors' => $bg_colors,
            'text_colors' => $text_colors,
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
    public function store(Request $request)
    {

        $verified = $request->validate(
            ['formtype' => 'string|max:20|nullable',
                'action' => 'string|max:10|nullable',
                'file_id' => 'numeric|integer|required',
                'folder_id' => 'numeric|integer|required',
                'entry_id' => 'numeric|integer|nullable',
                'entrytype_id' => 'numeric|integer|required',
                'from_contact_id' => 'numeric|integer|required',
                'note' => 'string|max:5000|nullable',
                'allDay' => 'boolean',
                // 'fileSpecific' => 'boolean',
            ],
            [
                'file_id' => 'Related file is not specified',
                'folder_id' => 'Invalid Folder ID',
                'entrytype_id' => 'Event type is required',
                'from_contact_id' => 'This field is required',
            ]);

        if ($request->allDay == false) {     // if not allDay, validate datetime format
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
            $event->date_response_expected = $request->date1;

            $event->on_calendar = true;
            $event->all_day = $request->allDay;
            $event->save();

        } elseif ($request->action === 'edit' && $request->entry_id) {
            $event = Entry::where('id', $request->entry_id)->first();
            $event->file_id = $request->file_id;            // the file id
            $event->entrytype_id = $request->entrytype_id;
            $event->from_contact_id = $request->from_contact_id;
            $event->to_contact_id = $request->from_contact_id;  // copy same contact to both fields
            $event->note = $request->note;

            $event->date1 = $request->date1;
            $event->date2 = $request->date2;
            $event->date_response_expected = $request->date1;

            $event->on_calendar = true;
            $event->all_day = $request->allDay;

            $event->save();

        } elseif ($request->action === 'delete' && $request->entry_id) {
            $event = Entry::where('id', $request->entry_id)->first();
            $event->delete();
        }

        // return to_route('calendar.index');
        // return Inertia::render('Calendar/Index');

    } // end function

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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function get_events(Request $request)
    {
        $firm_id = $request->user()->firm_id;

        // get all firm event color preferences at once - this collection is used to pull individual member preferences rather than going back to the db again
        $event_colors = Preference::select('id', 'user_id', 'firm_id', 'name', 'setting')->where('firm_id', $firm_id)
            ->where(function ($q) {
                $q->where('name', 'event_bg')
                    ->orWhere('name', 'event_text');
            })
            ->get();

        // Start of building query to retrieve events
        $events = Entry::query()
            ->where('firm_id', $firm_id)
            ->where('on_calendar', true);

        if ($request->user1 != '1') {  // if not for user 1 (****), then filter calendar for the user
            $events = $events->where('to_contact_id', $request->user1);
        }

        if ($request->include_due == 'false') {                                // if not including due dates, then filter them out
            $events = $events->where('folder_id', 6);                         // limit to 'Events' folder only
        }
        if ($request->include_due == 'true') {                                // if including due dates
            $events = $events->where('folder_id', 6);                         // events folder entries
            $events = $events->orWhere('expecting_response', '=', 1);         // or, entries expecting a response
        }

        $events = $events->whereBetween('date_response_expected', [$request->start, $request->end])
            ->with(['file:id,name',
                'contact_to:id,display_last_first,member_initials,user_id',
                'entrytype:id,name',
                // 'contact_from:id,display_last_first,member_initials',
                // 'folder:id,name,input_time,hide_date2_prompt,hide_to_prompt,hide_amount_prompt',
            ])
            ->get();
        // End of query

        $events_back = [];

        if ($events) {
            foreach ($events as $event) {      // for each event, get the user's event colors from the collection
                $color_bg = $event_colors->where('user_id', $event->contact_to->user_id)->where('name', 'event_bg')->first();
                $color_text = $event_colors->where('user_id', $event->contact_to->user_id)->where('name', 'event_text')->first();

                if ($event->folder_id == 6) {                                  // if folder 6, so not a due date
                    $e_title = '('.$event->contact_to->member_initials.') '.$event->entrytype->name.' - '.$event->note;
                } else {                                                        // else, it is a response due
                    $e_title = 'Response Due: '.$event->entrytype->name.' - '.$event->note;
                }

                $events_back[] = [
                    'id' => $event->id,
                    'start' => $event->date_response_expected,
                    'end' => $event->date2,
                    'title' => $e_title,
                    'allDay' => $event->all_day,
                    'backgroundColor' => $color_bg->setting ?? '#fff68f',
                    'textColor' => $color_text->setting ?? '#000000',
                    'extendedProps' => [
                        'file_id' => $event->file->id,
                        'file_name' => $event->file->name,
                        'entrytype_id' => $event->entrytype->id,
                        'event_for' => $event->contact_to->id,
                        'note' => $event->note,
                        // 'description' => '<div>This here is the tooltip text</div>\\n\\nHere is the next line',
                    ],
                ];
            } // end foreach
        } // end if

        return response()->json($events_back);
    } // end function

    public function move_event(Request $request)
    {
        $verified = $request->validate(
            ['formtype' => 'string|max:20|nullable',
                'action' => 'string|max:10|nullable',
                'entry_id' => 'numeric|integer|required',
                'allDay' => 'boolean',
            ]);

        if ($request->allDay == false) {     // if not allDay, validate datetime format
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

        if ($event && $event->id == $request->entry_id && $event->firm_id == $request->user()->firm_id) {
            $event->all_day = $request->allDay;
            $event->date1 = $request->date1;
            $event->date_response_expected = $request->date1;
            $event->date2 = $request->date2 != null ? $request->date2 : null;

            if ($request->allDay == false && $event->date2 == null) {    // if not an allday event and there's no end time, set the end to 1 hr later than the start
                $event->date2 = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($event->date1)));
            }

            $event->save();
        }

    }

    public function event_placement(Request $request)
    {
        $verified = $request->validate(
            ['formtype' => 'string|max:20|nullable',
                'action' => 'string|max:10|nullable',
                'entry_id' => 'numeric|integer|required',
                'allDay' => 'boolean',
            ]);

        if ($request->allDay == false) {     // if not allDay, validate datetime format
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
            $event->all_day = $request->allDay;
            $event->date1 = $request->date1;
            $event->date_response_expected = $request->date1;

            if ($request->action == 'move') {
                $event->date2 = $request->date2 != null ? $request->date2 : null;

                if ($request->allDay == false && $event->date2 == null) {    // if not allDay and end time is NULL, then set end time to 1 hour after start time
                    $event->date2 = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($event->date1)));
                }
            } elseif ($request->action == 'resize') {
                $event->date2 = $request->date2;
            }

            $event->save();
        }
    } // end function

    public function resize_event(Request $request)
    {
        $verified = $request->validate(
            ['formtype' => 'string|max:20|nullable',
                'action' => 'string|max:10|nullable',
                'entry_id' => 'numeric|integer|required',
                'allDay' => 'boolean',
            ]);

        if ($request->allDay == false) {     // if not allDay, validate datetime format
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

        if ($event && $event->id == $request->entry_id && $event->firm_id == $request->user()->firm_id) {  // if retrieved the correct event, and it's for the correct law firm, then make the changes
            $event->all_day = $request->allDay;
            $event->date1 = $request->date1;
            $event->date_response_expected = $request->date1;
            $event->date2 = $request->date2;

            $event->save();
        }

    }

    public function lookup_file(Request $request)
    {
        $files_found = File::query()
            ->select('id', 'name')
            ->where('firm_id', $request->user()->firm_id)
            ->where('name', 'like', $request->search.'%')
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
