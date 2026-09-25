<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\AdminPasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPasswordResetController extends Controller
{
    public function index(Request $request, AdminPasswordResetService $service): View
    {
        $actor = $request->user();
        abort_unless($actor && $service->canAccessPanel($actor), 403);

        $courses = $service->accessibleCourses($actor);
        $courseId = $request->query('course');
        if (is_string($courseId) && $courseId !== '' && ! $courses->contains('course_id', $courseId)) {
            $courseId = null;
        }
        $q = trim((string) $request->query('q', ''));
        $hasQuery = $q !== '' || filled($courseId);

        return view('students.password-reset', [
            'q' => $q,
            'courses' => $courses,
            'courseId' => $courseId,
            'students' => $hasQuery ? $service->search($actor, $q, $courseId ? (string) $courseId : null) : collect(),
            'hasQuery' => $hasQuery,
            'isSuperadminPanel' => $request->routeIs('superadmin.*'),
            'storeRoute' => $this->storeRouteName($request),
            'indexRoute' => $this->indexRouteName($request),
        ]);
    }

    public function store(Request $request, AdminPasswordResetService $service): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor && $service->canAccessPanel($actor), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:user,user_id'],
        ]);

        $subject = User::query()->findOrFail($data['user_id']);
        $result = $service->send($actor, $subject);

        $redirect = $request->input('from') === 'roster'
            ? back()
            : redirect()->route($this->indexRouteName($request), array_filter([
                'q' => $request->input('q', $subject->email),
                'course' => $request->input('course'),
            ], fn ($value) => $value !== null && $value !== ''));

        if (! ($result['ok'] ?? false)) {
            $reason = $result['reason'] ?? 'send_failed';

            return $redirect->with('error', __('students.password_reset_'.$reason));
        }

        return $redirect->with('success', __('students.password_reset_sent', [
            'name' => $subject->displayName(),
            'email' => $subject->email,
        ]));
    }

    private function indexRouteName(Request $request): string
    {
        return $request->routeIs('superadmin.*')
            ? 'superadmin.password-reset.index'
            : 'students.password-reset.index';
    }

    private function storeRouteName(Request $request): string
    {
        return $request->routeIs('superadmin.*')
            ? 'superadmin.password-reset.store'
            : 'students.password-reset.store';
    }
}
