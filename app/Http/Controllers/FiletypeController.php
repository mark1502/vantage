<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Entry;
use App\Models\File;
use App\Models\Filetype;
use Illuminate\Http\Request;

class FiletypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index( Request $request )
    {   
        $show = $request->query('show') ?? 10;

        $this_firm_id = $request->user()->firm_id;

        if( Filetype::where( 'firm_id', $this_firm_id )->exists() ) {                               // If filetypes exist for this firm, do nothing
        } else {                                                                                        // else, no filetypes found, so add the base types for this firm
            $base_filetypes = Filetype::where( 'firm_id', 1 )->orderBy('name')->get();          // get all base_filetypes (where firm_id === 1)

            foreach ($base_filetypes as $base_filetype) {                                       // for each base_filetype add an entry for this law firm
                $new_filetype = new Filetype;
    
                $new_filetype->name = $base_filetype->name;
                $new_filetype->has_correspondence = $base_filetype->has_correspondence;
                $new_filetype->has_pleadings = $base_filetype->has_pleadings;
                $new_filetype->has_discovery = $base_filetype->has_discovery;
                $new_filetype->has_documents = $base_filetype->has_documents;
                $new_filetype->has_memos = $base_filetype->has_memos;
                $new_filetype->has_events = $base_filetype->has_events;
                $new_filetype->has_todo = $base_filetype->has_todo;
                $new_filetype->has_phone = $base_filetype->has_phone;
                $new_filetype->has_medrecs = $base_filetype->has_medrecs;
                $new_filetype->has_medbills = $base_filetype->has_medbills;
                $new_filetype->has_costs = $base_filetype->has_costs;
                $new_filetype->enable_file_SOL = $base_filetype->enable_file_SOL;
                $new_filetype->set_as_default = $base_filetype->set_as_default;
    
                $new_filetype->firm_id = $this_firm_id;
    
                $new_filetype->save();
            }
        }

        $filetypes = Filetype::query();
        $filetypes = $filetypes->where('firm_id', $request->user()->firm_id)
        ->orderBy('name')
        ->paginate($show ? $show : 10)
        ->withQueryString();

        // dd($filetypes);
        return Inertia::render('Filetypes/Index', compact('filetypes'));

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Filetypes/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $verified = $request->validate(
            [   'formtype' => 'max:255|nullable',
                'name' => 'required|max:255',
                'has_correspondence' => 'boolean',
                'has_pleadings' => 'boolean',
                'has_discovery' => 'boolean',
                'has_documents' => 'boolean',
                'has_memos' => 'boolean',
                'has_events' => 'boolean',
                'has_todo' => 'boolean',
                'has_phone' => 'boolean',
                'has_medrecs' => 'boolean',
                'has_medbills' => 'boolean',
                'has_costs' => 'boolean',
                'enable_file_SOL' => 'boolean',
                'current_page' => 'numeric|integer',
                'show' => 'numeric|integer',
            ]);

            // If SOL is enabled, pleadings must be enabled
            if ($request->enable_file_SOL) {
                $request->merge(['has_pleadings' => true]);
            }

            $filetype = new Filetype;

            $filetype->name = $request->name;
            $filetype->firm_id = $request->user()->firm_id;
            $filetype->has_correspondence = $request->has_correspondence === true ? 1 : 0;
            $filetype->has_pleadings = $request->has_pleadings === true ? 1 : 0;
            $filetype->has_discovery = $request->has_discovery === true ? 1 : 0;
            $filetype->has_documents = $request->has_documents === true ? 1 : 0;
            $filetype->has_memos = $request->has_memos === true ? 1 : 0;
            $filetype->has_events = $request->has_events === true ? 1 : 0;
            $filetype->has_todo = $request->has_todo === true ? 1 : 0;
            $filetype->has_phone = $request->has_phone === true ? 1 : 0;
            $filetype->has_medrecs = $request->has_medrecs === true ? 1 : 0;
            $filetype->has_medbills = $request->has_medbills === true ? 1 : 0;
            $filetype->has_costs = $request->has_costs === true ? 1 : 0;
            $filetype->enable_file_SOL = $request->enable_file_SOL === true ? 1 : 0;

            $filetype->save();

            return redirect('/filetypes?page=' . $request->current_page . '&show=' . $request->show);
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
    public function edit(Filetype $filetype)
    {
        return Inertia::render('Filetypes/Edit', [
            'filetype' => $filetype,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Filetype $filetype)
    {
        $verified = $request->validate(
            [   'formtype' => 'max:255|nullable',
                'name' => 'required|max:255',
                'has_correspondence' => 'boolean',
                'has_pleadings' => 'boolean',
                'has_discovery' => 'boolean',
                'has_documents' => 'boolean',
                'has_memos' => 'boolean',
                'has_events' => 'boolean',
                'has_todo' => 'boolean',
                'has_phone' => 'boolean',
                'has_medrecs' => 'boolean',
                'has_medbills' => 'boolean',
                'has_costs' => 'boolean',
                'enable_file_SOL' => 'boolean',
                'current_page' => 'numeric|integer',
                'show' => 'numeric|integer',
            ]);

        // If SOL is enabled, pleadings must be enabled
        if ($request->enable_file_SOL) {
            $request->merge(['has_pleadings' => true]);
        }

        $removed_folders = $this->check4RemovedFolder( $request, $filetype );

        if( $removed_folders ) {
            return back()->withErrors(["existing_entries" => $removed_folders])->withInput();
        }


        // if( true ) {
        //     return back()->withErrors(["existing_entries" => "correspondence"])->withInput();
        // }

        $filetype->name = $request->name;
        $filetype->firm_id = $request->user()->firm_id;
        $filetype->has_correspondence = $request->has_correspondence === true ? 1 : 0;
        $filetype->has_pleadings = $request->has_pleadings === true ? 1 : 0;
        $filetype->has_discovery = $request->has_discovery === true ? 1 : 0;
        $filetype->has_documents = $request->has_documents === true ? 1 : 0;
        $filetype->has_memos = $request->has_memos === true ? 1 : 0;
        $filetype->has_events = $request->has_events === true ? 1 : 0;
        $filetype->has_todo = $request->has_todo === true ? 1 : 0;
        $filetype->has_phone = $request->has_phone === true ? 1 : 0;
        $filetype->has_medrecs = $request->has_medrecs === true ? 1 : 0;
        $filetype->has_medbills = $request->has_medbills === true ? 1 : 0;
        $filetype->has_costs = $request->has_costs === true ? 1 : 0;
        $filetype->enable_file_SOL = $request->enable_file_SOL === true ? 1 : 0;

        $filetype->save();

        return redirect('/filetypes?page=' . $request->current_page . '&show=' . $request->show);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    
    public function check4RemovedFolder( $request, $filetype )
    {
        $removed_folders = [];

        if( $filetype->has_correspondence === 1 && $request->has_correspondence === false ) {
            if( $this->entriesFound( $request, $filetype->id, 1 ) === true ) $removed_folders[] = 'correspondence';
        }
        else if( $filetype->has_pleadings === 1 && $request->has_pleadings === false ) {
            if( $this->entriesFound( $request, $filetype->id, 2 ) === true ) $removed_folders[] = 'pleadings';
        }
        else if( $filetype->has_discovery === 1 && $request->has_discovery === false ) {
            if( $this->entriesFound( $request, $filetype->id, 3 ) === true ) $removed_folders[] = 'discovery';
        }
        else if( $filetype->has_documents === 1 && $request->has_documents === false ) {
            if( $this->entriesFound( $request, $filetype->id, 4 ) === true ) $removed_folders[] = 'documents';
        }
        else if( $filetype->has_memos === 1 && $request->has_memos === false ) {
            if( $this->entriesFound( $request, $filetype->id, 5 ) === true ) $removed_folders[] = 'memos';
        }
        else if( $filetype->has_events === 1 && $request->has_events === false ) {
            if( $this->entriesFound( $request, $filetype->id, 6 ) === true ) $removed_folders[] = 'events';
        }
        else if( $filetype->has_todo === 1 && $request->has_todo === false ) {
            if( $this->entriesFound( $request, $filetype->id, 7 ) === true ) $removed_folders[] = 'todo';
        }
        else if( $filetype->has_phone === 1 && $request->has_phone === false ) {
            if( $this->entriesFound( $request, $filetype->id, 8 ) === true ) $removed_folders[] = 'phone';
        }
        else if( $filetype->has_medrecs === 1 && $request->has_medrecs === false ) {
            if( $this->entriesFound( $request, $filetype->id, 9 ) === true ) $removed_folders[] = 'medrecs';
        }
        else if( $filetype->has_medbills === 1 && $request->has_medbills === false ) {
            if( $this->entriesFound( $request, $filetype->id, 10 ) === true ) $removed_folders[] = 'medbills';
        }
        else if( $filetype->has_costs === 1 && $request->has_costs === false ) {
            if( $this->entriesFound( $request, $filetype->id, 11 ) === true ) $removed_folders[] = 'costs';
        }
        else if( $filetype->has_enable_file_SOL === 1 && $request->has_enable_file_SOL === false ) {
            if( $this->entriesFound( $request, $filetype->id, 99 ) === true ) $removed_folders[] = 'enable_file_SOL';
        }

        return $removed_folders;
    }


    public function entriesFound( $request, $filetype_id, $folder_id ) {
        $entry_found = false;

        $files_of_type = File::query()                                              // get all files of this type (for this firm)
                            ->where( 'firm_id', $request->user()->firm_id )
                            ->where( 'filetype_id', $filetype_id )
                            ->get();

        if( $files_of_type ) {                                                          // if files of this type are found
            foreach ( $files_of_type as $aFile ) {                                      // for each file of this type
                if( $folder_id === 99 ) {                                               // if the folder we're looking for is 99 (look for sol)
                    if( $aFile->date_sol ) {                                            // is there an sol for this file
                        $entry_found = true;                                            // set found as true, then break out of foreach
                        break;
                    }
                } else {                                                                // else if folder is not 99
                    $foundEntry = Entry::query()                                        // is there an entry in the file for this folder_id
                                    ->where('file_id', $aFile->id)
                                    ->where('folder_id', $folder_id)
                                    ->first();
                    if( $foundEntry !== null ) {                                        // if an entry for this folder_id was found
                        $entry_found = true;                                            // set found as true, then break out of foreach
                        break;
                    } 
                }
            } // end foreach
        } // end if

        return $entry_found;
    }


    public function set_default_type(Request $request)
    {
        $filetypes = Filetype::query()
                            ->where('firm_id', $request->user()->firm_id)
                            ->get();
        foreach ( $filetypes as $filetype ) {
            if( $filetype->set_as_default === 1 && $filetype->id !== $request->default_id ) {           // if saved as default is true , but id is not same as the requested defult
                $filetype['set_as_default'] = 0;                                                            // now, save as false
                $filetype->save();
            } else if( $filetype->set_as_default === 0 && $filetype->id === $request->default_id ) {    // else if saved as default is false, but id matches requested default
                $filetype['set_as_default'] = 1;                                                            // not, save as true
                $filetype->save();
            }
        }
    }
    
}
