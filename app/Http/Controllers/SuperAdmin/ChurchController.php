<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Billing\QuotaGuard;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreChurchRequest;
use App\Http\Requests\SuperAdmin\UpdateChurchRequest;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Services\ChurchProvisioningService;
use App\Services\ChurchSlugSuggester;
use App\Services\PlaceLookupService;
use App\Services\PlatformAccessService;
use App\Services\RolePreviewService;
use App\Support\ChurchHost;
use App\Support\ChurchPlace;
use Illuminate\Http\Request;

class ChurchController extends Controller
{
    public function __construct(
        private ChurchProvisioningService $provisioning,
        private QuotaGuard $quotaGuard,
        private ChurchSlugSuggester $slugSuggester,
        private PlaceLookupService $placeLookup,
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
            'email' => ['required', 'email', 'exists:user,email'],
            'role_id' => ['nullable', 'integer', 'exists:roles,role_id'],
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();

        if (! $this->quotaGuard->canUse($church, 'max_active_users', 1)) {
            return back()->withErrors(['email' => __('billing.seat_quota_exceeded')]);
        }

        $role = null;
        if (! empty($validated['role_id'])) {
            $role = Role::where('role_id', $validated['role_id'])
                ->where('church_id', $church->church_id)
                ->first();
        }

        $this->provisioning->addMember($church, $user, $role);
        $this->quotaGuard->syncSeatUsage($church->fresh());

        return back()->with('success', __('tenancy.member_added'));
    }

    public function removeMember(Church $church, User $user)
    {
        $this->provisioning->removeMember($church, $user);

        return back()->with('success', __('tenancy.member_removed'));
    }
}
