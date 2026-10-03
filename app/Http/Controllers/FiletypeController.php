<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\File;
use App\Models\Filetype;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FiletypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        $show = min((int) $request->query('show', 10) ?: 10, 50);

        $this_firm_id = $request->user()->firm_id;

        if (Filetype::where('firm_id', $this_firm_id)->exists()) {                               // If filetypes exist for this firm, do nothing
        } else {                                                                                        // else, no filetypes found, so add the base types for this firm
            $base_filetypes = Filetype::withoutGlobalScope('firm')->where('firm_id', 1)->orderBy('name')->get();          // get all base_filetypes (firm 1); bypass firm scope for this cross-firm bootstrap read

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

        $search = trim((string) $request->query('search', ''));

        $filetypes = Filetype::query()
            ->where('firm_id', $this_firm_id)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->withCount(['files' => fn ($query) => $query->withoutGlobalScope('firm')])
            ->orderBy('name')
            ->paginate($show)
            ->withQueryString();

        if ($filetypes->isEmpty() && $filetypes->currentPage() > 1) {
            return redirect(route('filetypes.index', [
                'page' => $filetypes->lastPage(),
                'show' => $show,
                'search' => $search ?: null,
            ]));
        }

        $filetypeCount = Filetype::query()->where('firm_id', $this_firm_id)->count();

        return Inertia::render('Filetypes/Index', [
            'filetypes' => $filetypes,
            'filetypeCount' => $filetypeCount,
            'search' => $search,
        ]);
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
            ['formtype' => 'max:255|nullable',
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

        return redirect('/filetypes?page='.$request->current_page.'&show='.$request->show);
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
        $this->authorize('view', $filetype);

        return Inertia::render('Filetypes/Edit', [
            'filetype' => $filetype,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Filetype $filetype)
    {
        $this->authorize('update', $filetype);

        $verified = $request->validate(
            ['formtype' => 'max:255|nullable',
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

        $removed_folders = $this->check4RemovedFolder($request, $filetype);

        if ($removed_folders) {
            return back()->withErrors(['existing_entries' => implode(', ', $removed_folders)])->withInput();
        }

        // if( true ) {
        //     return back()->withErrors(["existing_entries" => "correspondence"])->withInput();
        // }

        $filetype->name = $request->name;
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

        return redirect('/filetypes?page='.$request->current_page.'&show='.$request->show);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Filetype $filetype): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $filetype);

        $redirectParams = [
            'page' => $request->input('page'),
            'show' => $request->input('show'),
            'search' => $request->input('search') ?: null,
        ];

        if ($filetype->isInUse()) {
            return back()->withErrors(['delete' => 'This file type cannot be deleted because one or more files (open or closed) use it.']);
        }

        if ($filetype->set_as_default) {
            return back()->withErrors(['delete' => 'The default file type cannot be deleted. Set another file type as the default first.']);
        }

        if (Filetype::query()->where('firm_id', $filetype->firm_id)->count() <= 1) {
            return back()->withErrors(['delete' => 'The last remaining file type cannot be deleted.']);
        }

        $filetype->delete();

        return redirect(route('filetypes.index', $redirectParams));
    }

    public function check4RemovedFolder($request, $filetype)
    {
        $folders = [
            'has_correspondence' => [1, 'correspondence'],
            'has_pleadings' => [2, 'pleadings'],
            'has_discovery' => [3, 'discovery'],
            'has_documents' => [4, 'documents'],
            'has_memos' => [5, 'memos'],
            'has_events' => [6, 'events'],
            'has_todo' => [7, 'todo'],
            'has_phone' => [8, 'phone'],
            'has_medrecs' => [9, 'medrecs'],
            'has_medbills' => [10, 'medbills'],
            'has_costs' => [11, 'costs'],
            'enable_file_SOL' => [99, 'enable_file_SOL'],
        ];

        $removed_folders = [];

        foreach ($folders as $field => [$folder_id, $folder_name]) {
            if ($filetype->{$field} === 1 && $request->{$field} === false) {
                if ($this->entriesFound($request, $filetype->id, $folder_id) === true) {
                    $removed_folders[] = $folder_name;
                }
            }
        }

        return $removed_folders;
    }

    public function entriesFound($request, $filetype_id, $folder_id)
    {
        $entry_found = false;

        $files_of_type = File::query()                                              // get all files of this type (for this firm)
            ->where('firm_id', $request->user()->firm_id)
            ->where('filetype_id', $filetype_id)
            ->get();

        if ($files_of_type) {                                                          // if files of this type are found
            foreach ($files_of_type as $aFile) {                                      // for each file of this type
                if ($folder_id === 99) {                                               // if the folder we're looking for is 99 (look for sol)
                    if ($aFile->date_sol) {                                            // is there an sol for this file
                        $entry_found = true;                                            // set found as true, then break out of foreach
                        break;
                    }
                } else {                                                                // else if folder is not 99
                    $foundEntry = Entry::query()                                        // is there an entry in the file for this folder_id
                        ->where('file_id', $aFile->id)
                        ->where('folder_id', $folder_id)
                        ->first();
                    if ($foundEntry !== null) {                                        // if an entry for this folder_id was found
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
        foreach ($filetypes as $filetype) {
            if ($filetype->set_as_default === 1 && $filetype->id !== $request->default_id) {           // if saved as default is true , but id is not same as the requested defult
                $filetype['set_as_default'] = 0;                                                            // now, save as false
                $filetype->save();
            } elseif ($filetype->set_as_default === 0 && $filetype->id === $request->default_id) {    // else if saved as default is false, but id matches requested default
                $filetype['set_as_default'] = 1;                                                            // not, save as true
                $filetype->save();
            }
        }
    }
}
