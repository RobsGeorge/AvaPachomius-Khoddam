<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DemoController extends Controller
{
    public function index(): View
    {
        return view('demo.index', [
            'studentEmail'    => config('demo.users.student.email'),
            'instructorEmail' => config('demo.users.instructor.email'),
            'adminEmail'      => config('demo.users.admin.email'),
        ]);
    }

    public function enter(Request $request, string $role): RedirectResponse
    {
        $role = strtolower($role);
        if (! in_array($role, ['student', 'instructor', 'admin'], true)) {
            abort(404);
        }

        $email = config("demo.users.{$role}.email");
        $user = User::where('email', $email)->where('is_demo', true)->first();

        if (! $user) {
            return redirect()->route('demo.index')
                ->with('error', __('demo.not_seeded'));
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->put('demo_session', true);

        return redirect()->route('dashboard')
            ->with('success', __('demo.entered_as', ['role' => __("demo.role_{$role}")]));
    }
}
