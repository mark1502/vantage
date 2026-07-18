<?php

namespace App\Http\Controllers;

use App\Models\Entrytype;
use App\Models\Folder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EntrytypeController extends Controller
{
    private const SOL_ENTRYTYPE_NAME = 'Deadline - Statute of Limitations';

    private const SOL_FOLDER_ID = 6;

    public function index(Request $request): \Inertia\Response
    {
        $firmId = $request->user()->firm_id;
        $folderId = $request->query('folder_id');
        $show = min((int) $request->query('show', 10) ?: 10, 50);
        $filter = $request->query('filter', 'current');

        $folders = Folder::query()
            ->whereNotIn('id', [5, 7, 10])
            ->orderBy('id')
            ->get(['id', 'name']);

        if (! $folderId && $folders->isNotEmpty()) {
            $folderId = $folders->first()->id;
        }

        $activeCount = Entrytype::query()
            ->where('firm_id', $firmId)
            ->where('folder_id', $folderId)
            ->where('faux_deleted', false)
            ->count();

        $entrytypes = Entrytype::query()
            ->where('firm_id', $firmId)
            ->where('folder_id', $folderId)
            ->when($filter === 'current', fn ($query) => $query->where('faux_deleted', false))
            ->when($filter === 'deleted', fn ($query) => $query->where('faux_deleted', true))
            ->orderBy('name')
            ->paginate($show)
            ->withQueryString();

        $solEntrytypeId = Entrytype::query()
            ->where('firm_id', $firmId)
            ->where('folder_id', self::SOL_FOLDER_ID)
            ->where('name', self::SOL_ENTRYTYPE_NAME)
            ->value('id');

        return Inertia::render('Entrytypes/Index', [
            'entrytypes' => $entrytypes,
            'folders' => $folders,
            'selectedFolderId' => (int) $folderId,
            'filter' => $filter,
            'activeCount' => $activeCount,
            'solEntrytypeId' => $solEntrytypeId,
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
        $this->authorize('update', $entrytype);

        if ($entrytype->name === self::SOL_ENTRYTYPE_NAME
            && $entrytype->folder_id === self::SOL_FOLDER_ID) {
            return back();
        }

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
        $this->authorize('delete', $entrytype);

        if ($entrytype->name === self::SOL_ENTRYTYPE_NAME
            && $entrytype->folder_id === self::SOL_FOLDER_ID) {
            return back();
        }

        $activeCount = Entrytype::query()
            ->where('firm_id', $entrytype->firm_id)
            ->where('folder_id', $entrytype->folder_id)
            ->where('faux_deleted', false)
            ->count();

        if ($activeCount <= 1) {
            return back();
        }

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
        $this->authorize('update', $entrytype);

        $entrytype->update(['faux_deleted' => false]);

        return redirect(route('entrytypes.index', [
            'folder_id' => $request->folder_id,
            'page' => $request->page,
            'show' => $request->show,
            'filter' => $request->filter,
        ]));
    }
}
