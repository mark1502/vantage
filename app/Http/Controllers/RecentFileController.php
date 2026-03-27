<?php

namespace App\Http\Controllers;

use App\Models\RecentFile;
use Illuminate\Http\JsonResponse;

class RecentFileController extends Controller
{
    public function index(): JsonResponse
    {
        $recentFiles = RecentFile::where('user_id', auth()->id())
            ->orderByDesc('last_opened_at')
            ->with('file:id,name')
            ->get()
            ->pluck('file')
            ->map(fn ($file) => ['id' => $file->id, 'name' => $file->name]);

        return response()->json($recentFiles);
    }
}
