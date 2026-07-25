<?php

namespace App\Support\Structure;

/**
 * Service cycle progression policies (T9). Bind via template anchors / service override —
 * never hardcode level display names.
 */
final class ProgressionPolicy
{
    public const SCHOOL_YEAR_LADDER = 'school_year_ladder';

    public const SEMESTER_COHORT = 'semester_cohort';

    public const CONTINUOUS_OPEN = 'continuous_open';

    public const COURSE_CLOSE_ONLY = 'course_close_only';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::SCHOOL_YEAR_LADDER,
            self::SEMESTER_COHORT,
            self::CONTINUOUS_OPEN,
            self::COURSE_CLOSE_ONLY,
        ];
    }

    public static function isValid(?string $policy): bool
    {
        return is_string($policy) && in_array($policy, self::all(), true);
    }

    /** Policies that use the End-of-Cycle wizard (T9b), not silent apply. */
    public static function usesEndOfCycleWizard(string $policy): bool
    {
        return in_array($policy, [self::SCHOOL_YEAR_LADDER, self::SEMESTER_COHORT], true);
    }
}
