<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Module;
use App\Models\Session;
use App\Models\User;
use App\Services\AttendanceCloseService;
use App\Services\SessionNotificationService;
use App\Services\StudentRosterService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function __construct(
        private AttendanceCloseService $attendanceClose,
        private SessionNotificationService $sessionNotifications,
        private StudentRosterService $rosterService,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $canManageSessions = $user instanceof User
            && (($user->is_superadmin ?? false) || $user->isInstructorOrAdmin());

        $query = Session::with(['course', 'module', 'attendanceClosedBy'])
            ->orderBy('session_date', 'desc');

        $currentCourse = current_course();
        if ($currentCourse) {
            $query->where('course_id', $currentCourse->course_id);
        }

        if ($canManageSessions) {
            $query->withCount([
                'attendances as attended_count' => fn ($q) => $q->whereIn(
                    'status',
                    Attendance::ATTENDED_STATUSES
                ),
                'attendances as recorded_count',
            ]);
        }

        $sessions = $query->paginate(20)->appends($request->only('session_id'));

        $missingCounts = [];
        $focusSession = null;
        $focusMissingCount = 0;
        $focusSessionId = $request->integer('session_id') ?: null;

        if ($canManageSessions) {
            foreach ($sessions as $session) {
                $missingCounts[$session->session_id] = $this->attendanceClose->missingRecordCount($session);
            }

            if ($focusSessionId) {
                $focusSession = Session::with(['course', 'module', 'attendanceClosedBy'])
                    ->withCount([
                        'attendances as attended_count' => fn ($q) => $q->whereIn(
                            'status',
                            Attendance::ATTENDED_STATUSES
                        ),
                        'attendances as recorded_count',
                    ])
                    ->find($focusSessionId);

                if ($focusSession) {
                    $focusMissingCount = $this->attendanceClose->missingRecordCount($focusSession);
                }
            }
        }

        $todayLocal = $this->attendanceClose->todayInTimezone()->toDateString();
        $canNotifySessions = $canManageSessions && $this->userCanNotifySessions($user);

        return view('sessions.index', compact(
            'sessions',
            'todayLocal',
            'canManageSessions',
            'canNotifySessions',
            'missingCounts',
            'focusSession',
            'focusMissingCount',
            'focusSessionId',
        ));
    }

    public function show(Session $session)
    {
        return redirect()->route('sessions.index', [
            'session_id' => $session->session_id,
        ]);
    }

    public function notifyStudents(Session $session)
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        if (! $session->shouldNotifyStudents()) {
            return redirect()->back()->with('warning', __('pages.session_notify_disabled'));
        }

        if (! $this->sessionNotifications->isFutureSession($session)) {
            return redirect()->back()->with('warning', __('pages.session_notify_past'));
        }

        $result = $this->sessionNotifications->notifySession($session, $user, 'manual');

        if ($result['count'] === 0) {
            return redirect()->back()->with('warning', __('pages.session_notify_none'));
        }

        return redirect()->back()->with('success', __('pages.session_notify_sent', ['count' => $result['count']]));
    }

    public function notifyNextSession()
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $currentCourse = current_course();
        if (! $currentCourse) {
            return redirect()->route('sessions.index')
                ->with('warning', __('pages.session_notify_no_next'));
        }

        $session = $this->sessionNotifications->nextNotifiableSession($currentCourse);
        if (! $session) {
            return redirect()->route('sessions.index')
                ->with('warning', __('pages.session_notify_no_next'));
        }

        $result = $this->sessionNotifications->notifySession($session, $user, 'manual');

        if ($result['count'] === 0) {
            return redirect()->route('sessions.index')
                ->with('warning', __('pages.session_notify_none'));
        }

        return redirect()->route('sessions.index')
            ->with('success', __('pages.session_notify_sent', ['count' => $result['count']]));
    }

    public function toggleNotifyStudents(Request $request, Session $session)
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $this->sessionNotifications->authorizeNotify($user, $session);

        $validated = $request->validate([
            'notify_students' => ['required', 'boolean'],
        ]);

        $session->update([
            'notify_students' => (bool) $validated['notify_students'],
        ]);

        return redirect()->back()->with('success', __('pages.session_notify_toggled'));
    }

    public function closeAttendance(Session $session)
    {
        $user = auth()->user();
        if (! $user instanceof User
            || (! ($user->is_superadmin ?? false)
                && ! ($session->course_id
                    ? $user->isInstructorOrAdmin((string) $session->course_id)
                    : $user->isInstructorOrAdmin()))) {
            abort(403, __('pages.not_authorized'));
        }

        if ($session->isAttendanceClosed()) {
            return redirect()->back()
                ->with('warning', __('pages.attendance_already_closed'));
        }

        $sessionDate = $session->session_date?->toDateString();
        $todayLocal = $this->attendanceClose->todayInTimezone()->toDateString();

        if (! $sessionDate || $sessionDate > $todayLocal) {
            return redirect()->back()
                ->with('error', __('pages.attendance_cannot_close_future_session'));
        }

        $result = $this->attendanceClose->closeSession($session, (int) $user->user_id);

        if ($result['already_closed']) {
            return redirect()->back()
                ->with('warning', __('pages.attendance_already_closed'));
        }

        return redirect()->back()
            ->with('success', __('pages.attendance_closed_success_detail', [
                'absent' => $result['absent_marked'],
                'late' => $result['late_marked'],
            ]));
    }

    public function create()
    {
        $currentCourse = current_course();
        $courses = $currentCourse
            ? Course::with('modules')->whereKey($currentCourse->course_id)->orderBy('title')->get()
            : Course::with('modules')->orderBy('title')->get();

        return view('sessions.create', [
            'courses' => $courses,
            'defaultCourseId' => $currentCourse?->course_id,
            'rosterStudents' => $this->rosterStudentsForCourseId(
                old('course_id', $currentCourse?->course_id)
            ),
        ]);
    }

    public function store(Request $request)
    {
        $mode = $request->input('creation_mode', 'single');

        if (! in_array($mode, ['single', 'multi', 'weekly'], true)) {
            $mode = 'single';
        }

        $payload = [
            'course_id'     => $request->input('course_id'),
            'module_id'     => $request->input('module_id'),
            'session_title' => $request->input('session_title'),
            'session_start_time' => $this->normalizeTimeInput($request->input('session_start_time')),
            'creation_mode' => $mode,
        ];

        if ($mode === 'single') {
            $payload['single_date'] = $this->normalizeDateInput($request->input('single_date'));
        } elseif ($mode === 'multi') {
            $normalized = [];
            foreach ((array) $request->input('dates', []) as $raw) {
                $date = $this->normalizeDateInput(is_string($raw) ? $raw : null);
                if ($date !== null) {
                    $normalized[] = $date;
                }
            }
            $payload['dates'] = array_values(array_unique($normalized));
        } else {
            $payload['start_date'] = $this->normalizeDateInput($request->input('start_date'));
            $payload['weeks'] = $request->input('weeks');
        }

        $rules = [
            'course_id'     => 'required|exists:course,course_id',
            'module_id'     => 'required|exists:modules,module_id',
            'session_title' => 'required|string|max:27',
            'session_start_time' => 'nullable|date_format:H:i',
            'creation_mode' => 'required|in:single,multi,weekly',
        ];

        if ($mode === 'single') {
            $rules['single_date'] = 'required|date_format:Y-m-d';
        } elseif ($mode === 'multi') {
            $rules['dates'] = 'required|array|min:1';
            $rules['dates.*'] = 'required|date_format:Y-m-d';
        } else {
            $rules['start_date'] = 'required|date_format:Y-m-d';
            $rules['weeks'] = 'required|integer|min:1|max:52';
        }

        $validated = validator($payload, $rules, [
            'single_date.required' => 'يرجى اختيار تاريخ الجلسة.',
            'single_date.date_format' => 'تاريخ الجلسة غير صالح. استخدم التقويم أو الصيغة يوم/شهر/سنة.',
            'dates.required' => 'يرجى إضافة تاريخ واحد على الأقل.',
            'dates.min' => 'يرجى إضافة تاريخ واحد على الأقل.',
            'dates.*.date_format' => 'أحد التواريخ غير صالح.',
            'start_date.required' => 'يرجى اختيار تاريخ أول محاضرة.',
            'start_date.date_format' => 'تاريخ البداية غير صالح.',
            'module_id.required' => __('pages.module_required_for_session'),
        ])->validate();

        $this->assertModuleBelongsToCourse((int) $validated['module_id'], (int) $validated['course_id']);

        $datesToCreate = [];

        if ($mode === 'single') {
            $datesToCreate = [$validated['single_date']];
        } elseif ($mode === 'multi') {
            $datesToCreate = $validated['dates'];
            sort($datesToCreate);
        } else {
            $start = Carbon::createFromFormat('Y-m-d', $validated['start_date']);
            for ($i = 0; $i < (int) $validated['weeks']; $i++) {
                $datesToCreate[] = $start->copy()->addWeeks($i)->format('Y-m-d');
            }
        }

        $isSingle = count($datesToCreate) === 1;
        $moduleId = (int) $validated['module_id'];

        $notifyStudents = $this->parseNotifyStudentsFlag($request);
        $targetUserIds = $this->parseTargetUserIds($request);

        try {
            $createdSessions = [];
            foreach ($datesToCreate as $index => $date) {
                $title = $isSingle
                    ? $validated['session_title']
                    : $validated['session_title'].' '.($index + 1);

                $session = Session::create([
                    'course_id'     => $validated['course_id'],
                    'module_id'     => $moduleId,
                    'week_number'   => $index + 1,
                    'session_title' => mb_substr($title, 0, 30),
                    'session_date'  => $date,
                    'session_start_time' => $validated['session_start_time'] ?? null,
                    'notify_students' => $notifyStudents,
                ]);

                $this->linkSessionToModule($session, $moduleId, $index + 1);
                $this->sessionNotifications->syncTargets($session, $targetUserIds);
                $createdSessions[] = $session;
            }
        } catch (QueryException $e) {
            Log::error('Session create failed', [
                'mode'    => $mode,
                'dates'   => $datesToCreate,
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'general' => 'تعذر حفظ الجلسة في قاعدة البيانات. تأكد من تشغيل migrations على الخادم.',
                ]);
        }

        $count = count($datesToCreate);

        return redirect()->route('sessions.index')
            ->with('success', "تم إنشاء {$count} ".($count === 1 ? 'جلسة' : 'جلسات').' بنجاح');
    }

    public function edit(string $id)
    {
        $session = Session::with(['module', 'notificationTargets'])->findOrFail($id);
        $courses = Course::with('modules')->orderBy('title')->get();

        return view('sessions.edit', [
            'session' => $session,
            'courses' => $courses,
            'rosterStudents' => $this->rosterStudentsForCourseId($session->course_id),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $normalizedDate = $this->normalizeDateInput($request->input('session_date'));

        $validated = validator(
            [
                'course_id'     => $request->input('course_id'),
                'module_id'     => $request->input('module_id'),
                'session_title' => $request->input('session_title'),
                'session_date'  => $normalizedDate,
                'session_start_time' => $this->normalizeTimeInput($request->input('session_start_time')),
            ],
            [
                'course_id'     => 'required|exists:course,course_id',
                'module_id'     => 'required|exists:modules,module_id',
                'session_title' => 'required|string|max:30',
                'session_date'  => 'required|date_format:Y-m-d',
                'session_start_time' => 'nullable|date_format:H:i',
            ],
            [
                'module_id.required' => __('pages.module_required_for_session'),
            ]
        )->validate();

        $this->assertModuleBelongsToCourse((int) $validated['module_id'], (int) $validated['course_id']);

        $session = Session::findOrFail($id);
        $session->update(array_merge($validated, [
            'notify_students' => $this->parseNotifyStudentsFlag($request),
        ]));
        $this->sessionNotifications->syncTargets(
            $session->fresh(),
            $this->parseTargetUserIds($request)
        );
        $this->linkSessionToModule($session, (int) $validated['module_id']);

        return redirect()->route('sessions.index')
            ->with('success', 'تم تحديث الجلسة بنجاح');
    }

    public function destroy(string $id)
    {
        $session = Session::findOrFail($id);
        DB::table('module_session')->where('session_id', $session->session_id)->delete();
        $session->delete();

        return redirect()->route('sessions.index')
            ->with('success', 'تم حذف الجلسة بنجاح');
    }

    private function assertModuleBelongsToCourse(int $moduleId, int $courseId): void
    {
        $linked = DB::table('course_module')
            ->where('course_id', $courseId)
            ->where('module_id', $moduleId)
            ->exists();

        if (! $linked) {
            throw ValidationException::withMessages([
                'module_id' => __('pages.module_not_in_course'),
            ]);
        }
    }

    private function linkSessionToModule(Session $session, int $moduleId, ?int $weekNumber = null): void
    {
        if ($weekNumber !== null) {
            $session->update(['module_id' => $moduleId, 'week_number' => $weekNumber]);
        } else {
            $session->update(['module_id' => $moduleId]);
        }

        DB::table('module_session')->where('session_id', $session->session_id)->delete();

        DB::table('module_session')->insert([
            'module_id'    => $moduleId,
            'session_id'   => $session->session_id,
            'week_number'  => $weekNumber,
        ]);
    }

    /**
     * Normalize to Y-m-d for MySQL DATE / Laravel date cast.
     * Accepts native date input (Y-m-d) or displayed d/m/Y (e.g. 06/08/2026).
     */
    private function normalizeDateInput(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        foreach (['d/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTimeInput(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return null;
    }

    private function parseNotifyStudentsFlag(Request $request): bool
    {
        return $request->boolean('notify_students', true);
    }

    /** @return list<int> */
    private function parseTargetUserIds(Request $request): array
    {
        return collect((array) $request->input('notification_target_user_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function rosterStudentsForCourseId(mixed $courseId): \Illuminate\Support\Collection
    {
        if (! $courseId) {
            return collect();
        }

        return $this->rosterService->enrolledStudents((int) $courseId);
    }

    private function userCanNotifySessions(User $user): bool
    {
        if ($user->is_superadmin ?? false) {
            return true;
        }

        $currentCourse = current_course();
        if ($currentCourse) {
            return app(\App\Services\CoursePermissionResolver::class)
                ->canInCourse($user, 'session.notify', $currentCourse);
        }

        return $user->userCourseRoles()
            ->activeStaff()
            ->exists();
    }
}
