<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SatisfactionReportController extends Controller
{
    public function module(string $courseId, string $moduleId)
    {
        abort_unless(Auth::user()->isInstructorOrAdmin(), 403);

        $course = Course::findOrFail($courseId);
        $module = $course->modules()->where('modules.module_id', $moduleId)->firstOrFail();

        $summary = $this->buildSummary(
            ModuleFeedback::where('course_id', $courseId)->where('module_id', $moduleId)
        );

        $responses = ModuleFeedback::with('user')
            ->where('course_id', $courseId)
            ->where('module_id', $moduleId)
            ->latest('feedback_id')
            ->paginate(30);

        return view('satisfaction.module', compact('course', 'module', 'summary', 'responses'));
    }

    public function course(string $courseId)
    {
        abort_unless(Auth::user()->isInstructorOrAdmin(), 403);

        $course = Course::findOrFail($courseId);

        $summary = $this->buildSummary(
            ModuleFeedback::where('course_id', $courseId)
        );

        $byModule = ModuleFeedback::query()
            ->where('course_id', $courseId)
            ->select('module_id')
            ->selectRaw('COUNT(*) as response_count')
            ->selectRaw('AVG(lecture_rating) as avg_lecture')
            ->selectRaw('AVG(speaker_rating) as avg_speaker')
            ->selectRaw('AVG(workshop_rating) as avg_workshop')
            ->selectRaw('AVG(timing_rating) as avg_timing')
            ->selectRaw('AVG(content_rating) as avg_content')
            ->groupBy('module_id')
            ->get();

        $modules = $course->modules->keyBy('module_id');

        return view('satisfaction.course', compact('course', 'summary', 'byModule', 'modules'));
    }

    private function buildSummary($query): array
    {
        $keys = ['lecture', 'speaker', 'workshop', 'timing', 'content'];
        $summary = ['total' => (clone $query)->count()];

        foreach ($keys as $key) {
            $column = $key.'_rating';
            $summary[$key] = [
                'average' => round((float) (clone $query)->whereNotNull($column)->avg($column), 2),
                'distribution' => (clone $query)
                    ->whereNotNull($column)
                    ->selectRaw("$column as rating, COUNT(*) as total")
                    ->groupBy($column)
                    ->orderBy('rating')
                    ->pluck('total', 'rating')
                    ->all(),
            ];
        }

        return $summary;
    }
}
