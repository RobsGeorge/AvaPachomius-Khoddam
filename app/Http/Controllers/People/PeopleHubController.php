<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchService;
use App\Models\Course;
use App\Models\Invitation;
use App\Models\Person;
use App\Models\PersonPlacement;
use App\Models\Role;
use App\Services\People\InvitationService;
use App\Services\People\PersonDuplicateNeedsConfirmationException;
use App\Services\People\PersonPlacementService;
use App\Services\People\PersonRegistryService;
use App\Services\People\PortalAccountPreferenceResolver;
use App\Support\People\PlacementMode;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeopleHubController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePeople('people.view');

        $q = trim((string) $request->get('q', ''));
        $people = Person::query()
            ->active()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('display_name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('mobile_number', 'like', '%'.$q.'%')
                        ->orWhere('normalized_name', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('display_name')
            ->paginate(30)
            ->withQueryString();

        return view('people_onboarding.index', compact('people', 'q'));
    }

    public function create(PortalAccountPreferenceResolver $prefs)
    {
        $this->authorizePeople('people.create');

        $services = ChurchService::query()->orderBy('title')->get();
        $defaultInvite = false;

        return view('people_onboarding.create', compact('services', 'defaultInvite'));
    }

    public function store(
        Request $request,
        PersonRegistryService $registry,
        PersonPlacementService $placements,
        InvitationService $invitations,
        PortalAccountPreferenceResolver $prefs,
    ) {
        $this->authorizePeople('people.create');

        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'second_name' => ['nullable', 'string', 'max:100'],
            'third_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'],
            'mobile_number' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'service_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'intended_role_id' => ['nullable', 'integer'],
            'invite_now' => ['sometimes', 'boolean'],
            'send_email' => ['sometimes', 'boolean'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ]);

        if (! filled($validated['first_name'] ?? null) && ! filled($validated['second_name'] ?? null)) {
            return back()->withErrors(['first_name' => __('people_onboarding.import_name_required')])->withInput();
        }

        if (! filled($validated['email'] ?? null)
            && ! filled($validated['mobile_number'] ?? null)
            && ! filled($validated['national_id'] ?? null)
        ) {
            return back()->withErrors(['email' => __('people_onboarding.import_identity_required')])->withInput();
        }

        $churchId = TenantContext::id() ?? Church::main()?->church_id;
        $validated['church_id'] = $churchId;

        try {
            $person = $registry->createPerson($validated, (bool) ($validated['confirm_duplicate'] ?? false));
        } catch (PersonDuplicateNeedsConfirmationException $e) {
            return back()
                ->withInput()
                ->with('duplicate_matches', $e->matches)
                ->withErrors(['confirm_duplicate' => __('people_onboarding.confirm_duplicate')]);
        }

        $service = isset($validated['service_id'])
            ? ChurchService::find($validated['service_id'])
            : null;
        $course = isset($validated['course_id'])
            ? Course::find($validated['course_id'])
            : null;
        $role = isset($validated['intended_role_id'])
            ? Role::withoutTenancy()->find($validated['intended_role_id'])
            : null;

        $inviteNow = (bool) ($validated['invite_now'] ?? $prefs->defaultsInviteToPortal($course, $service));

        if ($service) {
            $placements->place(
                $person,
                $service,
                $course,
                $role,
                $inviteNow ? PlacementMode::PORTAL_PENDING : PlacementMode::INFO_ONLY,
            );
        }

        if ($inviteNow) {
            $this->authorizePeople('people.invite');
            $invitations->invite($person, [
                'send_email' => (bool) ($validated['send_email'] ?? filled($person->email)),
                'send_whatsapp' => (bool) ($validated['send_whatsapp'] ?? false),
                'service_id' => $service?->service_id,
                'course_id' => $course?->course_id,
                'intended_role_id' => $role?->role_id,
                'invited_by_user_id' => $request->user()?->user_id,
            ]);
        }

        return redirect()
            ->route('people.show', $person)
            ->with('success', __('people_onboarding.create_success'));
    }

    public function show(Person $person)
    {
        $this->authorizePeople('people.view');

        $placements = PersonPlacement::query()
            ->where('person_id', $person->person_id)
            ->with(['service', 'course', 'intendedRole'])
            ->orderByDesc('person_placement_id')
            ->get();

        $invitations = Invitation::query()
            ->where('person_id', $person->person_id)
            ->orderByDesc('invitation_id')
            ->limit(20)
            ->get();

        return view('people_onboarding.show', compact('person', 'placements', 'invitations'));
    }

    public function invite(
        Request $request,
        Person $person,
        InvitationService $invitations,
    ) {
        $this->authorizePeople('people.invite');

        $validated = $request->validate([
            'send_email' => ['sometimes', 'boolean'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'service_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'intended_role_id' => ['nullable', 'integer'],
        ]);

        $invitations->invite($person, array_merge($validated, [
            'invited_by_user_id' => $request->user()?->user_id,
        ]));

        return back()->with('success', __('people_onboarding.invite_success'));
    }

    private function authorizePeople(string $permission): void
    {
        $user = request()->user();
        abort_unless($user && (
            $user->is_superadmin
            || $user->canInSystem($permission)
            || $user->canInSystem('church.members.manage')
        ), 403);
    }
}
