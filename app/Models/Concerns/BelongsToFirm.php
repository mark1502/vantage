<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds a tenant ("firm") global scope to a model so that, for any
 * authenticated request, queries are automatically constrained to the
 * caller's firm and new records are stamped with it.
 *
 * The scope no-ops when there is no authenticated user (seeders, queue
 * jobs, console commands, pre-login flows), so cross-firm bootstrapping
 * such as copying firm-1 base data during registration keeps working.
 *
 * Models with special visibility rules (e.g. the shared reserved File)
 * may override applyFirmScope().
 */
trait BelongsToFirm
{
    protected static function bootBelongsToFirm(): void
    {
        static::addGlobalScope('firm', function (Builder $builder): void {
            if (auth()->check()) {
                static::applyFirmScope($builder, auth()->user()->firm_id);
            }
        });

        static::creating(function (Model $model): void {
            if (auth()->check() && empty($model->firm_id)) {
                $model->firm_id = auth()->user()->firm_id;
            }
        });
    }

    protected static function applyFirmScope(Builder $builder, int $firmId): void
    {
        $builder->where($builder->getModel()->getTable().'.firm_id', $firmId);
    }
}
