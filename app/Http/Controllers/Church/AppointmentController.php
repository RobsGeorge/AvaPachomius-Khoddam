<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\AppointmentBooking;
use App\Models\AppointmentSlot;
use App\Models\AppointmentType;
use App\Models\ChurchUser;
use App\Models\Priest;
use App\Models\PriestSecretary;
use App\Models\User;
use App\Services\Pastoral\AppointmentBookingService;
use App\Services\Pastoral\AppointmentSlotService;
use App\Services\Pastoral\PriestDelegationService;
use App\Support\Pastoral\BookingRules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    use ResolvesTenantChurch;

    public function __construct(
        private AppointmentSlotService $slots,
        private AppointmentBookingService $bookings,
        private PriestDelegationService $delegation,
    ) {}

    public function index(Request $request)
    {
        $church = $this->resolveChurch();
        $tz = BookingRules::for($church)->timezone;
        $weekStart = $this->resolveWeekStart($request->query('week'), $tz);

        $priestFilter = $request->query('priest_id') ? (int) $request->query('priest_id') : null;
        $typeFilter = $request->query('appointment_type_id') ? (int) $request->query('appointment_type_id') : null;
        $grid = $this->slots->weekGrid($weekStart->copy()->timezone($tz), $priestFilter, $typeFilter);

        $user = Auth::user();
        $myPriest = Priest::query()
            ->where('user_id', $user->user_id)
            ->where('status', Priest::STATUS_ACTIVE)
            ->first();

        $manageablePriestIds = $this->manageablePriestIds($user);
        $canManageAny = $manageablePriestIds !== [];
        $canBookOnBehalf = Gate::allows('bookOnBehalf', AppointmentBooking::class);

        $myBookings = AppointmentBooking::query()
            ->with(['slot.priest.user', 'slot.type'])
            ->where('user_id', $user->user_id)
            ->where('status', AppointmentBooking::STATUS_CONFIRMED)
            ->whereHas('slot', fn ($q) => $q->where('starts_at', '>=', now()->subDay()))
            ->get()
            ->sortBy(fn (AppointmentBooking $b) => $b->slot?->starts_at)
            ->values()
            ->take(20);

        $priests = Priest::query()->active()->with('user')->orderBy('priest_id')->get();
        $types = AppointmentType::query()->active()->orderBy('name_ar')->get();

        return view('church.appointments.index', [
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
            'types' => $types,
            'priestFilter' => $priestFilter,
            'typeFilter' => $typeFilter,
        ]);
    }

    public function typesIndex()
    {
        abort_unless($this->canManageTypes(Auth::user()), 403);

        $types = AppointmentType::query()->orderBy('name_ar')->get();

        return view('church.appointments.types_index', compact('types'));
    }

    public function typesCreate()
    {
        abort_unless($this->canManageTypes(Auth::user()), 403);

        return view('church.appointments.types_form', ['type' => null]);
    }

    public function typesStore(Request $request)
    {
        abort_unless($this->canManageTypes(Auth::user()), 403);
        $church = $this->resolveChurch();

        $validated = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'default_capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'default_duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'status' => ['required', Rule::in([AppointmentType::STATUS_ACTIVE, AppointmentType::STATUS_INACTIVE])],
        ]);

        $slugSource = $validated['slug'] ?? null;
        $slug = filled($slugSource)
            ? (string) $slugSource
            : (Str::slug($validated['name_en'] ?? '') ?: Str::slug($validated['name_ar']) ?: 'type-'.Str::random(6));

        $type = new AppointmentType([
            'slug' => $slug,
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'] ?? null,
            'default_capacity' => $validated['default_capacity'],
            'default_duration_minutes' => $validated['default_duration_minutes'],
            'status' => $validated['status'],
        ]);
        $type->church_id = $church->church_id;
        $type->save();

        return redirect()
            ->route('church.appointments.types.index')
            ->with('success', __('church_mgmt.appointment_type_saved'));
    }

    public function typesEdit(AppointmentType $type)
    {
        abort_unless($this->canManageTypes(Auth::user()), 403);

        return view('church.appointments.types_form', compact('type'));
    }

    public function typesUpdate(Request $request, AppointmentType $type)
    {
        abort_unless($this->canManageTypes(Auth::user()), 403);

        $validated = $request->validate([
            'name_ar' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash'],
            'default_capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'default_duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'status' => ['required', Rule::in([AppointmentType::STATUS_ACTIVE, AppointmentType::STATUS_INACTIVE])],
        ]);

        $type->update($validated);

        return redirect()
            ->route('church.appointments.types.index')
            ->with('success', __('church_mgmt.appointment_type_saved'));
    }

    public function create()
    {
        $priest = $this->requireManageablePriest();
        $types = AppointmentType::query()->active()->orderBy('name_ar')->get();
        abort_if($types->isEmpty(), 422, __('church_mgmt.appointment_type_required'));

        return view('church.appointments.create', compact('priest', 'types'));
    }

    public function store(Request $request)
    {
        $priest = $this->requireManageablePriest($request->input('priest_id') ? (int) $request->input('priest_id') : null);

        $validated = $request->validate([
            'appointment_type_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'location' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = AppointmentType::query()
            ->active()
            ->where('appointment_type_id', $validated['appointment_type_id'])
            ->firstOrFail();

        $this->slots->create($priest, $type, $validated);

        return redirect()
            ->route('church.appointments.index')
            ->with('success', __('church_mgmt.slot_created'));
    }

    public function generateForm()
    {
        $priest = $this->requireManageablePriest();
        $types = AppointmentType::query()->active()->orderBy('name_ar')->get();
        abort_if($types->isEmpty(), 422, __('church_mgmt.appointment_type_required'));

        return view('church.appointments.generate', compact('priest', 'types'));
    }

    public function generate(Request $request)
    {
        $priest = $this->requireManageablePriest($request->input('priest_id') ? (int) $request->input('priest_id') : null);

        $validated = $request->validate([
            'appointment_type_id' => ['required', 'integer'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'min:1', 'max:7'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i'],
            'weeks' => ['required', 'integer', 'min:1', 'max:26'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'location' => ['nullable', 'string', 'max:191'],
        ]);

        $type = AppointmentType::query()
            ->active()
            ->where('appointment_type_id', $validated['appointment_type_id'])
            ->firstOrFail();

        $created = $this->slots->generateWeekly(
            $priest,
            $type,
            $validated['weekdays'],
            $validated['time_start'],
            $validated['time_end'],
            (int) $validated['weeks'],
            isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            $validated['location'] ?? null,
        );

        return redirect()
            ->route('church.appointments.index')
            ->with('success', __('church_mgmt.slots_generated', ['count' => $created->count()]));
    }

    public function edit(AppointmentSlot $slot)
    {
        $this->authorize('update', $slot);
        $slot->load(['confirmedBookings.user', 'type']);
        $types = AppointmentType::query()->orderBy('name_ar')->get();

        return view('church.appointments.edit', compact('slot', 'types'));
    }

    public function update(Request $request, AppointmentSlot $slot)
    {
        $this->authorize('update', $slot);

        $validated = $request->validate([
            'appointment_type_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'location' => ['nullable', 'string', 'max:191'],
            'status' => ['required', Rule::in([
                AppointmentSlot::STATUS_OPEN,
                AppointmentSlot::STATUS_CLOSED,
                AppointmentSlot::STATUS_CANCELLED,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        AppointmentType::query()->where('appointment_type_id', $validated['appointment_type_id'])->firstOrFail();
        $this->slots->update($slot, $validated);

        return redirect()
            ->route('church.appointments.index')
            ->with('success', __('church_mgmt.slot_updated'));
    }

    public function setStatus(Request $request, AppointmentSlot $slot)
    {
        $this->authorize('update', $slot);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                AppointmentSlot::STATUS_OPEN,
                AppointmentSlot::STATUS_CLOSED,
                AppointmentSlot::STATUS_CANCELLED,
            ])],
        ]);

        $this->slots->setStatus($slot, $validated['status']);

        return back()->with('success', __('church_mgmt.slot_updated'));
    }

    public function book(Request $request, AppointmentSlot $slot)
    {
        $this->authorize('create', AppointmentBooking::class);

        $user = Auth::user();
        abort_unless($user, 403);

        try {
            $this->bookings->book($slot, $user, $user, $request->input('notes'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('church.appointments.index')
            ->with('success', __('church_mgmt.booking_confirmed'));
    }

    public function bookOnBehalfForm(AppointmentSlot $slot)
    {
        Gate::authorize('bookOnBehalf', AppointmentBooking::class);
        abort_unless($slot->isOpen() && $slot->remainingCapacity() > 0, 404);

        $members = $this->churchMembers();

        return view('church.appointments.book_on_behalf', compact('slot', 'members'));
    }

    public function bookOnBehalf(Request $request, AppointmentSlot $slot)
    {
        Gate::authorize('bookOnBehalf', AppointmentBooking::class);

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
            ->route('church.appointments.index')
            ->with('success', __('church_mgmt.booking_confirmed'));
    }

    public function myBookings()
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $bookings = AppointmentBooking::query()
            ->with(['slot.priest.user', 'slot.type'])
            ->where('user_id', $user->user_id)
            ->orderByDesc('appointment_booking_id')
            ->paginate(30);

        return view('church.appointments.my_bookings', compact('bookings'));
    }

    public function updateBookingNotes(Request $request, AppointmentBooking $booking)
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

    public function cancelBooking(AppointmentBooking $booking)
    {
        $this->authorize('cancel', $booking);

        try {
            $this->bookings->cancel($booking, Auth::user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', __('church_mgmt.booking_cancelled'));
    }

    public function rescheduleForm(AppointmentBooking $booking)
    {
        $this->authorize('update', $booking);
        $booking->load('slot.type');

        $alternatives = AppointmentSlot::query()
            ->with(['confirmedBookings', 'type'])
            ->where('priest_id', $booking->slot->priest_id)
            ->where('appointment_type_id', $booking->slot->appointment_type_id)
            ->where('status', AppointmentSlot::STATUS_OPEN)
            ->where('starts_at', '>', now())
            ->where('appointment_slot_id', '!=', $booking->appointment_slot_id)
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (AppointmentSlot $s) => $s->remainingCapacity() > 0);

        return view('church.appointments.reschedule', [
            'booking' => $booking,
            'alternatives' => $alternatives,
        ]);
    }

    public function reschedule(Request $request, AppointmentBooking $booking)
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'appointment_slot_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $newSlot = AppointmentSlot::findOrFail($validated['appointment_slot_id']);

        try {
            $this->bookings->reschedule($booking, $newSlot, Auth::user(), $validated['notes'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('church.appointments.my-bookings')
            ->with('success', __('church_mgmt.booking_rescheduled'));
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
            if ($this->delegation->canManageAppointmentSlots($user, $priest)) {
                $ids[] = (int) $priest->priest_id;
            }
        }

        return $ids;
    }

    private function canManageTypes(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', AppointmentSlot::class)
            || $this->manageablePriestIds($user) !== [];
    }

    private function requireManageablePriest(?int $priestId = null): Priest
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if ($priestId) {
            $priest = Priest::query()->where('priest_id', $priestId)->where('status', Priest::STATUS_ACTIVE)->firstOrFail();
            abort_unless($this->delegation->canManageAppointmentSlots($user, $priest), 403);

            return $priest;
        }

        $own = Priest::query()
            ->where('user_id', $user->user_id)
            ->where('status', Priest::STATUS_ACTIVE)
            ->first();

        if ($own && $this->delegation->canManageAppointmentSlots($user, $own)) {
            return $own;
        }

        $delegated = PriestSecretary::query()
            ->active()
            ->where('user_id', $user->user_id)
            ->with('priest')
            ->get()
            ->pluck('priest')
            ->filter(fn ($p) => $p && $this->delegation->canManageAppointmentSlots($user, $p))
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
