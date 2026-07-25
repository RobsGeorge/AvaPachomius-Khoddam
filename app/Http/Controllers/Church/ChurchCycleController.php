<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\ChurchSchoolYear;
use App\Models\ChurchService;
use App\Services\Structure\ChurchCycleSeasonService;
use App\Support\Structure\SchoolYearStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ChurchCycleController extends Controller
{
    use ResolvesTenantChurch;

    public function __construct(
        private ChurchCycleSeasonService $seasons,
    ) {}

    public function index()
    {
        $church = $this->resolveChurch();
        $dashboard = $this->seasons->dashboard($church);
        $history = ChurchSchoolYear::query()
            ->orderByDesc('starts_on')
            ->limit(10)
            ->get();

        return view('church.cycle.index', [
            'church' => $church,
            'year' => $dashboard['year'],
            'rows' => $dashboard['rows'],
            'summary' => $dashboard['summary'],
            'history' => $history,
            'canManage' => $this->userCanManage(),
        ]);
    }

    public function storeYear(Request $request)
    {
        abort_unless($this->userCanManage(), 403);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:64'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in([SchoolYearStatus::PLANNED, SchoolYearStatus::ACTIVE])],
        ]);

        $this->seasons->createYear($this->resolveChurch(), $validated, Auth::user());

        return redirect()
            ->route('church.cycle.index')
            ->with('success', __('church_cycle.year_created'));
    }

    public function startPromotion(Request $request, ChurchSchoolYear $year)
    {
        abort_unless($this->userCanManage(), 403);
        $this->assertYearInChurch($year);

        $this->seasons->startPromotionSeason($year, Auth::user());

        return redirect()
            ->route('church.cycle.index')
            ->with('success', __('church_cycle.promotion_started'));
    }

    public function closeYear(Request $request, ChurchSchoolYear $year)
    {
        abort_unless($this->userCanManage(), 403);
        $this->assertYearInChurch($year);

        $force = $request->boolean('force');
        $this->seasons->closeYear($year, Auth::user(), $force);

        return redirect()
            ->route('church.cycle.index')
            ->with('success', __('church_cycle.year_closed'));
    }

    public function markServiceDone(Request $request, ChurchSchoolYear $year, ChurchService $service)
    {
        abort_unless($this->userCanManage(), 403);
        $this->assertYearInChurch($year);

        $this->seasons->markServiceDone($year, $service, Auth::user());

        return redirect()
            ->route('church.cycle.index')
            ->with('success', __('church_cycle.service_marked_done'));
    }

    private function userCanManage(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $church = $this->resolveChurch();

        return ($user->is_superadmin ?? false)
            || $user->canInChurch('church.cycle.manage', $church);
    }

    private function assertYearInChurch(ChurchSchoolYear $year): void
    {
        $church = $this->resolveChurch();
        abort_unless((int) $year->church_id === (int) $church->church_id, 404);
    }
}
