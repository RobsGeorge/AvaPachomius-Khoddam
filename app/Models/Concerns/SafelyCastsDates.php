<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Prevent MySQL zero-dates (0000-00-00) from throwing on Eloquent datetime casts.
 * Sticky authenticated 500s often come from hot-path casts on every layout/middleware hit.
 */
trait SafelyCastsDates
{
    protected function asDateTime($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_string($value) && str_starts_with($value, '0000-00-00')) {
            return null;
        }

        if ($value instanceof DateTimeInterface && (int) $value->format('Y') < 1) {
            return null;
        }

        try {
            $parsed = parent::asDateTime($value);
        } catch (\Throwable) {
            return null;
        }

        if ($parsed instanceof CarbonInterface && (int) $parsed->format('Y') < 1) {
            return null;
        }

        return $parsed;
    }

    /**
     * True when the raw DB value is a real (non-null, non-zero) timestamp.
     * Prefer this over `$model->attr !== null` on hot paths before casts run.
     */
    public function hasRealDateAttribute(string $attribute): bool
    {
        $raw = $this->attributes[$attribute] ?? $this->getAttributes()[$attribute] ?? null;

        if ($raw === null || $raw === '') {
            return false;
        }

        if (is_string($raw) && str_starts_with($raw, '0000-00-00')) {
            return false;
        }

        if ($raw instanceof DateTimeInterface) {
            return (int) $raw->format('Y') >= 1;
        }

        return true;
    }
}
