<?php

namespace App\Support\Structure;

use App\Models\ChurchService;
use App\Models\ServiceUnit;
use Illuminate\Support\Facades\Schema;

/**
 * Parse service progression_config ladder edges (T9b).
 * Prefer course_id edges (UCR source of truth); unit ids resolve via service_units.course_id.
 */
final class ProgressionLadder
{
    /**
     * @return list<array{from_course_id: int, to_course_id: int}>
     */
    public static function courseEdges(ChurchService $service, array $config): array
    {
        $raw = $config['ladder']['edges'] ?? $config['edges'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $edges = [];
        foreach ($raw as $edge) {
            if (! is_array($edge)) {
                continue;
            }

            $from = self::resolveCourseId($service, $edge, 'from');
            $to = self::resolveCourseId($service, $edge, 'to');
            if ($from === null || $to === null || $from === $to) {
                continue;
            }

            $edges[] = [
                'from_course_id' => $from,
                'to_course_id' => $to,
            ];
        }

        return $edges;
    }

    /**
     * @param  list<array{from_course_id: int, to_course_id: int}>  $edges
     */
    public static function nextCourseId(array $edges, int $fromCourseId): ?int
    {
        foreach ($edges as $edge) {
            if ((int) $edge['from_course_id'] === $fromCourseId) {
                return (int) $edge['to_course_id'];
            }
        }

        return null;
    }

    /**
     * Normalize posted edge rows into progression_config.ladder.edges.
     *
     * @param  list<array{from_course_id?: mixed, to_course_id?: mixed}>  $rows
     * @return array{ladder: array{edges: list<array{from_course_id: int, to_course_id: int}>}}
     */
    public static function configFromEdgeRows(array $rows): array
    {
        $edges = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $from = (int) ($row['from_course_id'] ?? 0);
            $to = (int) ($row['to_course_id'] ?? 0);
            if ($from <= 0 || $to <= 0 || $from === $to) {
                continue;
            }
            $edges[] = [
                'from_course_id' => $from,
                'to_course_id' => $to,
            ];
        }

        return ['ladder' => ['edges' => $edges]];
    }

    /** @param array<string, mixed> $edge */
    private static function resolveCourseId(ChurchService $service, array $edge, string $side): ?int
    {
        $courseKey = "{$side}_course_id";
        if (isset($edge[$courseKey]) && (int) $edge[$courseKey] > 0) {
            return (int) $edge[$courseKey];
        }

        $unitKey = "{$side}_service_unit_id";
        if (! isset($edge[$unitKey]) || (int) $edge[$unitKey] <= 0) {
            return null;
        }

        if (! Schema::hasTable('service_units')) {
            return null;
        }

        $courseId = ServiceUnit::query()
            ->where('service_unit_id', (int) $edge[$unitKey])
            ->where('service_id', $service->service_id)
            ->value('course_id');

        return $courseId ? (int) $courseId : null;
    }
}
