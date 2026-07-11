<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseGraduationStudent;
use App\Models\GradeCategory;
use App\Services\GraduationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GraduationController extends Controller
{
    public function __construct(
        private GraduationService $graduation,
    ) {}

    /** Overview across all courses. */
    public function index()
    {
        $courses = Course::orderBy('year', 'desc')->orderBy('title')->get();

        $summaries = $courses->map(function (Course $course) {
            $course->load(['gradeCategories.items.grades']);
            $students = $this->graduation->enrolledStudents($course);
            $evaluations = $course->hasGraduationCriteria()
                ? $this->graduation->evaluateCourse($course, $students)
                : collect();

            return [
                'course'     => $course,
                'students'   => $students->count(),
                'eligible'   => $evaluations->where('eligible', true)->count(),
                'configured' => $course->hasGraduationCriteria(),
            ];
        });

        $unconfiguredCount = $summaries->where('configured', false)->count();

        return view('graduation.index', compact('summaries', 'unconfiguredCount'));
    }

    public function show(string $courseId)
    {
        $course = Course::with(['gradeCategories.items.grades', 'latestGraduation.students.user'])
            ->findOrFail($courseId);

        $usingSnapshot = $course->areGradesAnnounced() && $course->latestGraduation;
        $criteriaConfigured = $course->hasGraduationCriteria();

        if ($usingSnapshot) {
            $evaluations = $this->snapshotEvaluations($course);
            $eligible = $evaluations->where('graduated', true)->values();
        } else {
            $students = $this->graduation->enrolledStudents($course);
            $evaluations = $criteriaConfigured
                ? $this->graduation->evaluateCourse($course, $students)
                : $this->graduation->evaluateCoursePreview($course, $students);
            $eligible = $evaluations->where('eligible', true)->values();
        }

        return view('graduation.show', compact(
            'course',
            'evaluations',
            'eligible',
            'criteriaConfigured',
            'usingSnapshot'
        ));
    }

    public function exportCsv(string $courseId): StreamedResponse
    {
        $course = Course::with('latestGraduation.students.user')->findOrFail($courseId);
        abort_unless($course->areGradesAnnounced(), 403);

        $evaluations = $this->snapshotEvaluations($course);
        $filename = 'graduation-'.$course->course_id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($evaluations, $course) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Student',
                'National ID',
                'Attendance %',
                'Raw grade',
                'Grace',
                'Final grade',
                'Letter',
                'Graduated',
                'Failure reason',
            ]);

            foreach ($evaluations as $row) {
                fputcsv($out, [
                    $row['user']->first_name.' '.$row['user']->second_name,
                    $row['user']->national_id,
                    $row['attendance_pct'],
                    $row['raw_total_grade'],
                    $row['grace_marks_applied'],
                    $row['total_grade'],
                    $row['letter'],
                    $row['graduated'] ? 'yes' : 'no',
                    $row['failure_reason'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Admin: configure graduation percentages for all courses. */
    public function settings()
    {
        $courses = Course::orderBy('year', 'desc')->orderBy('title')->get();

        return view('graduation.settings', compact('courses'));
    }

    public function updateSettings(Request $request, string $courseId)
    {
        $course = Course::findOrFail($courseId);

        $data = $request->validate([
            'passing_percentage'        => 'required|numeric|min:0|max:100',
            'min_attendance_percentage' => 'required|numeric|min:0|max:100',
            'grace_marks_enabled'       => 'nullable|boolean',
            'max_grace_marks'           => 'nullable|numeric|min:0|max:100',
        ]);

        $course->update([
            'passing_percentage'        => $data['passing_percentage'],
            'min_attendance_percentage' => $data['min_attendance_percentage'],
            'grace_marks_enabled'       => $request->boolean('grace_marks_enabled'),
            'max_grace_marks'           => $data['max_grace_marks'] ?? 0,
        ]);

        return redirect()
            ->route('admin.graduation-settings.index')
            ->with('success', __('pages.graduation_settings_saved_for', ['course' => $course->title]));
    }

    private function snapshotEvaluations(Course $course)
    {
        $graduation = $course->latestGraduation;
        if (! $graduation) {
            return collect();
        }

        return $graduation->students
            ->map(function (CourseGraduationStudent $row) {
                $total = $row->final_total_grade;

                return [
                    'user'                => $row->user,
                    'attendance_pct'      => $row->attendance_pct,
                    'raw_total_grade'     => $row->raw_total_grade,
                    'grace_marks_applied' => $row->grace_marks_applied,
                    'total_grade'         => $total,
                    'letter'              => $row->letter_grade,
                    'letter_ar'           => GradeCategory::letterGradeAr($total),
                    'color'               => GradeCategory::gradeColor($total),
                    'eligible'            => $row->eligible,
                    'graduated'           => $row->graduated,
                    'attendance_pass'     => $row->failure_reason !== 'attendance',
                    'grade_pass'          => $row->graduated || $row->failure_reason !== 'grade',
                    'failure_reason'      => $row->failure_reason,
                ];
            })
            ->sortByDesc(fn ($row) => [$row['graduated'], $row['total_grade']])
            ->values();
    }
}
