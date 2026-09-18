<?php

namespace App\Console\Commands;

use App\Services\ProjectExampleSeedService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seed the separate example project assessment (three teams, distinct titles
 * and requirements). Idempotent. Refuses production unless --force.
 */
class SeedExampleProjectCommand extends Command
{
    protected $signature = 'projects:seed-example
                            {--course= : course_id to attach (default: latest active course on Tenant Zero / current church)}
                            {--unpublished : Create as a draft instead of publishing}
                            {--force : Allow running when APP_ENV=production}';

    protected $description = 'Seed a separate example project with three teams that have distinct titles and requirements';

    public function handle(ProjectExampleSeedService $seeder): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to seed example project data on production without --force.');

            return self::FAILURE;
        }

        $courseId = $this->option('course') !== null && $this->option('course') !== ''
            ? (int) $this->option('course')
            : null;

        if ($courseId !== null && $seeder->resolveCourse($courseId) === null) {
            $this->error("Course [{$courseId}] was not found.");

            return self::FAILURE;
        }

        $result = $seeder->seed(
            $courseId,
            null,
            ! (bool) $this->option('unpublished'),
        );

        if ($result === null) {
            $this->error('No eligible course (or approved actor) was found. Create an active course first.');

            return self::FAILURE;
        }

        $assessment = $result['assessment'];
        $verb = $result['created'] ? 'Created' : 'Already present';
        $this->info(sprintf(
            '%s example project assessment #%d “%s” on course #%d (%s).',
            $verb,
            $assessment->project_assessment_id,
            $assessment->title,
            $assessment->course_id,
            $assessment->is_published ? 'published' : 'draft',
        ));

        $rows = $assessment->projects
            ->map(fn ($project) => [
                $project->sort_order,
                $project->title,
                Str::limit((string) $project->requirements, 80),
            ])
            ->all();
        $this->table(['#', 'Team title', 'Requirements'], $rows);

        return self::SUCCESS;
    }
}
