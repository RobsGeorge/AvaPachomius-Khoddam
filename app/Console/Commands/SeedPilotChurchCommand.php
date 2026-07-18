<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\User;
use App\Services\ChurchProvisioningService;
use Illuminate\Console\Command;

/**
 * T7 / P6 — provision a contrasting second church for the multi-tenant pilot.
 * Does not flip MULTI_TENANT; enable that in staging .env after DNS/TLS is ready.
 */
class SeedPilotChurchCommand extends Command
{
    protected $signature = 'tenancy:seed-pilot-church
                            {slug=pilot-service : Church subdomain slug}
                            {--name= : Display name}
                            {--admin= : Existing user email to grant church-admin}';

    protected $description = 'Provision a contrasting pilot church (limited capabilities) for T7 cutover';

    public function handle(ChurchProvisioningService $provisioning): int
    {
        $slug = strtolower((string) $this->argument('slug'));
        $name = (string) ($this->option('name') ?: 'Pilot Service Church');

        if (Church::where('slug', $slug)->exists()) {
            $this->warn("Church slug [{$slug}] already exists — skipping create.");
            $church = Church::where('slug', $slug)->firstOrFail();
            // Repair pre-fix churches that lack an organizations row (FK target).
            $org = $provisioning->ensureOrganizationLinked($church);
            if ($org) {
                $this->info("Linked organizations #{$org->organization_id} for church #{$church->church_id}.");
            }
        } else {
            $adminIds = [];
            $adminEmail = $this->option('admin');
            if ($adminEmail) {
                $user = User::where('email', $adminEmail)->first();
                if (! $user) {
                    $this->error("Admin email [{$adminEmail}] not found.");

                    return self::FAILURE;
                }
                $adminIds[] = $user->user_id;
            }

            $church = $provisioning->create([
                'slug' => $slug,
                'name' => $name,
                'status' => 'active',
                'capabilities' => (array) config('tenancy.pilot_capabilities'),
            ], $adminIds);

            $this->info("Created pilot church #{$church->church_id} ({$church->slug}).");
        }

        $this->line('Capabilities: '.implode(', ', (array) config('tenancy.pilot_capabilities')));
        $this->line('Next: set MULTI_TENANT=true on staging, SESSION_DRIVER=database, TENANCY_BASE_DOMAIN, then hit {slug}.'.$this->baseDomainHint());

        return self::SUCCESS;
    }

    private function baseDomainHint(): string
    {
        return (string) (config('tenancy.base_domain')
            ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com'));
    }
}
