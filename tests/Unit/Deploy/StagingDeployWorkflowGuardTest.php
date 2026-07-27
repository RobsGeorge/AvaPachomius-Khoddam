<?php

namespace Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;

/**
 * Structural guards for staging deploy safety (pre-git chown + cache dir).
 * Pure file assertions — no Laravel bootstrap required.
 */
class StagingDeployWorkflowGuardTest extends TestCase
{
    private string $stagingWorkflow;

    private string $productionWorkflow;

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 3);
        $this->stagingWorkflow = $root.'/.github/workflows/deploy-staging.yml';
        $this->productionWorkflow = $root.'/.github/workflows/deploy.yml';
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

    public function test_staging_workflow_chowns_storage_and_bootstrap_cache(): void
    {
        $body = $this->read($this->stagingWorkflow);

        $this->assertStringContainsString('chown -R "${DEPLOY_USER}:www-data" storage bootstrap/cache', $body);
        $this->assertStringContainsString('chmod -R ug+rwx storage bootstrap/cache', $body);
        $this->assertStringContainsString('passwordless sudo not configured', $body);
    }

    public function test_staging_workflow_ensures_file_cache_data_directory(): void
    {
        $body = $this->read($this->stagingWorkflow);
        $this->assertStringContainsString('mkdir -p storage/framework/cache/data', $body);
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

    public function test_deploy_docs_mention_pre_git_reclaim(): void
    {
        $docs = $this->read(dirname(__DIR__, 3).'/.github/DEPLOY-VPS.md');
        $this->assertMatchesRegularExpression('/reclaim|chown.*storage|before git/i', $docs);
    }

    private function read(string $path): string
    {
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
