<?php

namespace App\Console\Commands;

use App\Models\ProjectAssessment;
use App\Services\ProjectTeamWorkflowService;
use Illuminate\Console\Command;

class NotifyProjectJoinClosedCommand extends Command
{
    protected $signature = 'projects:notify-join-closed';

    protected $description = 'Email course admins when a project join window has closed so they can settle teams';

    public function handle(ProjectTeamWorkflowService $workflow): int
    {
        $assessments = ProjectAssessment::query()
            ->whereNotNull('join_closes_at')
            ->where('join_closes_at', '<=', now())
            ->whereNull('join_close_admin_notified_at')
            ->where('is_published', true)
            ->get();

        foreach ($assessments as $assessment) {
            $workflow->notifyJoinClosedIfNeeded($assessment);
        }

        $this->info('Checked '.$assessments->count().' closed join window(s).');

        return self::SUCCESS;
    }
}
