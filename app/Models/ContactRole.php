<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactRole extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_protected' => 'boolean',
        'is_file_attorney' => 'boolean',
        'is_file_client' => 'boolean',
    ];

    const ROLE_LABELS = [
        'client' => 'Client',
        'opposing_party' => 'Opposing Party',
        'attorney_for_client' => 'Attorney for Client',
        'opposing_counsel' => 'Opposing Counsel',
        'witness' => 'Witness',
        'expert_witness' => 'Expert Witness',
        'medical_provider' => 'Medical Provider',
        'medical_facility' => 'Medical Facility',
        'insurance_adjuster' => 'Insurance Adjuster',
        'court_reporter' => 'Court Reporter',
        'other' => 'Other',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the display label for this role.
     * Returns role_label if set, otherwise falls back to ROLE_LABELS constant.
     */
    public function getRoleLabelAttribute($value)
    {
        return $value ?: (self::ROLE_LABELS[$this->role] ?? $this->role);
    }

    public function scopeClients($query)
    {
        return $query->where('role', 'client');
    }

    public function scopeAttorneysForClient($query)
    {
        return $query->where('role', 'attorney_for_client');
    }

    public function scopeOpposingParties($query)
    {
        return $query->where('role', 'opposing_party');
    }

    public function scopeOpposingCounsel($query)
    {
        return $query->where('role', 'opposing_counsel');
    }

    public function scopeProtected($query)
    {
        return $query->where('is_protected', true);
    }

    public function scopeFileAttorney($query)
    {
        return $query->where('is_file_attorney', true);
    }

    public function scopeFileClient($query)
    {
        return $query->where('is_file_client', true);
    }
}
