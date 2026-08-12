<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseApplication;
use App\Models\User;
use App\Services\ApplicationsHubQuery;
use App\Support\Applications\ApplicationQueueItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationsHubController extends Controller
{
    public function __construct(
        private ApplicationsHubQuery $hub,
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $this->hub->canAccessHub($user), 403);

        $typeFilter = $request->query('type');
        if ($typeFilter !== null && $typeFilter !== '' && ! in_array($typeFilter, ApplicationQueueItem::types(), true)) {
            $typeFilter = null;
        }

        $statusFilter = $request->query('filter');
        $allowedStatuses = CourseApplication::statuses();
        if ($statusFilter !== null && $statusFilter !== '' && ! in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = null;
        }

        $result = $this->hub->paginate(
            $user,
            $typeFilter ?: null,
            $statusFilter ?: null,
            max(1, (int) $request->query('page', 1))
        );

        return view('admin.applications-hub.index', [
            'items' => $result['items'],
            'counts' => $result['counts'],
            'typeCounts' => $result['type_counts'],
            'canSee' => $result['can_see'],
            'typeFilter' => $typeFilter ?: null,
            'filter' => $statusFilter ?: null,
            'statusKeys' => $allowedStatuses,
            'isPlatformReviewer' => (bool) ($result['can_see'][ApplicationQueueItem::TYPE_CHURCH] ?? false),
        ]);
    }
}
