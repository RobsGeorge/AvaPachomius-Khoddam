<?php

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;

/**
 * Structural guards for staging deploy safety (pre-git reclaim + least-privilege sudo).
 * Pure file assertions — no Laravel bootstrap required.
 */
class StagingDeployWorkflowGuardTest extends TestCase
{
    private string $stagingWorkflow;

    private string $productionWorkflow;

    private string $deployDocs;

    private string $permsHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 3);
        $this->stagingWorkflow = $root.'/.github/workflows/deploy-staging.yml';
        $this->productionWorkflow = $root.'/.github/workflows/deploy.yml';
        $this->deployDocs = $root.'/.github/DEPLOY-VPS.md';
        $this->permsHelper = $root.'/scripts/vps/avapakhomios-deploy-perms.sh';
    }

    public function test_staging_workflow_reclaims_storage_before_git_reset(): void
    {
        $body = $this->read($this->stagingWorkflow);

        $reclaimPos = strpos($body, 'reclaim storage for git');
        $gitSyncPos = strpos($body, 'step "git sync"');
        $resetPos = strpos($body, 'git reset --hard origin/staging');

        $this->assertNotFalse($reclaimPos, 'missing reclaim storage step');
        $this->assertNotFalse($gitSyncPos, 'missing git sync step');
        $this->assertNotFalse($resetPos, 'missing git reset');
        $this->assertLessThan($gitSyncPos, $reclaimPos, 'reclaim must run before git sync');
        $this->assertLessThan($resetPos, $reclaimPos + 1, 'reclaim marker must precede reset');
        $this->assertLessThan($resetPos, $gitSyncPos);
    }

    public function test_staging_workflow_uses_least_privilege_perms_helper(): void
    {
        $body = $this->read($this->stagingWorkflow);

        $this->assertStringContainsString('avapakhomios-deploy-perms', $body);
        $this->assertStringContainsString('sudo -n "$DEPLOY_FIX_PERMS" "$(pwd)" "${DEPLOY_USER}:www-data"', $body);
        $this->assertStringContainsString('Do NOT grant bare chown/chmod/systemctl', $body);
        $this->assertStringNotContainsString('NOPASSWD: /usr/bin/chown, /usr/bin/chmod', $body);
    }

    public function test_staging_workflow_ensures_file_cache_data_directory(): void
    {
        $body = $this->read($this->stagingWorkflow);
        $this->assertStringContainsString('mkdir -p storage/framework/cache/data', $body);
    }

    public function test_deploy_workflows_set_umask_for_group_write(): void
    {
        foreach ([$this->stagingWorkflow, $this->productionWorkflow] as $path) {
            $body = $this->read($path);
            $this->assertStringContainsString('umask 0002', $body, basename($path).' must set umask 0002');
        }
    }

    public function test_deploy_docs_cover_chmod_operation_not_permitted(): void
    {
        $docs = $this->read($this->deployDocs);
        $this->assertStringContainsString('chmod(): Operation not permitted', $docs);
        $this->assertStringContainsString('umask 0002', $docs);
    }

    public function test_production_workflow_also_reclaims_before_git_reset(): void
    {
        $body = $this->read($this->productionWorkflow);

        $reclaimPos = strpos($body, 'reclaim storage for git');
        $resetPos = strpos($body, 'git reset --hard');

        $this->assertNotFalse($reclaimPos);
        $this->assertNotFalse($resetPos);
        $this->assertLessThan($resetPos, $reclaimPos);
    }

    public function test_production_workflow_uses_least_privilege_perms_helper(): void
    {
        $body = $this->read($this->productionWorkflow);

        $this->assertStringContainsString('avapakhomios-deploy-perms', $body);
        $this->assertStringContainsString('sudo -n "$DEPLOY_FIX_PERMS" "$(pwd)" "${DEPLOY_USER}:www-data"', $body);
        $this->assertStringContainsString('systemctl reload php8.2-fpm', $body);
        $this->assertStringNotContainsString('NOPASSWD: /usr/bin/chown, /usr/bin/chmod', $body);
        $this->assertStringNotContainsString('/usr/bin/systemctl, /bin/systemctl', $body);
    }

    public function test_deploy_docs_document_least_privilege_sudoers(): void
    {
        $docs = $this->read($this->deployDocs);

        $this->assertMatchesRegularExpression('/reclaim|before git/i', $docs);
        $this->assertStringContainsString('/usr/local/sbin/avapakhomios-deploy-perms', $docs);
        $this->assertStringContainsString('/usr/bin/systemctl reload php8.2-fpm', $docs);
        $this->assertStringContainsString('Do **not** grant passwordless sudo on the full', $docs);
        $this->assertStringNotContainsString(
            'NOPASSWD: /usr/bin/chown, /usr/bin/chmod, /bin/chown, /bin/chmod, /usr/bin/systemctl, /bin/systemctl',
            $docs
        );
    }

    public function test_perms_helper_restricts_app_roots(): void
    {
        $script = $this->read($this->permsHelper);

        $this->assertStringContainsString('/var/www/avapakhomios', $script);
        $this->assertStringContainsString('/var/www/khedma-staging', $script);
        $this->assertStringContainsString('Refusing unknown app root', $script);
        $this->assertStringContainsString('storage', $script);
        $this->assertStringContainsString('bootstrap/cache', $script);
        $this->assertStringContainsString('*.unwritable.*', $script);
    }

    private function read(string $path): string
    {
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
