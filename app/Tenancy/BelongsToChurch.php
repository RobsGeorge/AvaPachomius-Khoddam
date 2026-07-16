<?php

namespace App\Tenancy;

use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant-isolation core (CLAUDE.md rules 1–3). Applied to every church-scoped
 * data-root model. When a church is bound (TenantContext::enforced()):
 *   - reads are filtered to that church, and
 *   - inserts are auto-stamped with that church_id,
 * so a controller can neither read nor write across the tenant boundary.
 *
 * When no church is bound (MULTI_TENANT=false): the read scope no-ops, but inserts
 * still stamp Tenant Zero so NOT NULL church_id (T7 contract) never breaks dormant mode.
 * Superadmin cross-church code paths use `Model::withoutGlobalScope('church')`.
 */
trait BelongsToChurch
{
    public static function bootBelongsToChurch(): void
    {
        static::addGlobalScope('church', function (Builder $query) {
            if (TenantContext::enforced()) {
                $model = $query->getModel();
                $query->where($model->getTable().'.church_id', TenantContext::id());
            }
        });

        static::creating(function (Model $model) {
            if (! empty($model->getAttribute('church_id'))) {
                return;
            }

            if (TenantContext::enforced()) {
                $model->setAttribute('church_id', TenantContext::id());

                return;
            }

            // T7 contract: church_id is NOT NULL. While MULTI_TENANT=false, stamp Tenant Zero.
            if (! Schema::hasTable('church')) {
                return;
            }

            $mainId = Church::query()->where('slug', config('tenancy.main_slug'))->value('church_id');
            if ($mainId) {
                $model->setAttribute('church_id', $mainId);
            }
        });
    }

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }
}
