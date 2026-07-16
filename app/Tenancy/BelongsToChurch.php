<?php

namespace App\Tenancy;

use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-isolation core (CLAUDE.md rules 1–3). Filters reads by
 * app(TenantContext::class)->churchId() and stamps church_id on create.
 *
 * Models that keep platform-wide NULL church_id rows (e.g. role templates) may
 * override churchScopeAllowsNullTemplates() to include those rows.
 */
trait BelongsToChurch
{
    public static function bootBelongsToChurch(): void
    {
        static::addGlobalScope('church', function (Builder $query) {
            if (! TenantContext::enforced()) {
                return;
            }

            $churchId = app(TenantContext::class)->churchId() ?? TenantContext::id();
            if ($churchId === null) {
                return;
            }

            $column = $query->getModel()->getTable().'.church_id';

            if (static::churchScopeAllowsNullTemplates()) {
                $query->where(function (Builder $inner) use ($column, $churchId) {
                    $inner->whereNull($column)->orWhere($column, $churchId);
                });

                return;
            }

            $query->where($column, $churchId);
        });

        static::creating(function (Model $model) {
            if (! empty($model->getAttribute('church_id'))) {
                return;
            }

            $churchId = app(TenantContext::class)->churchId() ?? TenantContext::id();

            if ($churchId !== null) {
                $model->setAttribute('church_id', $churchId);

                return;
            }

            // Safety net when context is unbound (tests / console): stamp Tenant Zero.
            if (! Schema::hasTable('church')) {
                return;
            }

            $mainId = Church::query()->where('slug', config('tenancy.main_slug'))->value('church_id');
            if ($mainId) {
                $model->setAttribute('church_id', $mainId);
            }
        });
    }

    /** Override to true for models that keep null church_id platform templates. */
    protected static function churchScopeAllowsNullTemplates(): bool
    {
        return false;
    }

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }

    /**
     * Explicit cross-tenant escape hatch (CLAUDE.md rule 3). Callers must justify.
     */
    public function scopeWithoutTenancy(Builder $query): Builder
    {
        return $query->withoutGlobalScope('church');
    }
}
