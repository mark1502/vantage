<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFirm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Contact Model
 *
 * Represents both external contacts and internal firm members.
 * - External contacts: is_firm_member = false
 * - Internal firm members: is_firm_member = true, has user_id
 *
 * IMPORTANT: The 'account_status' field tracks status for all contacts.
 * - 'A' = Active
 * - 'I' = Inactive
 * - 'N' = Normal (default for external contacts)
 *
 * For firm members, this is the single source of truth for active status.
 * Use User::isActive() or User::scopeActive() to check user status.
 */
class Contact extends Model
{
    use BelongsToFirm, HasFactory;

    protected $guarded = [];

    protected $hidden = ['firm_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function firm()
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * Check if this contact is active.
     */
    public function isActive(): bool
    {
        return $this->account_status === 'A';
    }

    /**
     * Scope query to only active contacts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('account_status', 'A');
    }

    /**
     * Scope query to only firm members.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFirmMembers($query)
    {
        return $query->where('is_firm_member', true);
    }

    /**
     * Scope query to only external contacts (non-firm members).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExternalContacts($query)
    {
        return $query->where('is_firm_member', false);
    }

    /**
     * Scope query to exclude faux-deleted contacts.
     */
    public function scopeNotFauxDeleted($query)
    {
        return $query->where('faux_deleted', false);
    }

    public function files()
    {
        return $this->belongsToMany(File::class, 'contact_roles');
    }

    public function fileRoles()
    {
        return $this->hasMany(ContactRole::class);
    }

    // Many-to-many relationship with pivot data
    public function filesWithRoles()
    {
        return $this->belongsToMany(File::class, 'contact_roles')
            ->withPivot(['role', 'role_label'])
            ->withTimestamps();
    }
}
