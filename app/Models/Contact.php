<?php

namespace App\Models;

use App\Models\Firm;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Contact Model
 *
 * Represents both external contacts and internal firm members.
 * - External contacts: is_firm_member = false
 * - Internal firm members: is_firm_member = true, has user_id
 *
 * IMPORTANT: The 'current' field tracks active/inactive status for firm members.
 * - 'C' = Current/Active
 * - 'I' = Inactive (or other value)
 *
 * For firm members, this is the single source of truth for active status.
 * Use User::isActive() or User::scopeActive() to check user status.
 */
class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['firm_id'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function firm() {
        return $this->belongsTo(Firm::class);
    }

    /**
     * Check if this contact is a current/active firm member.
     *
     * @return bool
     */
    public function isCurrent(): bool
    {
        return $this->current === 'C';
    }

    /**
     * Scope query to only current/active contacts.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrent($query)
    {
        return $query->where('current', 'C');
    }

    /**
     * Scope query to only firm members.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFirmMembers($query)
    {
        return $query->where('is_firm_member', true);
    }

    /**
     * Scope query to only external contacts (non-firm members).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExternalContacts($query)
    {
        return $query->where('is_firm_member', false);
    }

    public function files() {
        return $this->belongsToMany(File::class, 'contact_roles') ;
    }

    public function fileRoles()
    {
        return $this->hasMany(ContactRole::class);
    }

    // Many-to-many relationship with pivot data
    public function filesWithRoles()
    {
        return $this->belongsToMany(File::class, 'contact_roles')
            ->withPivot(['role_id', 'is_client', 'is_attorney'])
            ->withTimestamps();
    }
}
