<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    use HasFactory;

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function entrytype()
    {
        return $this->belongsTo(Entrytype::class);
    }

    public function contact_from()
    {
        return $this->belongsTo(Contact::class, 'from_contact_id');
    }

    public function contact_to()
    {
        return $this->belongsTo(Contact::class, 'to_contact_id');
    }

    public function response()
    {
        return $this->hasOne(Response::class);
    }

    public function responses_received()
    {
        return $this->hasMany(Response::class, 'response_to');
    }

    public function firm(): Firm
    {
        return Firm::find($this->firm_id);
    }

    protected function hasLinkedDocument(): Attribute
    {
        return Attribute::make(
            get: fn () => ! empty($this->linked_document_path),
        );
    }

    /**
     * Build the full document path by combining the firm's base path with this entry's relative path.
     * Normalizes directory separators to match the server OS.
     */
    public function fullDocumentPath(): ?string
    {
        if (empty($this->linked_document_path)) {
            return null;
        }

        $firm = $this->firm();

        if (! $firm || empty($firm->document_base_path)) {
            return null;
        }

        $basePath = rtrim($firm->document_base_path, '/\\');
        $relativePath = ltrim($this->linked_document_path, '/\\');

        $fullPath = $basePath.DIRECTORY_SEPARATOR.$relativePath;

        // Normalize separators to match the server OS
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
    }
}
