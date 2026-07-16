<?php

namespace App\Tenancy;

use App\Models\Church;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The tenant-isolation core (CLAUDE.md rules 1–3). Applied to every church-scoped
 * data-root model. When a church is bound (TenantContext::enforced()):
 *   - reads are filtered to that church, and
 *   - inserts are auto-stamped with that church_id,
 * so a controller can neither read nor write across the tenant boundary.
 *
 * When no church is bound (production while MULTI_TENANT=false, and ordinary tests),
 * both behaviours no-op — identical to the pre-tenancy app. Superadmin cross-church
 * code paths use `Model::withoutGlobalScope('church')`.
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
            if (TenantContext::enforced() && empty($model->getAttribute('church_id'))) {
                $model->setAttribute('church_id', TenantContext::id());
            }
        });
    }

    public function church()
    {
        return $this->belongsTo(Church::class, 'church_id', 'church_id');
    }
}
