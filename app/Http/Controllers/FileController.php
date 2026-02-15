<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\File;
use App\Models\Filetype;
use App\Models\Role;
use Illuminate\Http\Request;
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
        // dd($request->user()->firm_id);
        $show = $request->query('show');

        $files = File::with([
            'filetype',
            'assignedAttorney.contact:id,display_name'
        ])
        ->where('firm_id', $request->user()->firm_id)
        ->when($request->query('search'), function($query, $search) {
            $query->where('name', 'like', '%' . $search . '%');
        })
        ->orderBy('name')

        ->paginate($show ? $show : 10)
        ->withQueryString();

        // dd($file_list);

        return Inertia::render('Files/Index', compact('files'));

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $filetypes = Filetype::select('id','name','enable_file_SOL','set_as_default')
                        ->where('firm_id', $request->user()->firm_id)
                        ->orderBy('name')
                        ->get();

        $attorneys = Contact::where('firm_id', $request->user()->firm_id)
                    ->where('is_firm_member', true)
                    ->where('firm_role', 'Attorney')
                    ->get();

        $firm_members = Contact::select('id', 'display_last_first')
                    ->where('firm_id', $request->user()->firm_id)
                    ->where('is_firm_member', true)
                    ->orderBy('display_last_first')
                    ->get();

        return inertia::render('Files/Create',[
            // 'editmode' => 'create',
            'filetypes' => $filetypes,
            'attorneys' => $attorneys,
            'firm_members' => $firm_members,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $verified = $request->validate(
            [   'name' => 'required|max:255',
                'summary' => 'nullable|max:5000',
                'date_sol' => 'nullable|date_format:Y-m-d',
                'date_opened' => 'nullable|date_format:Y-m-d',
                'date_filed' => 'nullable|date_format:Y-m-d',
                'date_closed' => 'nullable|date_format:Y-m-d',
                'date_archived' => 'nullable|date_format:Y-m-d',
                'court_filed' => 'nullable|max:255',
                'docket_number' => 'nullable|max:255',
                'file_number' => 'nullable|max:255',
                'referred_by' => 'nullable|max:255',
                'referral_amount' => 'nullable|max:255',
                'fee_arrangement' => 'nullable|max:255',
                'fee_amount' => 'nullable|max:255',
                'final_disposition' => 'nullable|max:255',
                'filetype_id' => 'nullable|integer',
                'attorney_id' => 'required|integer',
                'client_contact_id' => 'required|integer',
            ],
            [   'name' => 'File name is required.',
                'attorney_id' => 'Assigned attorney is required.',
                'client_contact_id' => 'Client is required.',
            ]);

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

            $AttorneyRole = Role::select('id')
                ->where( 'firm_id', $newCase->firm_id )
                ->where( 'name', 'Attorney' )
                ->first();

            // Create ContactRole for assigned attorney
            ContactRole::create([
                'file_id' => $newCase->id,
                'contact_id' => $request->attorney_id,
                'role_id' => $AttorneyRole->id,
                'is_attorney' => true,
                'is_client' => true,
            ]);

            // Create ContactRole for client
            $ClientRole = Role::select('id')
                ->where( 'firm_id', $newCase->firm_id )
                ->where( 'name', 'Client' )
                ->first();

            ContactRole::create([
                'file_id' => $newCase->id,
                'contact_id' => $request->client_contact_id,
                'role_id' => $ClientRole->id,
                'is_client' => true,
                'is_attorney' => false,
            ]);

            return redirect( route( 'files.index', [ 'page' => $request->current_page, 'show' => $request->show ] ) );
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
        $filetypes = Filetype::select('id','name')
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
                        ->where('is_client', true)
                        ->where('is_attorney', false)
                        ->with('contact:id,display_last_first')
                        ->first();

        return inertia::render('Files/Edit',[
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
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, File $file)
    {
        // dd($request);
        
        $verified = $request->validate(
            [
                'name' => 'required|max:255',
                'summary' => 'nullable|max:5000',
                'date_sol' => 'nullable|date_format:Y-m-d',
                'date_opened' => 'nullable|date_format:Y-m-d',
                'date_filed' => 'nullable|date_format:Y-m-d',
                'date_closed' => 'nullable|date_format:Y-m-d',
                'date_archived' => 'nullable|date_format:Y-m-d',
                'court_filed' => 'nullable|max:255',
                'docket_number' => 'nullable|max:255',
                'file_number' => 'nullable|max:255',
                'referred_by' => 'nullable|max:255',
                'referral_amount' => 'nullable|max:255',
                'fee_arrangement' => 'nullable|max:255',
                'fee_amount' => 'nullable|max:255',
                'final_disposition' => 'nullable|max:255',
                'filetype_id' => 'nullable|integer',
                'attorney_id' => 'required|integer',
            ]);

            // dd($request);

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

            // Update or create ContactRole for assigned attorney
            ContactRole::updateOrCreate(
                [
                    'file_id' => $file->id,
                    'is_attorney' => true,
                    'is_client' => true,    // attorney for client, so true
                ],
                [
                    'contact_id' => $request->attorney_id,
                ]
            );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function lookup_file(Request $request)
    {
        $verified = $request->validate(
            [ 'search' => 'string|max:255',
            ]);
            
        $files_found = File::query()
            ->select('id','name')
            ->where('firm_id', $request->user()->firm_id)
            ->where('name', 'like', '%' . $request->search . '%')
            ->orderBy('name')

            ->simplePaginate(8);
            // ->withQueryString();

        return $files_found;
    }

}