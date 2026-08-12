<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\ChurchUser;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ChurchMemberInviteService;
use App\Services\ChurchProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembersController extends Controller
{
    use ResolvesTenantChurch;

    public function __construct(
        private ChurchMemberInviteService $invites,
        private ChurchProvisioningService $provisioning,
    ) {}

    public function index()
    {
        $church = $this->resolveChurch();
        $members = ChurchUser::query()
            ->where('church_id', $church->church_id)
            ->with('user')
            ->orderBy('status')
            ->orderBy('church_user_id')
            ->get();

        $churchRoles = Role::query()
            ->where('church_id', $church->church_id)
            ->whereNull('course_id')
            ->whereNull('service_id')
            ->where('is_template', false)
            ->orderBy('role_name')
            ->get();

        return view('church.members.index', compact('church', 'members', 'churchRoles'));
    }

    public function store(Request $request)
    {
        $church = $this->resolveChurch();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'second_name' => ['nullable', 'string', 'max:120'],
            'third_name' => ['nullable', 'string', 'max:120'],
            'mobile_number' => ['nullable', 'string', 'max:40'],
            'role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'role_id')->where(
                    fn ($q) => $q
                        ->where('church_id', $church->church_id)
                        ->whereNull('course_id')
                        ->whereNull('service_id')
                ),
            ],
            'send_email' => ['sometimes', 'boolean'],
            'send_whatsapp' => ['sometimes', 'boolean'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ]);

        $result = $this->invites->addOrInvite($church, [
            ...$validated,
            'send_email' => $request->boolean('send_email', true),
            'send_whatsapp' => $request->boolean('send_whatsapp', false),
            'invited_by_user_id' => $request->user()?->user_id,
            'confirm_duplicate' => $request->boolean('confirm_duplicate', false),
        ]);

        $flash = $result['mode'] === 'invited'
            ? __('tenancy.member_invited')
            : __('tenancy.member_added');

        return redirect()
            ->route('church.members.index')
            ->with('success', $flash);
    }

    public function destroy(User $user)
    {
        $church = $this->resolveChurch();
        $this->provisioning->removeMember($church, $user);

        AuditLogService::recordEvent('church.member_removed_self_service', [
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
        ]);

        return redirect()
            ->route('church.members.index')
            ->with('success', __('tenancy.member_removed'));
    }
}
