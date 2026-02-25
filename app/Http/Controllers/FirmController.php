<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFirmRequest;
use App\Models\Firm;
use DirectoryIterator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FirmController extends Controller
{
    public function edit(Request $request)
    {
        if ($request->user()->user_type !== 'Admin') {
            abort(403);
        }

        $firm = Firm::findOrFail($request->user()->firm_id);

        return Inertia::render('Firm/Edit', [
            'firm' => [
                'id' => $firm->id,
                'name' => $firm->name,
                'address' => $firm->address,
                'phone' => $firm->phone,
                'email' => $firm->email,
                'document_base_path' => $firm->document_base_path,
            ],
        ]);
    }

    public function update(UpdateFirmRequest $request)
    {
        if ($request->user()->user_type !== 'Admin') {
            abort(403);
        }

        $firm = Firm::findOrFail($request->user()->firm_id);

        $firm->name = $request->name;
        $firm->address = $request->address;
        $firm->phone = $request->phone;
        $firm->email = $request->email;
        $firm->document_base_path = $request->document_base_path ?: null;
        $firm->save();

        return redirect()->route('firm.edit')->with('success', 'Firm settings saved.');
    }

    public function browseDirectory(Request $request): JsonResponse
    {
        $firm = Firm::findOrFail($request->user()->firm_id);

        if (empty($firm->document_base_path)) {
            return response()->json(['error' => 'No document storage path configured for your firm.'], 422);
        }

        $resolvedBase = realpath($firm->document_base_path);

        if ($resolvedBase === false) {
            return response()->json(['error' => 'The firm document storage path does not exist or is not accessible.'], 422);
        }

        // Normalize the requested sub-path and build the target directory
        $subPath = trim($request->query('path', ''));

        if ($subPath !== '') {
            $subPath = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subPath), DIRECTORY_SEPARATOR);
            $targetPath = $resolvedBase.DIRECTORY_SEPARATOR.$subPath;
        } else {
            $targetPath = $resolvedBase;
        }

        $resolvedTarget = realpath($targetPath);

        // Reject if the directory does not exist
        if ($resolvedTarget === false || ! is_dir($resolvedTarget)) {
            return response()->json(['error' => 'Directory not found.'], 404);
        }

        // Path traversal protection: target must be the base or inside it
        $baseWithSep = rtrim($resolvedBase, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($resolvedTarget !== $resolvedBase && ! str_starts_with($resolvedTarget, $baseWithSep)) {
            return response()->json(['error' => 'Access denied.'], 403);
        }

        // Derive the relative path from the base
        $relativePath = $resolvedTarget === $resolvedBase
            ? ''
            : ltrim(substr($resolvedTarget, strlen($resolvedBase)), DIRECTORY_SEPARATOR);

        // Compute the parent path (null when already at the root)
        if ($relativePath === '') {
            $parentPath = null;
        } else {
            $parentDir = dirname($relativePath);
            $parentPath = ($parentDir === '.' || $parentDir === '') ? '' : $parentDir;
        }

        // Scan the directory, skipping hidden entries (dot-prefix)
        $items = [];

        try {
            $iterator = new DirectoryIterator($resolvedTarget);

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }

                $name = $fileInfo->getFilename();

                if (str_starts_with($name, '.')) {
                    continue;
                }

                $items[] = [
                    'name' => $name,
                    'type' => $fileInfo->isDir() ? 'directory' : 'file',
                    'size' => $fileInfo->isFile() ? $fileInfo->getSize() : null,
                    'modified' => date('Y-m-d H:i', $fileInfo->getMTime()),
                ];
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to read directory contents.'], 500);
        }

        // Sort: directories first, then files, alphabetically within each group
        usort($items, function (array $a, array $b): int {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return response()->json([
            'current_path' => $relativePath,
            'parent' => $parentPath,
            'items' => $items,
        ]);
    }
}
