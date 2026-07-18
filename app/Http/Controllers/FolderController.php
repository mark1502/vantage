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
}
