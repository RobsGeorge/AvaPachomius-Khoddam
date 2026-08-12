<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Billing\QuotaGuard;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreChurchRequest;
use App\Http\Requests\SuperAdmin\UpdateChurchRequest;
use App\Models\Church;
use App\Models\User;
use App\Services\BreakGlass\BreakGlassService;
use App\Services\ChurchMemberInviteService;
use App\Services\ChurchProvisioningService;
use App\Services\ChurchSlugSuggester;
use App\Services\PlaceLookupService;
use App\Services\PlatformAccessService;
use App\Services\RolePreviewService;
use App\Support\ChurchHost;
use App\Support\ChurchPlace;
use App\Tenancy\TenantDatabaseResolver;
use Illuminate\Http\Request;

class ChurchController extends Controller
{
    public function __construct(
        private ChurchProvisioningService $provisioning,
        private ChurchMemberInviteService $memberInvites,
        private QuotaGuard $quotaGuard,
        private ChurchSlugSuggester $slugSuggester,
        private PlaceLookupService $placeLookup,
        private BreakGlassService $breakGlass,
    ) {}

    public function index()
    {
        $churches = Church::query()
            ->withCount('members')
            ->orderBy('church_id')
            ->get();

        return view('superadmin.churches.index', [
            'churches' => $churches,
            'tenancyEnabled' => (bool) config('tenancy.enabled'),
        ]);
    }

    public function create()
    {
        return view('superadmin.churches.create', [
            'capabilities' => config('capabilities'),
            'users' => User::query()->orderBy('email')->limit(200)->get(['user_id', 'email', 'first_name', 'second_name']),
            'countries' => config('countries'),
        ]);
    }

    public function store(StoreChurchRequest $request)
    {
        $validated = $request->validated();

        $church = $this->provisioning->create(
            [
                'slug' => $validated['slug'],
                'name' => $validated['name'],
                'short_name' => $validated['short_name'],
                'domain' => $validated['domain'] ?? null,
                'capabilities' => $validated['capabilities'] ?? array_keys((array) config('capabilities')),
                'place_street' => $validated['place_street'] ?? null,
                'place_district' => $validated['place_district'] ?? null,
                'place_region' => $validated['place_region'] ?? null,
                'place_governorate' => $validated['place_governorate'] ?? null,
                'place_country_code' => $validated['place_country_code'],
            ],
            $validated['admin_user_ids'] ?? []
        );

        return redirect()
            ->route('superadmin.churches.show', $church)
            ->with('success', __('tenancy.church_created', ['name' => $church->shownName()]));
    }

    public function show(Church $church)
    {
        $church->load(['capabilities', 'members.user', 'roles' => fn ($q) => $q->whereNull('course_id')->whereNull('service_id')]);

        $quota = app(\App\Services\ChurchStorageQuotaService::class);
        $placement = TenantDatabaseResolver::resolvePlacementOrganization($church);
        $grants = $placement
            ? \App\Models\BreakGlassGrant::query()
                ->forOrganization((int) $placement->organization_id)
                ->with('staff')
                ->orderByDesc('granted_at')
                ->limit(20)
                ->get()
            : collect();

        return view('superadmin.churches.show', [
            'church' => $church,
            'host' => ChurchHost::hostFor($church),
            'url' => ChurchHost::url($church),
            'catalog' => config('capabilities'),
            'churchRoles' => $church->roles,
            'storageQuota' => $quota->quotaBytes($church),
            'storageUsed' => $quota->usedBytes($church),
            'storageRemaining' => $quota->remainingBytes($church),
            'storagePercent' => $quota->usagePercent($church),
            'placementOrganization' => $placement,
            'breakGlassGrants' => $grants,
            'activeBreakGlassGrant' => $placement && auth()->user()
                ? $this->breakGlass->activeGrant(auth()->user(), $placement)
                : null,
        ]);
    }

    public function edit(Church $church)
    {
        $church->load('capabilities');
        $enabled = $church->capabilities->where('enabled', true)->pluck('capability_key')->all();

        return view('superadmin.churches.edit', [
            'church' => $church,
            'capabilities' => config('capabilities'),
            'enabledCapabilities' => $enabled,
            'countries' => config('countries'),
        ]);
    }

    public function update(UpdateChurchRequest $request, Church $church)
    {
        $validated = $request->validated();

        if ($church->slug === config('tenancy.main_slug') && ($validated['status'] ?? '') === 'suspended') {
            return back()->withErrors(['status' => __('tenancy.cannot_suspend_main')]);
        }

        $newDomain = $validated['domain'] ?? null;
        if ($newDomain && $newDomain !== $church->domain && ! $this->quotaGuard->allowsCustomDomain($church)) {
            return back()->withErrors(['domain' => __('billing.custom_domain_not_allowed')]);
        }

        $this->provisioning->updateIdentity($church, [
            'name' => $validated['name'],
            'short_name' => $validated['short_name'],
            'domain' => $validated['domain'] ?? null,
            'status' => $validated['status'],
            'settings' => $validated['settings'] ?? $church->settings,
            'place_street' => $validated['place_street'] ?? null,
            'place_district' => $validated['place_district'] ?? null,
            'place_region' => $validated['place_region'] ?? null,
            'place_governorate' => $validated['place_governorate'] ?? null,
            'place_country_code' => $validated['place_country_code'],
        ]);

        $this->provisioning->syncCapabilities($church->fresh(), $validated['capabilities'] ?? []);

        return redirect()
            ->route('superadmin.churches.show', $church)
            ->with('success', __('tenancy.church_updated'));
    }

    public function suggestSlug(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:'.ChurchPlace::NAME_MAX],
            'short_name' => ['nullable', 'string', 'max:'.ChurchPlace::SHORT_NAME_MAX],
            'place_country_code' => ['nullable', 'string', 'size:2'],
            'place_governorate' => ['nullable', 'string', 'max:120'],
            'place_district' => ['nullable', 'string', 'max:120'],
        ]);

        $suggestions = $this->slugSuggester->suggest($validated);
        $shown = ChurchPlace::shownName([
            'short_name' => $validated['short_name'] ?? null,
            'name' => $validated['name'] ?? null,
            'place_district' => $validated['place_district'] ?? null,
            'place_governorate' => $validated['place_governorate'] ?? null,
            'place_country_code' => $validated['place_country_code'] ?? null,
        ]);

        return response()->json([
            'suggestions' => $suggestions,
            'shown_name' => $shown,
        ]);
    }

    public function searchPlaces(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        $results = $this->placeLookup->search(
            $validated['q'],
            $validated['country'] ?? null,
            5
        );

        return response()->json(['results' => $results]);
    }

    public function storeBreakGlassGrant(Request $request, Church $church)
    {
        abort_unless($request->user()?->is_superadmin, 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'in:15,60,240,1440'],
        ]);

        $organization = TenantDatabaseResolver::resolvePlacementOrganization($church);
        if (! $organization) {
            return back()->withErrors(['reason' => __('workspace.break_glass_no_organization')]);
        }

        $this->breakGlass->grant(
            $request->user(),
            $request->user(),
            $organization,
            $validated['reason'],
            (int) $validated['duration_minutes'],
        );

        return back()->with('success', __('workspace.break_glass_granted'));
    }

    public function revokeBreakGlassGrant(Request $request, Church $church, \App\Models\BreakGlassGrant $grant)
    {
        abort_unless($request->user()?->is_superadmin, 403);

        $organization = TenantDatabaseResolver::resolvePlacementOrganization($church);
        abort_unless(
            $organization
            && (int) $grant->organization_id === (int) $organization->organization_id,
            404
        );

        $this->breakGlass->revoke($request->user(), $grant);

        return back()->with('success', __('workspace.break_glass_revoked'));
    }

    public function platformEnter(Request $request, Church $church)
    {
        abort_unless($request->user()?->is_superadmin, 403);

        PlatformAccessService::start($church, $request->user(), $request);

        return redirect(ChurchHost::url($church, '/dashboard'))
            ->with('success', __('workspace.platform_access_started', ['church' => $church->preferredShortName()]));
    }

    public function platformEnterSigned(Request $request, Church $church)
    {
        abort_unless($request->user()?->is_superadmin, 403);

        PlatformAccessService::start($church, $request->user(), $request);

        return redirect()
            ->route('dashboard')
            ->with('success', __('workspace.platform_access_started', ['church' => $church->preferredShortName()]));
    }

    public function viewAsChurch(Request $request, Church $church)
    {
        $superadmin = $request->user();
        abort_unless($superadmin?->is_superadmin, 403);

        RolePreviewService::startChurchAdminRole($superadmin, $church, $request);

        return redirect(ChurchHost::url($church, '/dashboard'))
            ->with('success', __('workspace.view_as_church_started', ['church' => $church->preferredShortName()]));
    }

    public function viewAsChurchSigned(Request $request, Church $church)
    {
        $superadmin = $request->user();
        abort_unless($superadmin?->is_superadmin, 403);

        RolePreviewService::startChurchAdminRole($superadmin, $church, $request);

        return redirect()
            ->route('dashboard')
            ->with('success', __('workspace.view_as_church_started', ['church' => $church->preferredShortName()]));
    }

    public function suspend(Church $church)
    {
        $this->provisioning->suspend($church);

        return back()->with('success', __('tenancy.church_suspended'));
    }

    public function activate(Church $church)
    {
        $this->provisioning->activate($church);

        return back()->with('success', __('tenancy.church_activated'));
    }

    public function addMember(Request $request, Church $church)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'second_name' => ['nullable', 'string', 'max:120'],
            'third_name' => ['nullable', 'string', 'max:120'],
            'mobile_number' => ['nullable', 'string', 'max:40'],
            'role_id' => ['nullable', 'integer', 'exists:roles,role_id'],
            'send_email' => ['sometimes', 'boolean'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ]);

        $result = $this->memberInvites->addOrInvite($church, [
            ...$validated,
            'send_email' => $request->boolean('send_email', true),
            'send_whatsapp' => $request->boolean('send_whatsapp', false),
            'invited_by_user_id' => $request->user()?->user_id,
            'confirm_duplicate' => $request->boolean('confirm_duplicate', false),
        ]);

        $flash = $result['mode'] === 'invited'
            ? __('tenancy.member_invited')
            : __('tenancy.member_added');

        return back()->with('success', $flash);
    }

    public function removeMember(Church $church, User $user)
    {
        $this->provisioning->removeMember($church, $user);

        return back()->with('success', __('tenancy.member_removed'));
    }
}
