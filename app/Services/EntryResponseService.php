<?php

namespace App\Services;

use App\Models\ContactRole;
use App\Models\Entry;
use App\Models\Response;

class EntryResponseService
{
    /**
     * @param  array<int, array{contact_id: int, role: string, role_label?: string|null}>|null  $pendingRoles
     */
    public function savePendingContactRoles(int $fileId, ?array $pendingRoles): void
    {
        if (empty($pendingRoles)) {
            return;
        }

        foreach ($pendingRoles as $pendingRole) {
            ContactRole::firstOrCreate(
                [
                    'file_id' => $fileId,
                    'contact_id' => $pendingRole['contact_id'],
                    'role' => $pendingRole['role'],
                ],
                [
                    'role_label' => $pendingRole['role_label'] ?? ContactRole::ROLE_LABELS[$pendingRole['role']] ?? $pendingRole['role'],
                ]
            );
        }
    }

    /**
     * Recompute expecting_response for one entry from its due date and its F responses.
     * Never touches date_response_expected; events never track expecting_response.
     */
    public function refreshExpecting(int $entryId): void
    {
        $entry = Entry::find($entryId);

        if (! $entry || $entry->folder_id === 6) {
            return;
        }

        $entry->expecting_response = ! empty($entry->date_response_expected)
            && ! Response::where('response_to', $entryId)->where('response_type', 'F')->exists();
        $entry->save();
    }

    /**
     * Apply the response choice from the entry form (store and update).
     */
    public function sync(Entry $entry, ?string $type, ?int $respondsToId, ?string $responseDate): void
    {
        if ($type === null) {
            return;
        }

        if ($type === 'N') {
            $this->remove($entry);

            return;
        }

        if (empty($respondsToId)) {
            return;
        }

        $oldTarget = Response::where('entry_id', $entry->id)->value('response_to');

        Response::updateOrCreate(
            ['entry_id' => $entry->id],
            ['response_to' => $respondsToId, 'response_date' => $responseDate, 'response_type' => $type],
        );

        $this->refreshExpecting($respondsToId);

        if ($oldTarget && (int) $oldTarget !== $respondsToId) {
            $this->refreshExpecting((int) $oldTarget);
        }
    }

    /**
     * Remove this entry's response (changed to "not a response", or entry being deleted).
     */
    public function remove(Entry $entry): void
    {
        $response = Response::where('entry_id', $entry->id)->first();

        if (! $response) {
            return;
        }

        $target = (int) $response->response_to;
        $response->delete();
        $this->refreshExpecting($target);
    }
}
