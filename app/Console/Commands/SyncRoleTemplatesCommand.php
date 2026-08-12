<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Services\RoleTemplateService;
use Illuminate\Console\Command;

/**
 * After permissions:sync — refresh platform templates and merge missing keys onto
 * already-cloned church/service roles (expand-only; does not strip custom grants).
 */
class SyncRoleTemplatesCommand extends Command
{
    protected $signature = 'roles:sync-templates {--church= : Limit church clone merge to one church_id}';

    protected $description = 'Ensure role templates match RoleTemplateService and merge missing permission keys onto cloned roles';

    public function handle(RoleTemplateService $templates): int
    {
        $this->info('Refreshing platform role templates…');
        $templates->ensureSystemTemplates();
        $templates->ensureServiceTemplates();
        $templates->ensureChurchTemplates();

        $churchId = $this->option('church');
        $query = Church::query()->orderBy('church_id');
        if ($churchId !== null && $churchId !== '') {
            $query->where('church_id', (int) $churchId);
        }

        $merged = 0;
        foreach ($query->get() as $church) {
            $merged += $templates->mergeTemplatePermissionsIntoChurchClones($church);
        }

        $merged += $templates->mergeTemplatePermissionsIntoServiceClones();

        $this->info("Merged missing template keys into {$merged} cloned role(s).");

        return self::SUCCESS;
    }
}
