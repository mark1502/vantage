<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Firm;
use App\Models\Contact;
use App\Models\Preference;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        // custom fields
        'user_type',
        'firm_id',
        'welcomed',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'firm_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function firm() {
        return $this->belongsTo(Firm::class);
    }

    public function contact() {
        return $this->hasOne(Contact::class);
    }

    public function preferences()
    {
        return $this->hasMany(Preference::class);
    }

    /**
     * Check if the user is currently active.
     * Active status is tracked via the contact's 'current' field ('C' = current/active).
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->contact && $this->contact->current === 'C';
    }

    /**
     * Scope query to only include active/current users.
     * Uses the contact's 'current' field to determine status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereHas('contact', function($q) {
            $q->where('current', 'C');
        });
    }

    /**
     * Alias for scopeActive - scope to current users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrent($query)
    {
        return $this->scopeActive($query);
    }

    /**
     * Get the user's member initials from their contact record.
     *
     * @return string|null
     */
    public function memberInitials(): ?string
    {
        return $this->contact?->member_initials;
    }

    /**
     * Get the user's firm role from their contact record.
     *
     * @return string|null
     */
    public function firmRole(): ?string
    {
        return $this->contact?->firm_role;
    }


}
