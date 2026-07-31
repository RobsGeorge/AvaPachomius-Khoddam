<?php

namespace App\Support\Applications;

use Carbon\CarbonInterface;

/**
 * Read-only row for the applications hub index (Church | Service | Course).
 * Approve/reject stay on each type's existing show page.
 */
final class ApplicationQueueItem
{
    public const TYPE_COURSE = 'course';

    public const TYPE_SERVICE = 'service';

    public const TYPE_CHURCH = 'church';

    /** @return list<string> */
    public static function types(): array
    {
        return [self::TYPE_COURSE, self::TYPE_SERVICE, self::TYPE_CHURCH];
    }

    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $subjectLabel,
        public readonly string $applicantLabel,
        public readonly ?string $applicantSecondary,
        public readonly string $status,
        public readonly ?CarbonInterface $submittedAt,
        public readonly string $showUrl,
    ) {}
}
