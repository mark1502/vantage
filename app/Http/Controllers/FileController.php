<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\UpdateFileRequest;
use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\File;
use App\Models\Filetype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FileController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $show = min((int) $request->query('show', 10) ?: 10, 50);
        $status = $request->query('status', 'open');
        $searchMode = $this->normalizeSearchMode($request->query('search_mode'));

        $files = File::with([
            'filetype',
            'assignedAttorney.contact:id,display_name',
        ])
            ->where('firm_id', $request->user()->firm_id)
            ->when($request->query('search'), function ($query, $search) use ($searchMode) {
                $query->where('name', 'like', $this->searchPattern($search, $searchMode));
            })
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'open') {
                    $query->whereNull('date_closed');
                } else {
                    $query->whereNotNull('date_closed');
                }
            })
            ->orderBy('name')
            ->paginate($show)
            ->withQueryString();

        $fileOpenTo = $request->user()->preferences()
            ->where('name', 'file_open_to')
            ->value('setting') ?? 'correspondence';

        $firm = $request->user()->firm;
        $fileCount = $firm?->fileCount() ?? 0;
        $fileLimit = $firm?->fileLimit();

        $subscription = [
            'file_count' => $fileCount,
            'file_limit' => $fileLimit,
            'can_create_files' => $fileLimit === null || $fileCount < $fileLimit,
        ];

        return Inertia::render('Files/Index', compact('files', 'fileOpenTo', 'status', 'subscription', 'searchMode'));
    }

    /**
     * Constrain the requested search mode to a known value.
     *
     * "starts" keeps the LIKE pattern anchored so the firm_id/name index can be
     * used; "includes" falls back to a full scan of the firm's files.
     */
    protected function normalizeSearchMode(?string $mode): string
    {
        return $mode === 'includes' ? 'includes' : 'starts';
    }

    /**
     * Build the LIKE pattern for a file name search.
     */
    protected function searchPattern(string $search, string $mode): string
    {
        $escaped = addcslashes($search, '%_\\');

        return $mode === 'includes' ? '%'.$escaped.'%' : $escaped.'%';
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if (! $request->user()->firm->canCreateFiles()) {
            return redirect()->route('files.index')->withErrors([
                'file_limit' => 'Your firm has reached the free plan limit of 10 files.',
            ]);
        }

        $filetypes = Filetype::select('id', 'name', 'enable_file_SOL', 'set_as_default')
            ->where('firm_id', $request->user()->firm_id)
            ->orderBy('name')
            ->get();

        $attorneys = Contact::select('id', 'display_last_first')
            ->where('firm_id', $request->user()->firm_id)
            ->where('is_firm_member', true)
            ->where('firm_role', 'Attorney')
            ->orderBy('display_last_first')
            ->get();

        $firm_members = Contact::select('id', 'display_last_first')
            ->where('firm_id', $request->user()->firm_id)
            ->where('is_firm_member', true)
            ->orderBy('display_last_first')
            ->get();

        return inertia::render('Files/Create', [
            'filetypes' => $filetypes,
            'attorneys' => $attorneys,
            'firm_members' => $firm_members,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreFileRequest $request)
    {
        $firm = $request->user()->firm;

        if (! $firm->canCreateFiles()) {
            return back()->withErrors([
                'file_limit' => 'Your firm has reached the free plan limit of 10 files. Please upgrade to a paid subscription to create more files.',
            ]);
        }

        $newCase = new File;
        $newCase->name = $request->name;
        $newCase->summary = $request->summary;
        $newCase->date_sol = $request->date_sol;
        $newCase->date_opened = $request->date_opened;
        $newCase->date_filed = $request->date_filed;
        $newCase->date_closed = $request->date_closed;
        $newCase->date_archived = $request->date_archived;
        $newCase->court_filed = $request->court_filed;
        $newCase->docket_number = $request->docket_number;
        $newCase->file_number = $request->file_number;
        $newCase->referred_by = $request->referred_by;
        $newCase->referral_amount = $request->referral_amount;
        $newCase->fee_arrangement = $request->fee_arrangement;
        $newCase->fee_amount = $request->fee_amount;
        $newCase->final_disposition = $request->final_disposition;
        $newCase->filetype_id = $request->filetype_id;

        $newCase->firm_id = $request->user()->firm_id;

        $newCase->save();

        // Create ContactRole for assigned attorney
        ContactRole::create([
            'file_id' => $newCase->id,
            'contact_id' => $request->attorney_id,
            'role' => 'attorney_for_client',
            'role_label' => 'Attorney for Client',
            'is_file_attorney' => true,
        ]);

        // Create ContactRole for client
        ContactRole::create([
            'file_id' => $newCase->id,
            'contact_id' => $request->client_contact_id,
            'role' => 'client',
            'role_label' => 'Client',
            'is_file_client' => true,
        ]);

        $filetype = Filetype::find($newCase->filetype_id);
        if ($filetype && $filetype->enable_file_SOL) {
            $newCase->syncSolCalendarEntry();
        }

        return redirect(route('files.index', ['page' => $request->current_page, 'show' => $request->show]));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(File $file)
    {
        $this->authorize('view', $file);

        $filetypes = Filetype::select('id', 'name')
            ->where('firm_id', $file->firm_id)
            ->orderBy('name')
            ->get();

        $attorneys = Contact::where('firm_id', $file->firm_id)
            ->where('is_firm_member', true)
            ->where('firm_role', 'Attorney')
            ->get();

        // Load the assigned attorney from contact_roles
        $assignedAttorney = $file->assignedAttorney;

        // Load the client from contact_roles
        $clientContactRole = ContactRole::where('file_id', $file->id)
            ->where('is_file_client', true)
            ->with('contact:id,display_last_first')
            ->first();

        return inertia::render('Files/Edit', [
            'file' => $file,
            'filetypes' => $filetypes,
            'attorneys' => $attorneys,
            'assigned_attorney_id' => $assignedAttorney?->contact_id,
            'client_name' => $clientContactRole?->contact?->display_last_first ?? '',
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateFileRequest $request, File $file)
    {
        $file->name = $request->name;
        $file->summary = $request->summary ?? '';
        $file->date_sol = $request->date_sol;
        $file->date_opened = $request->date_opened;
        $file->date_filed = $request->date_filed;
        $file->date_closed = $request->date_closed;
        $file->date_archived = $request->date_archived;
        $file->court_filed = $request->court_filed ?? '';
        $file->docket_number = $request->docket_number ?? '';
        $file->file_number = $request->file_number ?? '';
        $file->referred_by = $request->referred_by ?? '';
        $file->referral_amount = $request->referral_amount ?? '';
        $file->fee_arrangement = $request->fee_arrangement;
        $file->fee_amount = $request->fee_amount ?? '';
        $file->final_disposition = $request->final_disposition ?? '';
        $file->filetype_id = $request->filetype_id;

        $file->save();

        // Update the file's designated attorney
        DB::transaction(function () use ($file, $request) {
            // Clear is_file_attorney on all roles for this file
            ContactRole::where('file_id', $file->id)
                ->where('is_file_attorney', true)
                ->update(['is_file_attorney' => false]);

            // Find or create the attorney role for the new attorney
            $attorneyRole = ContactRole::firstOrCreate(
                [
                    'file_id' => $file->id,
                    'contact_id' => $request->attorney_id,
                    'role' => 'attorney_for_client',
                ],
                [
                    'role_label' => 'Attorney for Client',
                ]
            );

            // Mark as file attorney and protected
            $attorneyRole->update([
                'is_file_attorney' => true,
            ]);
        });

        $file->syncSolCalendarEntry();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(File $file)
    {
        $this->authorize('delete', $file);

        $file->date_closed = now();
        $file->summary = trim($file->summary."\nFile Closed as DELETED");
        $file->save();

        return redirect()->back();
    }

    public function lookup_file(Request $request)
    {
        $verified = $request->validate(
            ['search' => 'string|max:255',
                'search_mode' => 'nullable|in:starts,includes',
            ]);

        $searchMode = $this->normalizeSearchMode($request->input('search_mode'));

        $files_found = File::query()
            ->select('id', 'name')
            ->where('firm_id', $request->user()->firm_id)
            ->where('name', 'like', $this->searchPattern((string) $request->search, $searchMode))
            ->whereNull('date_closed')   // exclude closed files from lookup
            ->orderBy('name')

            ->simplePaginate(8);
        // ->withQueryString();

        return $files_found;
    }
}
