<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Services\ChurchProvisioningService;
use App\Support\ChurchHost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChurchController extends Controller
{
    public function __construct(
        private ChurchProvisioningService $provisioning,
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
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:120'],
            'domain' => ['nullable', 'string', 'max:191'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
            'admin_user_ids' => ['nullable', 'array'],
            'admin_user_ids.*' => ['integer', 'exists:user,user_id'],
        ]);

        $church = $this->provisioning->create(
            [
                'slug' => $validated['slug'],
                'name' => $validated['name'],
                'domain' => $validated['domain'] ?? null,
                'capabilities' => $validated['capabilities'] ?? array_keys((array) config('capabilities')),
            ],
            $validated['admin_user_ids'] ?? []
        );

        return redirect()
            ->route('superadmin.churches.show', $church)
            ->with('success', __('tenancy.church_created', ['name' => $church->name]));
    }

    public function show(Church $church)
    {
        $church->load(['capabilities', 'members.user', 'roles' => fn ($q) => $q->whereNull('course_id')->whereNull('service_id')]);

        return view('superadmin.churches.show', [
            'church' => $church,
            'host' => ChurchHost::hostFor($church),
            'url' => ChurchHost::url($church),
            'catalog' => config('capabilities'),
            'churchRoles' => $church->roles,
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
        ]);
    }

    public function update(Request $request, Church $church)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'domain' => ['nullable', 'string', 'max:191'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'settings' => ['nullable', 'array'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string'],
        ]);

        if ($church->slug === config('tenancy.main_slug') && $validated['status'] === 'suspended') {
            return back()->withErrors(['status' => __('tenancy.cannot_suspend_main')]);
        }

        $church->update([
            'name' => $validated['name'],
            'domain' => $validated['domain'] ?? null,
            'status' => $validated['status'],
            'settings' => $validated['settings'] ?? $church->settings,
        ]);

        $this->provisioning->syncCapabilities($church, $validated['capabilities'] ?? []);

        return redirect()
            ->route('superadmin.churches.show', $church)
            ->with('success', __('tenancy.church_updated'));
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
        $role = null;
        if (! empty($validated['role_id'])) {
            $role = Role::where('role_id', $validated['role_id'])
                ->where('church_id', $church->church_id)
                ->first();
        }

        $this->provisioning->addMember($church, $user, $role);

        return back()->with('success', __('tenancy.member_added'));
    }

    public function removeMember(Church $church, User $user)
    {
        $this->provisioning->removeMember($church, $user);

        return back()->with('success', __('tenancy.member_removed'));
    }
}
