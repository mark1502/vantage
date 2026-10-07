<?php

namespace App\Rules;

use App\Models\Response;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SingleFullResponse implements ValidationRule
{
    public function __construct(private ?int $respondsToId, private ?int $currentEntryId) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== 'F' || empty($this->respondsToId)) {
            return;
        }

        $taken = Response::where('response_to', $this->respondsToId)
            ->where('response_type', 'F')
            ->when($this->currentEntryId, fn ($query, $id) => $query->where('entry_id', '!=', $id))
            ->exists();

        if ($taken) {
            $fail('That entry already has a full response.');
        }
    }
}
