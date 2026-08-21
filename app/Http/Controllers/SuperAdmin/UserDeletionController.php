<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\UserDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDeletionController extends Controller
{
    public function __construct(
        private UserDeletionService $deletion,
    ) {}

    public function index(Request $request): View
    {
        $name = trim((string) $request->query('name', ''));
        $churchId = $request->filled('church_id') ? (int) $request->query('church_id') : null;
        $serviceId = $request->filled('service_id') ? (int) $request->query('service_id') : null;
        $includeDeleted = $request->boolean('include_deleted');
        $hasFilters = $name !== '' || $churchId || $serviceId || $includeDeleted;

        $options = $this->deletion->filterOptions();

        return view('superadmin.users.index', [
            'name' => $name,
            'churchId' => $churchId,
            'serviceId' => $serviceId,
            'includeDeleted' => $includeDeleted,
            'hasFilters' => $hasFilters,
            'users' => $hasFilters
                ? $this->deletion->search($name, $churchId, $serviceId, $includeDeleted)
                : null,
            'churches' => $options['churches'],
            'services' => $options['services'],
        ]);
    }

    public function confirm(int $user): View
    {
        $target = $this->deletion->findManaged($user);

        return view('superadmin.users.confirm', [
            'target' => $target,
        ]);
    }

    public function softDelete(Request $request, int $user): RedirectResponse
    {
        $target = $this->deletion->findManaged($user);

        $request->validate([
            'notify_email' => ['sometimes', 'boolean'],
            'notify_whatsapp' => ['sometimes', 'boolean'],
        ]);

        $notices = $this->deletion->softDelete(
            $request->user(),
            $target,
            $request->boolean('notify_email'),
            $request->boolean('notify_whatsapp'),
        );

        $redirect = redirect()
            ->route('superadmin.users.index', [
                'name' => $target->email,
                'include_deleted' => 1,
            ])
            ->with('success', __('user_deletion.soft_deleted', ['name' => $target->displayName()]));

        $warning = $this->noticeWarning($notices);
        if ($warning) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function hardDelete(Request $request, int $user): RedirectResponse
    {
        $target = $this->deletion->findManaged($user);
        $displayName = $target->displayName();

        $request->validate([
            'confirmation' => ['required', 'string', 'max:255'],
            'acknowledge' => ['sometimes', 'boolean'],
            'notify_email' => ['sometimes', 'boolean'],
            'notify_whatsapp' => ['sometimes', 'boolean'],
        ]);

        $notices = $this->deletion->hardDelete(
            $request->user(),
            $target,
            (string) $request->input('confirmation'),
            $request->boolean('acknowledge'),
            $request->boolean('notify_email'),
            $request->boolean('notify_whatsapp'),
        );

        $redirect = redirect()
            ->route('superadmin.users.index')
            ->with('success', __('user_deletion.hard_deleted', ['name' => $displayName]));

        $warning = $this->noticeWarning($notices);
        if ($warning) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /**
     * @param  array{email: bool, whatsapp: bool, email_error?: string, whatsapp_error?: string}  $notices
     */
    private function noticeWarning(array $notices): ?string
    {
        if (! empty($notices['whatsapp_error'])) {
            return __('user_deletion.whatsapp_failed');
        }

        return null;
    }
}
