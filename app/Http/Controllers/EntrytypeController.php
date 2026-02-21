<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Folder;
use App\Models\Entrytype;
use Illuminate\Http\Request;

class EntrytypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Folder $folder)
    {
        $show = $request->query('show');

        $entrytypes = Entrytype::where('firm_id', $request->user()->firm_id)
                        ->where('folder_id', $folder->id)
                        ->orderBy('name')
                        ->paginate($show ? $show : 10)
                        ->withQueryString();

        // dd($entrytypes);
                        
        return Inertia::render('Entrytypes/Index', [ 'entrytypes' => $entrytypes, 'folder' => $folder ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Folder $folder)
    {
        return inertia::render('Entrytypes/Create', [
            'entrytype' => [
                'id' => 0,
                'folder_name' => $folder->name,
                'folder_id' => $folder->id,
                'name' => '',
            ],
        ]);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Folder $folder)
    {
        $verified = $request->validate(
            [   'name' => 'required|max:255',
            ]);
        $thetype = new Entrytype;
        $thetype->firm_id = $request->user()->firm_id;
        $thetype->folder_id = $folder->id;
        $thetype->name = $request->name;

        $thetype->save();

        return redirect('/folders/' . $folder->id . '/entrytypes?page=' . $request->current_page . '&show=' . $request->show);
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
    public function edit(Folder $folder, Entrytype $entrytype)
    {
        // dd($entrytype);
        
        return Inertia::render('Entrytypes/Edit', [
            'entrytype' => [
                'id' => $entrytype->id,
                'folder_id' => $folder->id,
                'name' => $entrytype->name,
            ],
        ]);
        
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Folder $folder, Entrytype $entrytype)
    {
        $verified = $request->validate(
            [   'name' => 'required|max:255',
            ]);

        $entrytype->name = $request->name;

        $entrytype->save();

        return redirect('/folders/' . $folder->id . '/entrytypes?page=' . $request->current_page . '&show=' . $request->show);
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
}
