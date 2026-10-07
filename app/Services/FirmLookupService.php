<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Entrytype;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FirmLookupService
{
    /**
     * Folders with all of the firm's entrytypes, including faux-deleted ones (flag included).
     *
     * @return Collection<int, Folder>
     */
    public function folders(int $firmId): Collection
    {
        return Folder::query()
            ->where('id', '>', 0)
            ->with(['entrytypes' => fn ($query) => $query
                ->select('id', 'folder_id', 'name', 'faux_deleted')
                ->where('firm_id', $firmId)
                ->orderBy('name')])
            ->get();
    }

    /**
     * @return Collection<int, Contact>
     */
    public function firmMembers(int $firmId): Collection
    {
        return $this->activeMembersQuery($firmId)->get();
    }

    /**
     * @return Collection<int, Contact>
     */
    public function attorneys(int $firmId): Collection
    {
        return $this->activeMembersQuery($firmId)->where('firm_role', 'Attorney')->get();
    }

    /**
     * Find this firm's entrytype by name in a folder; restore it if faux-deleted, or create it if missing.
     */
    public function findOrRestoreEntrytype(int $firmId, int $folderId, string $name): Entrytype
    {
        $entrytype = Entrytype::query()
            ->where('firm_id', $firmId)
            ->where('folder_id', $folderId)
            ->where('name', $name)
            ->first();

        if ($entrytype === null) {
            return Entrytype::create(['firm_id' => $firmId, 'folder_id' => $folderId, 'name' => $name]);
        }

        if ($entrytype->faux_deleted) {
            $entrytype->update(['faux_deleted' => false]);
        }

        return $entrytype;
    }

    private function activeMembersQuery(int $firmId): Builder
    {
        return Contact::query()
            ->select('display_last_first', 'id', 'account_status', 'firm_role', 'member_initials')
            ->where('firm_id', $firmId)
            ->where('is_firm_member', true)
            ->where('account_status', 'A')
            ->orderBy('display_last_first');
    }
}
