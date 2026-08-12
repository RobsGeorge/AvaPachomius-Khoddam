<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\ChurchUser;
use App\Models\ConfessionBooking;
use App\Models\ConfessionSlot;
use App\Models\Priest;
use App\Models\PriestSecretary;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Pastoral\AppointmentIcsFeed;
use App\Services\Pastoral\ConfessionBookingService;
use App\Services\Pastoral\ConfessionSlotService;
use App\Services\Pastoral\PriestDelegationService;
use App\Support\Pastoral\BookingRules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConfessionController extends Controller
{
    use ResolvesTenantChurch;

    public function __construct(
        private ConfessionSlotService $slots,
        private ConfessionBookingService $bookings,
        private PriestDelegationService $delegation,
        private AppointmentIcsFeed $icsFeed,
    ) {}

    public function index(Request $request)
    {
        $church = $this->resolveChurch();
        $tz = BookingRules::for($church)->timezone;
        $weekStart = $this->resolveWeekStart($request->query('week'), $tz);

        $priestFilter = $request->query('priest_id') ? (int) $request->query('priest_id') : null;
        $grid = $this->slots->weekGrid($weekStart->copy()->timezone($tz), $priestFilter);

        $user = Auth::user();
        $myPriest = Priest::query()
            ->where('user_id', $user->user_id)
            ->where('status', Priest::STATUS_ACTIVE)
            ->first();

        $manageablePriestIds = $this->manageablePriestIds($user);
        $canManageAny = $manageablePriestIds !== [];
        $canBookOnBehalf = Gate::allows('bookOnBehalf', ConfessionBooking::class);

        $myBookings = ConfessionBooking::query()
            ->with(['slot.priest.user'])
            ->where('user_id', $user->user_id)
            ->where('status', ConfessionBooking::STATUS_CONFIRMED)
            ->whereHas('slot', fn ($q) => $q->where('starts_at', '>=', now()->subDay()))
            ->get()
            ->sortBy(fn (ConfessionBooking $b) => $b->slot?->starts_at)
            ->values()
            ->take(20);

        $priests = Priest::query()->active()->with('user')->orderBy('priest_id')->get();

        $icsAgendaLinks = $priests
            ->filter(fn (Priest $p) => in_array((int) $p->priest_id, $manageablePriestIds, true))
            ->map(fn (Priest $p) => ['priest' => $p, 'url' => $this->icsFeed->urlForPriest($p)])
            ->values();

        return view('church.confession.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->endOfWeek(Carbon::SUNDAY),
            'prevWeek' => $weekStart->copy()->subWeek()->format('Y-m-d'),
            'nextWeek' => $weekStart->copy()->addWeek()->format('Y-m-d'),
            'grid' => $grid,
            'timezone' => $tz,
            'myPriest' => $myPriest,
            'manageablePriestIds' => $manageablePriestIds,
            'canManageAny' => $canManageAny,
            'canBookOnBehalf' => $canBookOnBehalf,
            'myBookings' => $myBookings,
            'priests' => $priests,
            'priestFilter' => $priestFilter,
            'icsAgendaLinks' => $icsAgendaLinks,
        ]);
    }

    public function create()
    {
        $priest = $this->requireManageablePriest();

        return view('church.confession.create', compact('priest'));
    }

    public function store(Request $request)
    {
        $priest = $this->requireManageablePriest($request->input('priest_id'));

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'location' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->slots->create($priest, $validated);

        return redirect()
            ->route('church.confession.index')
            ->with('success', __('church_mgmt.slot_created'));
    }

    public function generateForm()
    {
        $priest = $this->requireManageablePriest();

        return view('church.confession.generate', compact('priest'));
    }

    public function generate(Request $request)
    {
        $priest = $this->requireManageablePriest($request->input('priest_id'));

        $validated = $request->validate([
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'min:1', 'max:7'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'weeks' => ['required', 'integer', 'min:1', 'max:26'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'location' => ['nullable', 'string', 'max:191'],
        ]);

        $created = $this->slots->generateWeekly(
            $priest,
            $validated['weekdays'],
            $validated['time_start'],
            $validated['time_end'],
            (int) $validated['weeks'],
            (int) $validated['capacity'],
            $validated['location'] ?? null,
        );

        return redirect()
            ->route('church.confession.index')
            ->with('success', __('church_mgmt.slots_generated', ['count' => $created->count()]));
    }

    public function edit(ConfessionSlot $slot)
    {
        $this->authorize('update', $slot);
        $slot->load(['confirmedBookings.user']);

        return view('church.confession.edit', ['slot' => $slot]);
    }

    public function update(Request $request, ConfessionSlot $slot)
    {
        $this->authorize('update', $slot);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'location' => ['nullable', 'string', 'max:191'],
            'status' => ['required', Rule::in([
                ConfessionSlot::STATUS_OPEN,
                ConfessionSlot::STATUS_CLOSED,
                ConfessionSlot::STATUS_CANCELLED,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->slots->update($slot, $validated);

        return redirect()
            ->route('church.confession.index')
            ->with('success', __('church_mgmt.slot_updated'));
    }

    public function setStatus(Request $request, ConfessionSlot $slot)
    {
        $this->authorize('update', $slot);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ConfessionSlot::STATUS_OPEN,
                ConfessionSlot::STATUS_CLOSED,
                ConfessionSlot::STATUS_CANCELLED,
            ])],
        ]);

        $this->slots->setStatus($slot, $validated['status']);

        return back()->with('success', __('church_mgmt.slot_updated'));
    }

    public function book(Request $request, ConfessionSlot $slot)
    {
        $this->authorize('create', ConfessionBooking::class);

        $user = Auth::user();
        abort_unless($user, 403);

        try {
            $this->bookings->book($slot, $user, $user, $request->input('notes'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('church.confession.index')
            ->with('success', __('church_mgmt.booking_confirmed'));
    }

    public function bookOnBehalfForm(ConfessionSlot $slot)
    {
        Gate::authorize('bookOnBehalf', ConfessionBooking::class);
        abort_unless($slot->isOpen() && $slot->remainingCapacity() > 0, 404);

        $members = $this->churchMembers();

        return view('church.confession.book_on_behalf', compact('slot', 'members'));
    }

    public function bookOnBehalf(Request $request, ConfessionSlot $slot)
    {
        Gate::authorize('bookOnBehalf', ConfessionBooking::class);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attendee = User::where('user_id', $validated['user_id'])->firstOrFail();
        $actor = Auth::user();

        try {
            $this->bookings->book($slot, $attendee, $actor, $validated['notes'] ?? null, onBehalf: true);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('church.confession.index')
            ->with('success', __('church_mgmt.booking_confirmed'));
    }

    public function myBookings()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $bookings = ConfessionBooking::query()
            ->with(['slot.priest.user'])
            ->where('user_id', $user->user_id)
            ->orderByDesc('confession_booking_id')
            ->paginate(30);

        $icsBookingsUrl = $this->icsFeed->urlForMember($user);

        return view('church.confession.my_bookings', compact('bookings', 'icsBookingsUrl'));
    }

    public function updateBookingNotes(Request $request, ConfessionBooking $booking)
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->bookings->updateNotes($booking, $validated['notes'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', __('church_mgmt.booking_notes_updated'));
    }

    public function cancelBooking(ConfessionBooking $booking)
    {
        $this->authorize('cancel', $booking);

        try {
            $this->bookings->cancel($booking, Auth::user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', __('church_mgmt.booking_cancelled'));
    }

    public function rescheduleForm(ConfessionBooking $booking)
    {
        $this->authorize('update', $booking);
        $booking->load('slot');

        $alternatives = ConfessionSlot::query()
            ->with('confirmedBookings')
            ->where('priest_id', $booking->slot->priest_id)
            ->where('status', ConfessionSlot::STATUS_OPEN)
            ->where('starts_at', '>', now())
            ->where('confession_slot_id', '!=', $booking->confession_slot_id)
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (ConfessionSlot $s) => $s->remainingCapacity() > 0);

        return view('church.confession.reschedule', [
            'booking' => $booking,
            'alternatives' => $alternatives,
        ]);
    }

    public function reschedule(Request $request, ConfessionBooking $booking)
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'confession_slot_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $newSlot = ConfessionSlot::findOrFail($validated['confession_slot_id']);

        try {
            $this->bookings->reschedule($booking, $newSlot, Auth::user(), $validated['notes'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('church.confession.my-bookings')
            ->with('success', __('church_mgmt.booking_rescheduled'));
    }

    public function secretaries(Priest $priest)
    {
        abort_unless($this->delegation->canAssignSecretaries(Auth::user(), $priest), 403);
        $priest->load(['secretaries.user', 'user']);

        $members = $this->churchMembers();

        return view('church.confession.secretaries', compact('priest', 'members'));
    }

    public function storeSecretary(Request $request, Priest $priest)
    {
        abort_unless($this->delegation->canAssignSecretaries(Auth::user(), $priest), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        abort_unless(
            ChurchUser::query()
                ->where('church_id', $priest->church_id)
                ->where('user_id', $validated['user_id'])
                ->where('status', 'active')
                ->exists(),
            422,
            __('church_mgmt.member_required')
        );

        $row = PriestSecretary::query()->firstOrNew([
            'priest_id' => $priest->priest_id,
            'user_id' => $validated['user_id'],
        ]);
        $row->church_id = $priest->church_id;
        $row->status = PriestSecretary::STATUS_ACTIVE;
        $row->save();

        AuditLogService::recordEvent('priest_secretary.saved', [
            'priest_secretary_id' => $row->priest_secretary_id,
            'priest_id' => $priest->priest_id,
            'user_id' => $validated['user_id'],
        ]);

        return back()->with('success', __('church_mgmt.secretary_saved'));
    }

    public function removeSecretary(Priest $priest, PriestSecretary $secretary)
    {
        abort_unless($this->delegation->canAssignSecretaries(Auth::user(), $priest), 403);
        abort_unless((int) $secretary->priest_id === (int) $priest->priest_id, 404);

        $secretary->update(['status' => PriestSecretary::STATUS_INACTIVE]);

        AuditLogService::recordEvent('priest_secretary.removed', [
            'priest_secretary_id' => $secretary->priest_secretary_id,
            'priest_id' => $priest->priest_id,
        ]);

        return back()->with('success', __('church_mgmt.secretary_removed'));
    }

    private function resolveWeekStart(?string $week, string $tz): Carbon
    {
        try {
            $base = $week ? Carbon::parse($week, $tz) : now($tz);
        } catch (\Throwable) {
            $base = now($tz);
        }

        return $base->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /** @return list<int> */
    private function manageablePriestIds(User $user): array
    {
        $ids = [];
        foreach (Priest::query()->active()->get() as $priest) {
            if ($this->delegation->canManageConfessionSlots($user, $priest)) {
                $ids[] = (int) $priest->priest_id;
            }
        }

        return $ids;
    }

    private function requireManageablePriest(?int $priestId = null): Priest
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if ($priestId) {
            $priest = Priest::query()->where('priest_id', $priestId)->where('status', Priest::STATUS_ACTIVE)->firstOrFail();
            abort_unless($this->delegation->canManageConfessionSlots($user, $priest), 403);

            return $priest;
        }

        $own = Priest::query()
            ->where('user_id', $user->user_id)
            ->where('status', Priest::STATUS_ACTIVE)
            ->first();

        if ($own && $this->delegation->canManageConfessionSlots($user, $own)) {
            return $own;
        }

        $delegated = PriestSecretary::query()
            ->active()
            ->where('user_id', $user->user_id)
            ->with('priest')
            ->get()
            ->pluck('priest')
            ->filter(fn ($p) => $p && $this->delegation->canManageConfessionSlots($user, $p))
            ->first();

        abort_unless($delegated, 403, __('church_mgmt.not_a_priest'));

        return $delegated;
    }

    private function churchMembers()
    {
        $church = $this->resolveChurch();

        return ChurchUser::query()
            ->where('church_id', $church->church_id)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->sortBy(fn (User $u) => trim(($u->first_name ?? '').' '.($u->second_name ?? '')))
            ->values();
    }
}
