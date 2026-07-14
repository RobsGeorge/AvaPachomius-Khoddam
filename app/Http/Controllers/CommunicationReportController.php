<?php

namespace App\Http\Controllers;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\CommunicationLogService;
use App\Services\CoursePermissionResolver;
use App\Services\RolePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CommunicationReportController extends Controller
{
    public function __construct(
        private CommunicationLogService $logs,
        private CoursePermissionResolver $resolver,
    ) {}

    public function index(Request $request)
    {
        $viewer = Auth::user();
        abort_unless($this->canView($viewer), 403);

        $courses = $this->logs->accessibleCourses($viewer, $this->resolver);
        $services = $this->logs->accessibleServices($viewer, $this->resolver);
        $unrestricted = $this->isUnrestricted($viewer);

        $filters = [
            'user_id' => $request->query('user_id'),
            'course_id' => $request->query('course_id'),
            'service_id' => $request->query('service_id'),
            'channel' => $request->query('channel'),
            'status' => $request->query('status'),
            'opened' => $request->query('opened'),
            'month' => $request->query('month'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'q' => $request->query('q'),
            'course_ids' => $unrestricted ? null : $courses->pluck('course_id')->all(),
            'service_ids' => $unrestricted ? null : $services->pluck('service_id')->all(),
            'unrestricted' => $unrestricted,
        ];

        if (! $unrestricted && ($filters['course_ids'] ?? []) === [] && ($filters['service_ids'] ?? []) === []) {
            $logs = CommunicationLog::query()->whereRaw('1 = 0')->paginate(40);
        } else {
            $logs = $this->logs->paginate($filters);
        }

        $people = User::query()
            ->whereIn('user_id', CommunicationLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id')->take(500))
            ->orderBy('first_name')
            ->limit(500)
            ->get();

        return view('communications.report', [
            'logs' => $logs,
            'filters' => $filters,
            'courses' => $courses,
            'services' => $services,
            'people' => $people,
            'channels' => CommunicationLog::channels(),
            'statuses' => CommunicationLog::statuses(),
        ]);
    }

    public function export(Request $request)
    {
        $viewer = Auth::user();
        abort_unless($this->canView($viewer), 403);

        $courses = $this->logs->accessibleCourses($viewer, $this->resolver);
        $services = $this->logs->accessibleServices($viewer, $this->resolver);
        $unrestricted = $this->isUnrestricted($viewer);

        $filters = [
            'user_id' => $request->query('user_id'),
            'course_id' => $request->query('course_id'),
            'service_id' => $request->query('service_id'),
            'channel' => $request->query('channel'),
            'status' => $request->query('status'),
            'opened' => $request->query('opened'),
            'month' => $request->query('month'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'q' => $request->query('q'),
            'course_ids' => $unrestricted ? null : $courses->pluck('course_id')->all(),
            'service_ids' => $unrestricted ? null : $services->pluck('service_id')->all(),
            'unrestricted' => $unrestricted,
        ];

        return $this->logs->exportCsv($filters);
    }

    public function trackOpen(string $token): Response
    {
        $this->logs->markOpenedByToken($token);

        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function canView(?User $viewer): bool
    {
        if (! $viewer instanceof User) {
            return false;
        }

        if (RolePreviewService::superadminBypassesPermissions($viewer)) {
            return true;
        }

        if ($viewer->canInSystem('communications.report')) {
            return true;
        }

        return $this->logs->accessibleCourses($viewer, $this->resolver)->isNotEmpty()
            || $this->logs->accessibleServices($viewer, $this->resolver)->isNotEmpty();
    }

    private function isUnrestricted(User $viewer): bool
    {
        return RolePreviewService::superadminBypassesPermissions($viewer)
            || $viewer->canInSystem('communications.report');
    }
}
