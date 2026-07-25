<?php

namespace App\Services\Structure;

use App\Models\ChurchService;
use App\Models\StructureTemplate;
use App\Support\Structure\ProgressionPolicy;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Resolve structure template anchors for a service (master-plan §15).
 * Callers must use anchor keys only — never hardcoded level display names.
 */
class StructureAnchorResolver
{
    public const ANCHOR_ENROLLMENT = 'enrollment_level';

    public const ANCHOR_ATTENDANCE = 'attendance_level';

    public const ANCHOR_ASSIGNMENT_LEVELS = 'assignment_levels';

    public const ANCHOR_REPORT_ROLLUP = 'report_rollup';

    public const ANCHOR_PROGRESSION = 'progression';

    /** @return list<string> */
    public function enabledLevelKeys(ChurchService $service): array
    {
        $template = $this->templateFor($service);
        if (! $template) {
            return [];
        }

        $levels = collect($template->levels ?? [])->pluck('key')->filter()->values()->all();
        $override = $service->enabled_levels;

        if (is_array($override) && $override !== []) {
            return array_values(array_intersect($levels, $override));
        }

        return $levels;
    }

    public function enrollmentLevel(ChurchService $service): ?string
    {
        return $this->anchorString($service, self::ANCHOR_ENROLLMENT);
    }

    public function attendanceLevel(ChurchService $service): ?string
    {
        return $this->anchorString($service, self::ANCHOR_ATTENDANCE);
    }

    /** @return list<string> */
    public function assignmentLevels(ChurchService $service): array
    {
        $value = $this->anchorValue($service, self::ANCHOR_ASSIGNMENT_LEVELS);

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    public function reportRollup(ChurchService $service): ?string
    {
        return $this->anchorString($service, self::ANCHOR_REPORT_ROLLUP);
    }

    /**
     * T9a — resolved progression policy (service override → template anchors.progression.policy).
     */
    public function progressionPolicy(ChurchService $service): ?string
    {
        if (Schema::hasColumn($service->getTable(), 'progression_policy')
            && ProgressionPolicy::isValid($service->progression_policy)
        ) {
            return $service->progression_policy;
        }

        $config = $this->progressionConfigFromTemplate($service);
        $policy = is_array($config) ? ($config['policy'] ?? null) : null;

        return ProgressionPolicy::isValid(is_string($policy) ? $policy : null)
            ? $policy
            : ProgressionPolicy::CONTINUOUS_OPEN;
    }

    /**
     * T9a — ladder / semester config (service.progression_config merges over template progression).
     *
     * @return array<string, mixed>
     */
    public function progressionConfig(ChurchService $service): array
    {
        $base = $this->progressionConfigFromTemplate($service) ?? [];
        $override = [];
        if (Schema::hasColumn($service->getTable(), 'progression_config')
            && is_array($service->progression_config)
        ) {
            $override = $service->progression_config;
        }

        return array_replace($base, $override);
    }

    public function supportsEndOfCycleWizard(ChurchService $service): bool
    {
        $policy = $this->progressionPolicy($service);

        return $policy !== null && ProgressionPolicy::usesEndOfCycleWizard($policy);
    }

    /**
     * Label for a level key (service override → template levels → key).
     */
    public function labelForLevel(ChurchService $service, string $levelKey, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $overrides = is_array($service->level_labels) ? $service->level_labels : [];

        if (isset($overrides[$levelKey]) && is_array($overrides[$levelKey])) {
            $label = $locale === 'ar'
                ? ($overrides[$levelKey]['label_ar'] ?? $overrides[$levelKey]['label_en'] ?? null)
                : ($overrides[$levelKey]['label_en'] ?? $overrides[$levelKey]['label_ar'] ?? null);
            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        $template = $this->templateFor($service);
        foreach ($template?->levels ?? [] as $level) {
            if (($level['key'] ?? null) !== $levelKey) {
                continue;
            }
            if ($locale === 'ar' && ! empty($level['label_ar'])) {
                return (string) $level['label_ar'];
            }
            if (! empty($level['label_en'])) {
                return (string) $level['label_en'];
            }
        }

        return $levelKey;
    }

    public function templateFor(ChurchService $service): ?StructureTemplate
    {
        if ($service->relationLoaded('structureTemplate')) {
            return $service->structureTemplate;
        }

        if ($service->structure_template_id) {
            return StructureTemplate::query()->find($service->structure_template_id);
        }

        return null;
    }

    public function requireTemplate(ChurchService $service): StructureTemplate
    {
        $template = $this->templateFor($service);
        if (! $template) {
            throw new InvalidArgumentException('Service has no structure template bound.');
        }

        return $template;
    }

    private function anchorString(ChurchService $service, string $anchor): ?string
    {
        $value = $this->anchorValue($service, $anchor);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function anchorValue(ChurchService $service, string $anchor): mixed
    {
        $template = $this->templateFor($service);
        if (! $template) {
            return null;
        }

        $anchors = is_array($template->anchors) ? $template->anchors : [];

        return $anchors[$anchor] ?? null;
    }

    /** @return array<string, mixed>|null */
    private function progressionConfigFromTemplate(ChurchService $service): ?array
    {
        $value = $this->anchorValue($service, self::ANCHOR_PROGRESSION);

        return is_array($value) ? $value : null;
    }
}
