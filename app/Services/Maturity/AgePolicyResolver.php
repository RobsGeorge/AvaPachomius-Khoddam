<?php

namespace App\Services\Maturity;

use App\Models\AgePolicy;
use App\Models\Church;
use App\Models\Organization;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves digital-consent and majority ages from organizations age_policies,
 * walking parent diocese/jurisdiction. Falls back to config/maturity.php only.
 */
final class AgePolicyResolver
{
    /**
     * @return array{digital_consent_age: int, age_of_majority: int, organization_id: int|null, source: string}
     */
    public function forChurch(?Church $church): array
    {
        $defaults = $this->defaults();

        if (! $church || ! Schema::hasTable('age_policies')) {
            return $defaults + ['organization_id' => null, 'source' => 'config'];
        }

        $orgId = $church->organization_id;
        if (! $orgId && Schema::hasTable('organizations')) {
            $orgId = Organization::query()->where('subdomain', $church->slug)->value('organization_id');
        }

        if (! $orgId) {
            return $defaults + ['organization_id' => null, 'source' => 'config'];
        }

        $currentId = (int) $orgId;
        $guard = 0;
        while ($currentId > 0 && $guard < 32) {
            $policy = AgePolicy::query()->where('organization_id', $currentId)->first();
            if ($policy) {
                return [
                    'digital_consent_age' => (int) $policy->digital_consent_age,
                    'age_of_majority' => (int) $policy->age_of_majority,
                    'organization_id' => $currentId,
                    'source' => 'organization',
                ];
            }

            $parentId = Organization::query()
                ->where('organization_id', $currentId)
                ->value('parent_id');
            if (! $parentId) {
                break;
            }
            $currentId = (int) $parentId;
            $guard++;
        }

        return $defaults + ['organization_id' => (int) $orgId, 'source' => 'config'];
    }

    /**
     * @return array{digital_consent_age: int, age_of_majority: int}
     */
    public function defaults(): array
    {
        return [
            'digital_consent_age' => (int) config('maturity.defaults.digital_consent_age'),
            'age_of_majority' => (int) config('maturity.defaults.age_of_majority'),
        ];
    }
}
