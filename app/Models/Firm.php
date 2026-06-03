<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Firm extends Model
{
    use Billable, HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class)
            ->withPivot('activated_at', 'expires_at')
            ->withTimestamps();
    }

    public function hasDocumentBasePath(): bool
    {
        return ! empty($this->document_base_path);
    }

    /**
     * Resolve the configured allow-list of document base roots to canonical paths.
     *
     * An empty result means no allow-list is configured (see config/documents.php),
     * in which case only the application-directory exclusion in safeDocumentBasePath
     * applies.
     *
     * @return list<string>
     */
    public static function allowedDocumentRoots(): array
    {
        $roots = [];

        foreach ((array) config('documents.allowed_roots', []) as $root) {
            $resolved = realpath($root);

            if ($resolved !== false) {
                $roots[] = $resolved;
            }
        }

        return $roots;
    }

    /**
     * Validate a candidate document base path and return its canonical form.
     *
     * A path is only considered safe when it:
     *   - resolves to an existing directory;
     *   - is neither inside nor an ancestor of the application directory
     *     (protects source code, .env, and other app-server files); and
     *   - lives under one of the configured allowed roots, when an allow-list
     *     is configured.
     *
     * Returns the realpath on success, or null when the path is missing,
     * non-existent, or disallowed. This is the single source of truth shared by
     * UpdateFirmRequest (write-time) and the directory/file readers (read-time).
     */
    public static function safeDocumentBasePath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $resolved = realpath($path);

        if ($resolved === false || ! is_dir($resolved)) {
            return null;
        }

        $appDir = realpath(base_path()) ?: base_path();

        if (self::pathContains($resolved, $appDir) || self::pathContains($appDir, $resolved)) {
            return null;
        }

        $allowedRoots = self::allowedDocumentRoots();

        if ($allowedRoots !== []) {
            foreach ($allowedRoots as $root) {
                if (self::pathContains($resolved, $root)) {
                    return $resolved;
                }
            }

            return null;
        }

        return $resolved;
    }

    /**
     * Determine whether $candidate is equal to, or located inside, $ancestor.
     *
     * Uses a separator-terminated prefix so that sibling directories sharing a
     * name prefix (e.g. "/data/firmdocs" vs "/data/firmdocs-evil") are not
     * treated as contained. Works for both "/" and "\" separators.
     */
    protected static function pathContains(string $candidate, string $ancestor): bool
    {
        if ($candidate === $ancestor) {
            return true;
        }

        $ancestorWithSep = rtrim($ancestor, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($candidate, $ancestorWithSep);
    }

    public function syncSubscriptionQuantity(): void
    {
        if (! $this->subscribed('default')) {
            return;
        }

        $seatCount = Contact::where('firm_id', $this->id)
            ->firmMembers()
            ->active()
            ->count();

        $this->subscription('default')->updateQuantity($seatCount);
    }

    public function fileCount(): int
    {
        return File::where('firm_id', $this->id)->count();
    }

    public function fileLimit(): ?int
    {
        return $this->subscribed('default') ? null : 10;
    }

    public function canCreateFiles(): bool
    {
        $limit = $this->fileLimit();

        return $limit === null || $this->fileCount() < $limit;
    }
}
