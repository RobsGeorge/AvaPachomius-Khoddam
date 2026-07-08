<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\Course;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\StudentRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AnnouncementManageController extends Controller
{
    public function __construct(
        private AnnouncementService $announcements,
        private StudentRosterService $rosterService
    ) {}

    public function index()
    {
        $user = Auth::user();
        $courseIds = $this->accessibleCourseIds($user);

        $items = Announcement::query()
            ->with(['course', 'creator', 'publisher', 'deliveries'])
            ->when(! $user->isAdmin() && ! ($user->is_superadmin ?? false), function ($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds);
            })
            ->orderByDesc('updated_at')
            ->paginate(20);

        return view('announcements.manage.index', compact('items'));
    }

    public function create(Request $request)
    {
        $courses = $this->accessibleCourses();
        $selectedCourse = $request->query('course_id');
        $students = $selectedCourse
            ? $this->rosterService->enrolledStudents((int) $selectedCourse)
            : collect();

        return view('announcements.manage.create', compact('courses', 'selectedCourse', 'students'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->authorizeCourseAccess($data);

        $announcement = $this->announcements->createDraft(Auth::user(), $data);

        return redirect()
            ->route('announcements.manage.edit', $announcement)
            ->with('success', __('announcements.created'));
    }

    public function edit(Announcement $announcement)
    {
        $this->authorizeAnnouncement($announcement);

        $courses = $this->accessibleCourses();
        $students = $announcement->course_id
            ? $this->rosterService->enrolledStudents($announcement->course_id)
            : collect();

        $announcement->load(['targetUsers', 'revisions.editor', 'deliveries.user']);

        return view('announcements.manage.edit', compact('announcement', 'courses', 'students'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->authorizeAnnouncement($announcement);
        $data = $this->validated($request);
        $this->authorizeCourseAccess($data);

        $this->announcements->updateAnnouncement($announcement, Auth::user(), $data);

        return redirect()
            ->route('announcements.manage.edit', $announcement)
            ->with('success', __('announcements.updated'));
    }

    public function publish(Announcement $announcement)
    {
        $this->authorizeAnnouncement($announcement);

        $republish = $announcement->isPublished();
        $this->announcements->publish($announcement, Auth::user(), $republish);

        $message = $republish
            ? __('announcements.republished')
            : __('announcements.published');

        if ($announcement->fresh()->hasChannel(Announcement::CHANNEL_WHATSAPP)) {
            return redirect()
                ->route('announcements.manage.whatsapp', $announcement)
                ->with('success', $message);
        }

        return redirect()
            ->route('announcements.manage.edit', $announcement)
            ->with('success', $message);
    }

    public function resendEmail(Announcement $announcement)
    {
        $this->authorizeAnnouncement($announcement);

        abort_unless($announcement->hasChannel(Announcement::CHANNEL_EMAIL), 404);

        $count = $this->announcements->resendEmails($announcement, Auth::user());

        return back()->with('success', __('announcements.email_resent', ['count' => $count]));
    }

    public function whatsapp(Announcement $announcement)
    {
        $this->authorizeAnnouncement($announcement);

        abort_unless($announcement->hasChannel(Announcement::CHANNEL_WHATSAPP), 404);

        $announcement->load(['deliveries.user']);

        return view('announcements.manage.whatsapp', compact('announcement'));
    }

    public function markWhatsappSent(Request $request, Announcement $announcement)
    {
        $this->authorizeAnnouncement($announcement);

        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:user,user_id',
        ]);

        $this->announcements->markWhatsappDispatched($announcement, Auth::user(), $data['user_ids']);

        return back()->with('success', __('announcements.whatsapp_marked'));
    }

    public function directory(Announcement $announcement)
    {
        $this->authorizeAnnouncement($announcement);

        $announcement->load([
            'course',
            'creator',
            'publisher',
            'revisions.editor',
            'deliveries.user',
        ]);

        return view('announcements.manage.directory', compact('announcement'));
    }

    private function validated(Request $request): array
    {
        $channels = $request->input('channels', []);

        return $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:10000',
            'target_mode' => ['required', Rule::in([Announcement::TARGET_COURSE, Announcement::TARGET_USERS])],
            'course_id' => 'nullable|exists:course,course_id',
            'target_user_ids' => 'nullable|array',
            'target_user_ids.*' => 'integer|exists:user,user_id',
            'banner_starts_at' => 'nullable|date',
            'banner_ends_at' => 'nullable|date|after_or_equal:banner_starts_at',
            'channels' => 'nullable|array',
            'channels.homepage' => 'boolean',
            'channels.banner_dismissible' => 'boolean',
            'channels.banner_locked' => 'boolean',
            'channels.email' => 'boolean',
            'channels.whatsapp' => 'boolean',
        ]) + ['channels' => is_array($channels) ? $channels : []];
    }

    /** @param array<string, mixed> $data */
    private function authorizeCourseAccess(array $data): void
    {
        if (($data['target_mode'] ?? '') === Announcement::TARGET_COURSE && empty($data['course_id'])) {
            abort(422, __('announcements.course_required'));
        }

        if (! empty($data['course_id'])) {
            $this->authorizeAnnouncementCourse((int) $data['course_id']);
        }
    }

    private function authorizeAnnouncement(Announcement $announcement): void
    {
        $user = Auth::user();

        if ($user->isAdmin() || ($user->is_superadmin ?? false)) {
            return;
        }

        abort_unless(
            $announcement->course_id && in_array($announcement->course_id, $this->accessibleCourseIds($user), true),
            403
        );
    }

    private function authorizeAnnouncementCourse(int $courseId): void
    {
        $this->rosterService->authorizeCourse(Auth::user(), (string) $courseId);
    }

    /** @return \Illuminate\Support\Collection<int, Course> */
    private function accessibleCourses()
    {
        return $this->rosterService->accessibleCourses(Auth::user());
    }

    /** @return list<int> */
    private function accessibleCourseIds(User $user): array
    {
        return $this->rosterService->accessibleCourses($user)->pluck('course_id')->all();
    }
}
