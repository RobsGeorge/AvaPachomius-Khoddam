<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectSubmissionFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectSubmissionService
{
    private const DISK = 'public';

    private const DIRECTORY = 'project-submissions';

    /**
     * Create or replace the single team submission for one deliverable.
     *
     * @param  array{body?:?string, link_url?:?string, replace_files?:bool}  $data
     * @param  list<UploadedFile>  $files
     */
    public function submit(
        Project $project,
        ProjectDeliverable $deliverable,
        User $user,
        array $data = [],
        array $files = [],
    ): ProjectDeliverableSubmission {
        if ((int) $deliverable->project_id !== (int) $project->project_id) {
            abort(404);
        }

        if (! $deliverable->acceptsSubmissionNow()) {
            throw ValidationException::withMessages([
                'deliverable' => [__('projects.submission_closed')],
            ]);
        }

        $existing = $deliverable->submissionForTeam((int) $project->project_id);
        $body = isset($data['body']) ? trim((string) $data['body']) : null;
        $link = isset($data['link_url']) ? trim((string) $data['link_url']) : null;
        $files = array_values(array_filter($files));

        $this->assertPayloadMatchesType($deliverable, $existing, $body, $link, $files);

        return DB::transaction(function () use ($project, $deliverable, $user, $body, $link, $files, $existing, $data) {
            $submission = ProjectDeliverableSubmission::updateOrCreate(
                [
                    'project_id' => $project->project_id,
                    'project_deliverable_id' => $deliverable->project_deliverable_id,
                ],
                [
                    'project_assessment_id' => $project->project_assessment_id,
                    'submitted_by_user_id' => $user->user_id,
                    'body' => $deliverable->expectsText() || $deliverable->expectsFiles() ? $body : null,
                    'link_url' => $deliverable->expectsLink() ? $link : null,
                    'submitted_at' => now(),
                    'is_late' => $deliverable->isOverdue(),
                ]
            );

            if ($files !== []) {
                $replace = ! $deliverable->allowsMultipleFiles() || (bool) ($data['replace_files'] ?? false);
                if ($replace) {
                    $this->deleteFiles($submission->files()->get());
                }

                $this->assertFileCountWithinLimit($deliverable, $submission, count($files));

                foreach ($files as $file) {
                    $path = $file->store(self::DIRECTORY, self::DISK);
                    ProjectSubmissionFile::create([
                        'project_deliverable_submission_id' => $submission->project_deliverable_submission_id,
                        'uploaded_by_user_id' => $user->user_id,
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size_bytes' => $file->getSize(),
                    ]);
                }
            }

            AuditLogService::recordEvent($existing ? 'project.submission_replaced' : 'project.submission_created', [
                'project_assessment_id' => $project->project_assessment_id,
                'project_id' => $project->project_id,
                'project_deliverable_id' => $deliverable->project_deliverable_id,
                'user_id' => $user->user_id,
                'is_late' => $submission->is_late,
                'file_count' => $submission->files()->count(),
            ]);

            return $submission->fresh(['files', 'submitter']);
        });
    }

    public function deleteFile(ProjectSubmissionFile $file, User $actor): void
    {
        $submission = $file->submission;
        $this->deleteFiles(collect([$file]));

        AuditLogService::recordEvent('project.submission_file_deleted', [
            'project_deliverable_submission_id' => $file->project_deliverable_submission_id,
            'project_id' => $submission?->project_id,
            'actor_user_id' => $actor->user_id,
        ]);
    }

    /**
     * Per-deliverable state for the student checklist and the manage dashboard.
     *
     * @return list<array{
     *     deliverable: ProjectDeliverable,
     *     submission: ?ProjectDeliverableSubmission,
     *     submitted: bool,
     *     late: bool,
     *     overdue: bool,
     *     open: bool,
     * }>
     */
    public function checklist(Project $project): array
    {
        $deliverables = $project->relationLoaded('deliverables')
            ? $project->deliverables
            : $project->deliverables()->get();

        $submissions = ProjectDeliverableSubmission::query()
            ->where('project_id', $project->project_id)
            ->with('files', 'submitter')
            ->get()
            ->keyBy('project_deliverable_id');

        $rows = [];
        foreach ($deliverables as $deliverable) {
            $submission = $submissions->get($deliverable->project_deliverable_id);
            $rows[] = [
                'deliverable' => $deliverable,
                'submission' => $submission,
                'submitted' => (bool) $submission,
                'late' => (bool) $submission?->is_late,
                'overdue' => $deliverable->isOverdue(),
                'open' => $deliverable->acceptsSubmissionNow(),
            ];
        }

        return $rows;
    }

    /**
     * @return array{required:int, submitted:int, missing:int, late:int}
     */
    public function progress(Project $project): array
    {
        $required = 0;
        $requiredSubmitted = 0;
        $submitted = 0;
        $late = 0;

        foreach ($this->checklist($project) as $row) {
            $isRequired = (bool) $row['deliverable']->is_required;
            $required += $isRequired ? 1 : 0;

            if (! $row['submitted']) {
                continue;
            }

            $submitted++;
            $requiredSubmitted += $isRequired ? 1 : 0;
            $late += $row['late'] ? 1 : 0;
        }

        return [
            'required' => $required,
            'submitted' => $submitted,
            'missing' => max($required - $requiredSubmitted, 0),
            'late' => $late,
        ];
    }

    public function deleteForProject(Project $project): void
    {
        $submissions = ProjectDeliverableSubmission::query()
            ->where('project_id', $project->project_id)
            ->with('files')
            ->get();

        foreach ($submissions as $submission) {
            $this->deleteFiles($submission->files);
            $submission->delete();
        }
    }

    public function deleteForAssessment(ProjectAssessment $assessment): void
    {
        foreach ($assessment->projects as $project) {
            $this->deleteForProject($project);
        }
    }

    /**
     * Save instructor feedback on a team submission. Notifies active members
     * the first time feedback is stored (not on later edits).
     */
    public function saveInstructorFeedback(
        ProjectDeliverableSubmission $submission,
        User $reviewer,
        string $feedback,
    ): ProjectDeliverableSubmission {
        $feedback = trim($feedback);
        $wasFirst = ! $submission->hasInstructorFeedback();

        $submission->update([
            'instructor_feedback' => $feedback === '' ? null : $feedback,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->user_id,
        ]);

        AuditLogService::recordEvent('project.submission_reviewed', [
            'project_deliverable_submission_id' => $submission->project_deliverable_submission_id,
            'project_id' => $submission->project_id,
            'project_deliverable_id' => $submission->project_deliverable_id,
            'actor_user_id' => $reviewer->user_id,
            'first_feedback' => $wasFirst && $feedback !== '',
        ]);

        if ($wasFirst && $feedback !== '') {
            app(ProjectNotificationService::class)->notifySubmissionFeedback(
                $submission->fresh(['project.assessment', 'deliverable', 'project.activeMemberships.user'])
            );
        }

        return $submission->fresh(['files', 'submitter', 'reviewer']);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function assertPayloadMatchesType(
        ProjectDeliverable $deliverable,
        ?ProjectDeliverableSubmission $existing,
        ?string $body,
        ?string $link,
        array $files,
    ): void {
        if ($deliverable->expectsLink()) {
            if ($link === null || $link === '') {
                throw ValidationException::withMessages([
                    'link_url' => [__('projects.submission_link_required')],
                ]);
            }

            if (! filter_var($link, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages([
                    'link_url' => [__('projects.submission_link_invalid')],
                ]);
            }

            return;
        }

        if ($deliverable->expectsText()) {
            if ($body === null || $body === '') {
                throw ValidationException::withMessages([
                    'body' => [__('projects.submission_text_required')],
                ]);
            }

            return;
        }

        $alreadyHasFiles = $existing && $existing->files()->exists();
        if ($files === [] && ! $alreadyHasFiles) {
            throw ValidationException::withMessages([
                'files' => [__('projects.submission_file_required')],
            ]);
        }

        if (! $deliverable->allowsMultipleFiles() && count($files) > 1) {
            throw ValidationException::withMessages([
                'files' => [__('projects.submission_single_file_only')],
            ]);
        }
    }

    private function assertFileCountWithinLimit(
        ProjectDeliverable $deliverable,
        ProjectDeliverableSubmission $submission,
        int $incoming,
    ): void {
        $total = $submission->files()->count() + $incoming;
        if ($total > $deliverable->maxFiles()) {
            throw ValidationException::withMessages([
                'files' => [__('projects.submission_too_many_files', ['max' => $deliverable->maxFiles()])],
            ]);
        }
    }

    /**
     * @param  Collection<int, ProjectSubmissionFile>  $files
     */
    private function deleteFiles(Collection $files): void
    {
        foreach ($files as $file) {
            if ($file->file_path) {
                Storage::disk(self::DISK)->delete($file->file_path);
            }
            $file->delete();
        }
    }
}
