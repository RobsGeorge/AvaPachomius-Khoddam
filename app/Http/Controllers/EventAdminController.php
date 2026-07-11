<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventReservationException;
use App\Models\Role;
use App\Models\User;
use App\Services\EventAuditService;
use App\Services\EventReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EventAdminController extends Controller
{
    public function __construct(
        private EventReservationService $reservations,
    ) {}

    public function index()
    {
        $events = Event::with('creator')->orderByDesc('starts_at')->paginate(20);

        return view('events.admin.index', compact('events'));
    }

    public function create()
    {
        $courses = Course::orderBy('title')->get();
        $roles = Role::orderBy('role_name')->pluck('role_name');

        return view('events.admin.form', [
            'event' => new Event(['capacity' => 50, 'visibility' => 'institution']),
            'courses' => $courses,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedEvent($request);
        $data['created_by_id'] = $request->user()->user_id;
        $data['check_in_token'] = Event::generateCheckInToken();
        $data['status'] = Event::STATUS_DRAFT;

        $event = Event::create($data);

        EventAuditService::log('admin.create', 'success', ['event_id' => $event->event_id]);

        return redirect()->route('events.admin.edit', $event->event_id)
            ->with('success', __('events.admin_created'));
    }

    public function edit(Event $event)
    {
        $courses = Course::orderBy('title')->get();
        $roles = Role::orderBy('role_name')->pluck('role_name');

        return view('events.admin.form', compact('event', 'courses', 'roles'));
    }

    public function update(Request $request, Event $event)
    {
        $oldCapacity = $event->capacity;
        $data = $this->validatedEvent($request);
        $event->update($data);

        EventAuditService::log('admin.update', 'success', [
            'event_id' => $event->event_id,
            'capacity' => $data['capacity'],
        ]);

        $demoted = [];
        if ($data['capacity'] < $oldCapacity) {
            $demoted = $this->reservations->applyCapacityDecrease($event->fresh(), (int) $data['capacity']);
        }

        $message = __('events.admin_updated');
        if (count($demoted) > 0) {
            $message .= ' '.__('events.demoted_count', ['count' => count($demoted)]);
        }

        return redirect()->route('events.admin.reservations', $event->event_id)->with('success', $message);
    }

    public function publish(Event $event)
    {
        $event->update(['status' => Event::STATUS_PUBLISHED]);
        EventAuditService::log('admin.publish', 'published', ['event_id' => $event->event_id]);
        app(\App\Services\NotificationScannerService::class)->notifyEventPublished($event->fresh());

        return back()->with('success', __('events.admin_published'));
    }

    public function cancelEvent(Event $event)
    {
        $event->update(['status' => Event::STATUS_CANCELLED]);
        $count = $this->reservations->cancelAllForEvent($event, Auth::user());

        EventAuditService::log('admin.cancel_event', 'cancelled_event', [
            'event_id' => $event->event_id,
            'reservations_cancelled' => $count,
        ]);

        return back()->with('success', __('events.admin_cancelled', ['count' => $count]));
    }

    public function reservations(Event $event)
    {
        $event->load(['reservations.user', 'exceptions.user']);
        $users = User::orderBy('first_name')->limit(200)->get(['user_id', 'first_name', 'second_name', 'email']);

        return view('events.admin.reservations', compact('event', 'users'));
    }

    public function addException(Request $request, Event $event)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:user,user_id',
            'note' => 'nullable|string|max:255',
        ]);

        EventReservationException::firstOrCreate(
            ['event_id' => $event->event_id, 'user_id' => $data['user_id']],
            ['note' => $data['note'] ?? null, 'created_by_id' => Auth::id()]
        );

        EventAuditService::log('admin.exception.add', 'success', [
            'event_id' => $event->event_id,
            'user_id' => $data['user_id'],
        ]);

        return back()->with('success', __('events.exception_added'));
    }

    public function removeException(Event $event, int $exception)
    {
        $row = EventReservationException::where('event_id', $event->event_id)
            ->where('exception_id', $exception)
            ->firstOrFail();

        EventAuditService::log('admin.exception.remove', 'success', [
            'event_id' => $event->event_id,
            'user_id' => $row->user_id,
        ]);

        $row->delete();

        return back()->with('success', __('events.exception_removed'));
    }

    /** @return array<string, mixed> */
    private function validatedEvent(Request $request): array
    {
        $tz = config('attendance.timezone', 'Africa/Cairo');

        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'required|string',
            'location' => 'nullable|string|max:255',
            'starts_at' => 'required|date_format:Y-m-d\TH:i',
            'ends_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at',
            'capacity' => 'required|integer|min:1|max:100000',
            'registration_opens_at' => 'nullable|date_format:Y-m-d\TH:i',
            'registration_closes_at' => 'nullable|date_format:Y-m-d\TH:i',
            'course_id' => 'nullable|exists:course,course_id',
            'visibility' => 'required|in:institution,course_enrolled,role_based',
            'eligible_roles' => 'nullable|array',
            'eligible_roles.*' => 'string|max:30',
        ]);

        if (! empty($validated['registration_opens_at'])
            && ! empty($validated['registration_closes_at'])
            && $validated['registration_closes_at'] <= $validated['registration_opens_at']) {
            throw ValidationException::withMessages([
                'registration_closes_at' => [__('validation.after', [
                    'attribute' => 'registration closes at',
                    'date' => 'registration opens at',
                ])],
            ]);
        }

        $validated['starts_at'] = Carbon::createFromFormat('Y-m-d\TH:i', $validated['starts_at'], $tz)->utc();
        $validated['ends_at'] = Carbon::createFromFormat('Y-m-d\TH:i', $validated['ends_at'], $tz)->utc();
        $validated['registration_opens_at'] = ! empty($validated['registration_opens_at'])
            ? Carbon::createFromFormat('Y-m-d\TH:i', $validated['registration_opens_at'], $tz)->utc() : null;
        $validated['registration_closes_at'] = ! empty($validated['registration_closes_at'])
            ? Carbon::createFromFormat('Y-m-d\TH:i', $validated['registration_closes_at'], $tz)->utc() : null;

        $eligibleRoles = array_values($validated['eligible_roles'] ?? []);
        $validated['eligible_roles'] = $eligibleRoles === [] ? null : $eligibleRoles;

        return $validated;
    }
}
