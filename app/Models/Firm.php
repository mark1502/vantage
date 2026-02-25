<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Firm extends Model
{
    use HasFactory;

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function hasDocumentBasePath(): bool
    {
        return ! empty($this->document_base_path);
    }
}
