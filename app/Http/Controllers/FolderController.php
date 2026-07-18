<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FolderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $show = min((int) $request->query('show', 10) ?: 10, 50);

        $folders = Folder::query()
            ->when($request->query('search'), function ($query, $search) {
                $query->where('name', 'like', $search.'%');
            })
            ->orderBy('name')
            ->paginate($show)
            ->withQueryString();

        return Inertia::render('Folders/Index', compact('folders'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return inertia::render('Folders/Create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $verified = $request->validate(
            ['name' => 'required|max:255',
                'input_time' => 'boolean',
                'date1_prompt' => 'nullable|max:20',
                'date2_prompt' => 'nullable|max:20',
                'hide_date2_prompt' => 'boolean',
                'from_prompt' => 'nullable|max:255',
                'to_prompt' => 'nullable|max:255',
                'hide_to_prompt' => 'boolean',
                'entrytype_prompt' => 'nullable|max:255',
                'note_prompt' => 'nullable|max:255',
                'responseexpected_prompt' => 'nullable|max:255',
                'short_name' => 'nullable|max:255',
                'amount_prompt' => 'nullable|max:255',
                'hide_amount_prompt' => 'boolean',
            ]);

        $contact = Folder::create($verified);

        return redirect('/folders?page='.$request->current_page.'&show='.$request->show);

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
    public function edit(Folder $folder)
    {
        return Inertia::render('Folders/Edit', [
            'folder' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'input_time' => $folder->input_time,
                'date1_prompt' => $folder->date1_prompt,
                'date2_prompt' => $folder->date2_prompt,
                'hide_date2_prompt' => $folder->hide_date2_prompt,
                'from_prompt' => $folder->from_prompt,
                'to_prompt' => $folder->to_prompt,
                'hide_to_prompt' => $folder->hide_to_prompt,
                'entrytype_prompt' => $folder->entrytype_prompt,
                'note_prompt' => $folder->note_prompt,
                'responseexpected_prompt' => $folder->responseexpected_prompt,
                'short_name' => $folder->short_name,
                'amount_prompt' => $folder->amount_prompt,
                'hide_amount_prompt' => $folder->hide_amount_prompt,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Folder $folder)
    {
        $verified = $request->validate(
            ['name' => 'required|max:255',
                'input_time' => 'boolean',
                'date1_prompt' => 'nullable|max:20',
                'date2_prompt' => 'nullable|max:20',
                'hide_date2_prompt' => 'boolean',
                'from_prompt' => 'nullable|max:255',
                'to_prompt' => 'nullable|max:255',
                'hide_to_prompt' => 'boolean',
                'entrytype_prompt' => 'nullable|max:255',
                'note_prompt' => 'nullable|max:255',
                'responseexpected_prompt' => 'nullable|max:255',
                'short_name' => 'nullable|max:255',
                'amount_prompt' => 'nullable|max:255',
                'hide_amount_prompt' => 'boolean',
            ]);

        $folder->name = $request->name;
        $folder->input_time = $request->input_time;
        $folder->date1_prompt = $request->date1_prompt;
        $folder->date2_prompt = $request->date2_prompt;
        $folder->hide_date2_prompt = $request->hide_date2_prompt;
        $folder->from_prompt = $request->from_prompt;
        $folder->to_prompt = $request->to_prompt;
        $folder->hide_to_prompt = $request->hide_to_prompt;
        $folder->entrytype_prompt = $request->entrytype_prompt;
        $folder->note_prompt = $request->note_prompt;
        $folder->responseexpected_prompt = $request->responseexpected_prompt;
        $folder->short_name = $request->short_name;
        $folder->amount_prompt = $request->amount_prompt;
        $folder->hide_amount_prompt = $request->hide_amount_prompt;

        $folder->save();

        return redirect('/folders?page='.$request->current_page.'&show='.$request->show);
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
