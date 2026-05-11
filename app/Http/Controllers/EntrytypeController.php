<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Folder;
use App\Models\Entrytype;
use Illuminate\Http\Request;

class EntrytypeController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $firmId = $request->user()->firm_id;
        $folderId = $request->query('folder_id');
        $show = $request->query('show', 10);
        $filter = $request->query('filter', 'current');

        $folders = Folder::query()
            ->whereNotIn('id', [5, 7, 10])
            ->orderBy('id')
            ->get(['id', 'name']);

        if (! $folderId && $folders->isNotEmpty()) {
            $folderId = $folders->first()->id;
        }

        $entrytypes = Entrytype::query()
            ->where('firm_id', $firmId)
            ->where('folder_id', $folderId)
            ->when($filter === 'current', fn ($query) => $query->where('faux_deleted', false))
            ->when($filter === 'deleted', fn ($query) => $query->where('faux_deleted', true))
            ->orderBy('name')
            ->paginate($show)
            ->withQueryString();

        return Inertia::render('Entrytypes/Index', [
            'entrytypes' => $entrytypes,
            'folders' => $folders,
            'selectedFolderId' => (int) $folderId,
            'filter' => $filter,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|max:255',
            'folder_id' => 'required|integer',
        ]);

        Entrytype::create([
            'firm_id' => $request->user()->firm_id,
            'folder_id' => $request->folder_id,
            'name' => $request->name,
        ]);

        return redirect(route('entrytypes.index', [
            'folder_id' => $request->folder_id,
            'show' => $request->show,
            'filter' => $request->filter,
        ]));
    }

    public function update(Request $request, Entrytype $entrytype): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'name' => 'required|max:255',
        ]);

        $entrytype->update(['name' => $request->name]);

        return redirect(route('entrytypes.index', [
            'folder_id' => $request->folder_id,
            'page' => $request->page,
            'show' => $request->show,
            'filter' => $request->filter,
        ]));
    }

    public function destroy(Request $request, Entrytype $entrytype): \Illuminate\Http\RedirectResponse
    {
        $entrytype->update(['faux_deleted' => true]);

        return redirect(route('entrytypes.index', [
            'folder_id' => $request->folder_id,
            'page' => $request->page,
            'show' => $request->show,
            'filter' => $request->filter,
        ]));
    }

    public function restore(Request $request, Entrytype $entrytype): \Illuminate\Http\RedirectResponse
    {
        $entrytype->update(['faux_deleted' => false]);

        return redirect(route('entrytypes.index', [
            'folder_id' => $request->folder_id,
            'page' => $request->page,
            'show' => $request->show,
            'filter' => $request->filter,
        ]));
    }
}
