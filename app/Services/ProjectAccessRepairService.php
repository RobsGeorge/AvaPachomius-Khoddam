<?php

namespace App\Services;

use App\Models\Church;
use App\Models\ChurchCapability;
use Illuminate\Support\Facades\Schema;

/**
 * Production repair: enable the projects capability and merge missing
 * project.* keys onto existing course role clones. Expand-only.
 */
class ProjectAccessRepairService
{
    public function __construct(private RoleTemplateService $templates) {}

    public function repair(): int
    {
        $this->enableProjectsCapability();
        $this->templates->ensureSystemTemplates();

        return $this->templates->mergeTemplatePermissionsIntoCourseClones();
    }

    public function enableProjectsCapability(): int
    {
        if (! Schema::hasTable('church') || ! Schema::hasTable('church_capability')) {
            return 0;
        }

        $enabled = 0;
        foreach (Church::query()->orderBy('church_id')->get() as $church) {
            $row = ChurchCapability::query()
                ->where('church_id', $church->church_id)
                ->where('capability_key', 'projects')
                ->first();

            if ($row === null) {
                ChurchCapability::create([
                    'church_id' => $church->church_id,
                    'capability_key' => 'projects',
                    'enabled' => true,
                    'config' => [],
                ]);
                $enabled++;
                $church->unsetRelation('capabilities');

                continue;
            }

            if (! $row->enabled) {
                $row->update(['enabled' => true]);
                $enabled++;
                $church->unsetRelation('capabilities');
            }
        }

        return $enabled;
    }
}
