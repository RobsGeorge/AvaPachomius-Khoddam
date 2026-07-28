<?php

namespace App\Services\People;

use App\Models\ChurchService;
use App\Models\Course;
use App\Models\PeopleImportBatch;
use App\Models\PeopleImportRow;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\People\PlacementMode;
use App\Support\People\PortalAccountPreference;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PeopleImportService
{
    public const TEMPLATE_VERSION = 'v1';

    /** @return list<string> */
    public static function templateHeaders(): array
    {
        return [
            'first_name',
            'second_name',
            'third_name',
            'date_of_birth',
            'gender',
            'email',
            'mobile_number',
            'national_id',
            'service_slug',
            'course_id',
            'unit_anchor_or_code',
            'role_slug',
            'portal_intent',
        ];
    }

    public function __construct(
        private readonly PersonRegistryService $people,
        private readonly PersonDuplicateDetector $duplicates,
        private readonly PersonPlacementService $placements,
        private readonly InvitationService $invitations,
    ) {}

    public function downloadTemplateCsv(): string
    {
        $headers = self::templateHeaders();
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        fputcsv($fh, [
            'Mina', 'George', 'Habib', '1995-03-01', 'male',
            'mina@example.com', '01012345678', '', '', '', '', 'student', 'invite_later',
        ]);
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv ?: '';
    }

    /**
     * Parse upload into a preview batch (no invites sent).
     */
    public function preview(
        UploadedFile $file,
        int $churchId,
        ?User $uploader = null,
        ?int $serviceId = null,
        ?int $courseId = null,
    ): PeopleImportBatch {
        if (! Schema::hasTable('people_import_batches')) {
            throw new \RuntimeException('Import schema is not migrated.');
        }

        $rows = $this->parseCsv($file);
        $intra = $this->duplicates->findIntraBatchCollisions($rows);

        $batch = PeopleImportBatch::withoutTenancy()->create([
            'church_id' => $churchId,
            'service_id' => $serviceId,
            'course_id' => $courseId,
            'uploaded_by_user_id' => $uploader?->user_id,
            'original_filename' => $file->getClientOriginalName(),
            'template_version' => self::TEMPLATE_VERSION,
            'status' => PeopleImportBatch::STATUS_PREVIEW,
            'row_count' => count($rows),
        ]);

        $collisionIndexes = [];
        foreach ($intra as $group) {
            foreach ($group['row_indexes'] as $idx) {
                $collisionIndexes[$idx] = true;
            }
        }

        foreach ($rows as $i => $row) {
            $error = $this->validateRow($row);
            $roleSlug = $row['role_slug'] ?? null;
            $role = null;
            if (! $error && filled($roleSlug)) {
                $role = $this->resolveRoleSlug($roleSlug, $churchId, $serviceId, $courseId, $row);
                if (! $role) {
                    $error = __('people_onboarding.import_unknown_role', ['slug' => $roleSlug]);
                }
            }

            $portalIntent = $row['portal_intent'] ?? 'invite_later';
            if (! in_array($portalIntent, ['info_only', 'invite_later', 'invite_now'], true)) {
                $portalIntent = 'invite_later';
            }

            PeopleImportRow::create([
                'people_import_batch_id' => $batch->people_import_batch_id,
                'row_number' => $i + 1,
                'raw' => $row,
                'match_action' => $error
                    ? PeopleImportRow::ACTION_ERROR
                    : (isset($collisionIndexes[$i]) ? PeopleImportRow::ACTION_PENDING : PeopleImportRow::ACTION_PENDING),
                'role_slug' => $roleSlug,
                'intended_role_id' => $role?->role_id,
                'portal_intent' => $portalIntent,
                'invite_eligible' => ! $error && (filled($row['email'] ?? null) || filled($row['mobile_number'] ?? null)),
                'invite_selected' => $portalIntent === 'invite_now',
                'error_message' => $error,
            ]);
        }

        $batch->update([
            'error_count' => $batch->rows()->where('match_action', PeopleImportRow::ACTION_ERROR)->count(),
        ]);

        AuditLogService::recordEvent('people.import_previewed', [
            'people_import_batch_id' => $batch->people_import_batch_id,
            'row_count' => $batch->row_count,
        ]);

        return $batch->fresh(['rows']);
    }

    public function commit(PeopleImportBatch $batch, bool $confirmedDuplicates = true): PeopleImportBatch
    {
        if ($batch->status === PeopleImportBatch::STATUS_COMMITTED) {
            throw ValidationException::withMessages([
                'batch' => __('people_onboarding.import_already_committed'),
            ]);
        }

        return DB::transaction(function () use ($batch, $confirmedDuplicates) {
            $created = 0;
            $linked = 0;
            $errors = 0;

            foreach ($batch->rows as $importRow) {
                if ($importRow->match_action === PeopleImportRow::ACTION_ERROR) {
                    $errors++;

                    continue;
                }

                $row = $importRow->raw ?? [];
                try {
                    $service = $this->resolveService($batch, $row);
                    $course = $this->resolveCourse($batch, $row, $service);

                    $result = $this->people->findOrCreateByIdentity([
                        'church_id' => $batch->church_id,
                        'first_name' => $row['first_name'] ?? null,
                        'second_name' => $row['second_name'] ?? null,
                        'third_name' => $row['third_name'] ?? null,
                        'date_of_birth' => $row['date_of_birth'] ?? null,
                        'email' => $row['email'] ?? null,
                        'mobile_number' => $row['mobile_number'] ?? null,
                        'national_id' => $row['national_id'] ?? null,
                        'gender' => $row['gender'] ?? null,
                    ], $confirmedDuplicates);

                    $person = $result['person'];
                    $result['linked'] ? $linked++ : $created++;

                    $placement = null;
                    if ($service) {
                        $mode = ($importRow->portal_intent === 'info_only')
                            ? PlacementMode::INFO_ONLY
                            : PlacementMode::PORTAL_PENDING;

                        $role = $importRow->intended_role_id
                            ? Role::withoutTenancy()->find($importRow->intended_role_id)
                            : null;

                        $placement = $this->placements->place(
                            $person,
                            $service,
                            $course,
                            $role,
                            $mode,
                        );
                    }

                    $importRow->update([
                        'person_id' => $person->person_id,
                        'person_placement_id' => $placement?->person_placement_id,
                        'match_action' => $result['linked']
                            ? PeopleImportRow::ACTION_LINKED
                            : PeopleImportRow::ACTION_CREATED,
                        'invite_eligible' => filled($person->email) || filled($person->mobile_number),
                        'error_message' => null,
                    ]);
                } catch (\Throwable $e) {
                    $errors++;
                    $importRow->update([
                        'match_action' => PeopleImportRow::ACTION_ERROR,
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            $batch->update([
                'status' => PeopleImportBatch::STATUS_COMMITTED,
                'created_count' => $created,
                'linked_count' => $linked,
                'error_count' => $errors,
            ]);

            AuditLogService::recordEvent('people.import_committed', [
                'people_import_batch_id' => $batch->people_import_batch_id,
                'created' => $created,
                'linked' => $linked,
                'errors' => $errors,
            ]);

            return $batch->fresh(['rows']);
        });
    }

    /**
     * Bulk invite selected eligible rows (email and/or WhatsApp).
     *
     * @param  list<int>  $rowIds
     * @return array{sent: int, failed: int}
     */
    public function bulkInvite(
        PeopleImportBatch $batch,
        array $rowIds,
        bool $sendEmail = true,
        bool $sendWhatsapp = false,
        ?User $inviter = null,
    ): array {
        $sent = 0;
        $failed = 0;

        $rows = $batch->rows()
            ->whereIn('people_import_row_id', $rowIds)
            ->whereNotNull('person_id')
            ->get();

        foreach ($rows as $row) {
            $person = Person::withoutTenancy()->find($row->person_id);
            if (! $person) {
                $failed++;

                continue;
            }

            try {
                $this->invitations->invite($person, [
                    'send_email' => $sendEmail && filled($person->email),
                    'send_whatsapp' => $sendWhatsapp && filled($person->mobile_number),
                    'service_id' => $batch->service_id,
                    'course_id' => $batch->course_id,
                    'intended_role_id' => $row->intended_role_id,
                    'person_placement_id' => $row->person_placement_id,
                    'invited_by_user_id' => $inviter?->user_id,
                ]);
                $row->update(['invite_selected' => true]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $row->update(['error_message' => $e->getMessage()]);
            }
        }

        AuditLogService::recordEvent('people.bulk_invite', [
            'people_import_batch_id' => $batch->people_import_batch_id,
            'sent' => $sent,
            'failed' => $failed,
            'send_email' => $sendEmail,
            'send_whatsapp' => $sendWhatsapp,
        ]);

        return compact('sent', 'failed');
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => __('people_onboarding.import_unreadable')]);
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => __('people_onboarding.import_empty')]);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($data[$i]) ? trim((string) $data[$i]) : null;
                if ($row[$key] === '') {
                    $row[$key] = null;
                }
            }
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /** @param  array<string, mixed>  $row */
    private function validateRow(array $row): ?string
    {
        if (! filled($row['first_name'] ?? null) && ! filled($row['second_name'] ?? null)) {
            return __('people_onboarding.import_name_required');
        }

        if (! filled($row['email'] ?? null)
            && ! filled($row['mobile_number'] ?? null)
            && ! filled($row['national_id'] ?? null)
        ) {
            return __('people_onboarding.import_identity_required');
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    private function resolveRoleSlug(
        string $slug,
        int $churchId,
        ?int $serviceId,
        ?int $courseId,
        array $row,
    ): ?Role {
        $courseId = isset($row['course_id']) && filled($row['course_id'])
            ? (int) $row['course_id']
            : $courseId;

        if ($courseId) {
            $role = Role::withoutTenancy()
                ->where('course_id', $courseId)
                ->where('slug', $slug)
                ->first();
            if ($role) {
                return $role;
            }
        }

        $serviceId = $serviceId;
        if (! $serviceId && filled($row['service_slug'] ?? null)) {
            $serviceId = ChurchService::withoutTenancy()
                ->where('church_id', $churchId)
                ->where('slug', $row['service_slug'])
                ->value('service_id');
        }

        if ($serviceId) {
            $role = Role::withoutTenancy()
                ->where('service_id', $serviceId)
                ->whereNull('course_id')
                ->where('slug', $slug)
                ->first();
            if ($role) {
                return $role;
            }
        }

        return Role::withoutTenancy()
            ->where('church_id', $churchId)
            ->whereNull('course_id')
            ->whereNull('service_id')
            ->where('slug', $slug)
            ->first();
    }

    /** @param  array<string, mixed>  $row */
    private function resolveService(PeopleImportBatch $batch, array $row): ?ChurchService
    {
        if ($batch->service_id) {
            return ChurchService::withoutTenancy()->find($batch->service_id);
        }

        if (filled($row['service_slug'] ?? null)) {
            return ChurchService::withoutTenancy()
                ->where('church_id', $batch->church_id)
                ->where('slug', $row['service_slug'])
                ->first();
        }

        return null;
    }

    /** @param  array<string, mixed>  $row */
    private function resolveCourse(PeopleImportBatch $batch, array $row, ?ChurchService $service): ?Course
    {
        $courseId = filled($row['course_id'] ?? null) ? (int) $row['course_id'] : $batch->course_id;
        if (! $courseId) {
            return null;
        }

        $course = Course::withoutTenancy()->find($courseId);
        if ($course && $service && (int) $course->service_id !== (int) $service->service_id) {
            return null;
        }

        return $course;
    }
}
