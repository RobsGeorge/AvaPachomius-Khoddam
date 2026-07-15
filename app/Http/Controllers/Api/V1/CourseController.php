<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesStudentCourse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use App\Services\CourseContextService;
use App\Services\StudentRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    use AuthorizesStudentCourse;

    private const CONTEXT_TTL_SECONDS = 60 * 60 * 24 * 90;

    public function __construct(
        private StudentRosterService $roster,
        private CourseContextService $courseContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $courses = $this->roster->studentEnrolledCourses($user);
        if ($courses->isEmpty()) {
            $courses = $this->courseContext->selectableCourses($user)
                ->pluck('course')
                ->filter()
                ->values();
        }

        return response()->json([
            'data' => $courses->map(fn (Course $course) => $this->serializeCourse($course))->values(),
            'meta' => [
                'current_course_id' => $this->currentCourseId($user),
            ],
        ]);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCourseAccess($user, $course);

        return response()->json(['data' => $this->serializeCourse($course)]);
    }

    public function current(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $courseId = $this->currentCourseId($user);

        if (! $courseId) {
            return response()->json(['data' => null]);
        }

        $course = Course::query()->find($courseId);
        if (! $course) {
            return response()->json(['data' => null]);
        }

        try {
            $this->authorizeCourseAccess($user, $course);
        } catch (\Throwable) {
            Cache::forget($this->contextCacheKey($user));

            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->serializeCourse($course)]);
    }

    public function setCurrent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'course_id' => ['required', 'integer'],
        ]);

        $course = Course::query()->findOrFail($data['course_id']);

        if (! $this->courseContext->userCanSelectCourse($user, $course)) {
            throw ValidationException::withMessages([
                'course_id' => [__('course_context.invalid_course')],
            ]);
        }

        // Bearer-token clients have no web session: persist course context in cache.
        Cache::put($this->contextCacheKey($user), (int) $course->course_id, self::CONTEXT_TTL_SECONDS);

        return response()->json(['data' => $this->serializeCourse($course)]);
    }

    public function clearCurrent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Cache::forget($this->contextCacheKey($user));

        return response()->json(['data' => null]);
    }

    private function currentCourseId(User $user): ?int
    {
        $cached = Cache::get($this->contextCacheKey($user));

        return $cached !== null ? (int) $cached : null;
    }

    private function contextCacheKey(User $user): string
    {
        return 'api.v1.course_context.'.$user->user_id;
    }

    /** @return array<string, mixed> */
    private function serializeCourse(Course $course): array
    {
        return [
            'course_id' => $course->course_id,
            'title' => method_exists($course, 'localizedTitle')
                ? $course->localizedTitle()
                : $course->title,
            'year' => $course->year,
            'service_id' => $course->service_id,
            'status' => $course->status ?? null,
        ];
    }
}
