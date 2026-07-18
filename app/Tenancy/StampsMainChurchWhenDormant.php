<?php

namespace App\Tenancy;

use App\Models\Church;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * When MULTI_TENANT is off, BelongsToChurch does not stamp church_id. T5 models still
 * need Tenant Zero ownership — stamp main church on create if unset.
 */
trait StampsMainChurchWhenDormant
{
    protected static function bootStampsMainChurchWhenDormant(): void
    {
        static::creating(function (Model $model) {
            if (! empty($model->getAttribute('church_id'))) {
                return;
            }

            if (TenantContext::enforced()) {
                return; // BelongsToChurch stamps
            }

            if (! Schema::hasTable('church')) {
                return;
            }

            $mainId = Church::query()->where('slug', config('tenancy.main_slug'))->value('church_id');
            if ($mainId) {
                $model->setAttribute('church_id', $mainId);
            }
        });
    }
}
