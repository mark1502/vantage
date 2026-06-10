<?php

namespace App\Http\Controllers;

use App\Models\RecentFile;
use Illuminate\Http\JsonResponse;

class RecentFileController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();

        // Load user's file-open preferences
        $prefs = $user->preferences()
            ->whereIn('name', ['file_open_to', 'file_recent_spot'])
            ->pluck('setting', 'name');

        $fileOpenTo = $prefs['file_open_to'] ?? 'correspondence';
        $fileRecentSpot = ($prefs['file_recent_spot'] ?? 'false') === 'true';

        $recentFiles = RecentFile::where('user_id', $user->id)
            ->orderByDesc('last_opened_at')
            ->with('file:id,name')
            ->get()
            ->filter(fn ($recentFile) => $recentFile->file !== null)
            ->map(fn ($recentFile) => [
                'id' => $recentFile->file->id,
                'name' => $recentFile->file->name,
                'filepart' => $fileRecentSpot ? $recentFile->filepart : $fileOpenTo,
                'page' => $fileRecentSpot ? $recentFile->page : 1,
                'show' => $recentFile->show,
            ])
            ->values();

        return response()->json($recentFiles);
    }
}
