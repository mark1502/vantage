<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use BelongsToFirm, HasFactory;

    // protected $dates = ['deleted_at'];  No more softDeletes

    protected $guarded = [];

    protected $hidden = ['firm_id'];

    /**
     * Scope to the caller's firm, but always keep the shared reserved
     * (non-file-specific) file visible so every firm can attach entries to it.
     */
    protected static function applyFirmScope(Builder $builder, int $firmId): void
    {
        $builder->where(function (Builder $query) use ($firmId): void {
            $query->where('files.firm_id', $firmId)
                ->orWhere('files.id', config('documents.reserved_file_id'));
        });
    }

    public function firm()
    {
        return $this->belongsTo(Firm::class);
    }

    public function filetype()
    {
        return $this->belongsTo(Filetype::class);
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function contactRoles()
    {
        return $this->hasMany(ContactRole::class);
    }

    // Helper to get the client contact role
    public function client()
    {
        return $this->hasOne(ContactRole::class)
            ->where('is_file_client', true);
    }

    // Helper to get the assigned attorney contact role
    public function assignedAttorney()
    {
        return $this->hasOne(ContactRole::class)
            ->where('is_file_attorney', true);
    }

    // Helper to get opposing counsel contact role
    public function opposingCounsel()
    {
        return $this->hasOne(ContactRole::class)
            ->where('role', 'opposing_counsel');
    }

    // Many-to-many relationship with pivot data
    public function contactsWithRoles()
    {
        return $this->belongsToMany(Contact::class, 'contact_roles')
            ->withPivot(['role', 'role_label', 'is_file_attorney', 'is_file_client'])
            ->withTimestamps();
    }

    public function syncSolCalendarEntry(): void
    {
        $solEntrytype = Entrytype::query()
            ->where('firm_id', $this->firm_id)
            ->where('folder_id', 6)
            ->where('name', 'Deadline - Statute of Limitations')
            ->first();

        if (! $solEntrytype) {
            return;
        }

        $existingEntry = Entry::query()
            ->where('file_id', $this->id)
            ->where('entrytype_id', $solEntrytype->id)
            ->first();

        $attorneyContactId = ContactRole::query()
            ->where('file_id', $this->id)
            ->where('is_file_attorney', true)
            ->value('contact_id');

        if (empty($this->date_sol)) {
            if ($existingEntry) {
                $existingEntry->delete();
            }

            return;
        }

        if ($existingEntry) {
            $existingEntry->date1 = $this->date_sol;
            $existingEntry->from_contact_id = $attorneyContactId;
            $existingEntry->to_contact_id = $attorneyContactId;
            $existingEntry->note = $this->name;
            $existingEntry->save();
        } else {
            $entry = new Entry;
            $entry->firm_id = $this->firm_id;
            $entry->file_id = $this->id;
            $entry->folder_id = 6;
            $entry->entrytype_id = $solEntrytype->id;
            $entry->date1 = $this->date_sol;
            $entry->all_day = true;
            $entry->on_calendar = true;
            $entry->from_contact_id = $attorneyContactId;
            $entry->to_contact_id = $attorneyContactId;
            $entry->note = $this->name;
            $entry->save();
        }
    }
}
