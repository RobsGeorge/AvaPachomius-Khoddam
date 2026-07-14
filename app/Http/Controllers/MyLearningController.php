<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MyLearningService;
use Illuminate\Support\Facades\Auth;

/**
 * F-02 — the unified "My learning" view for students: grades, attendance, and
 * certificates across every enrolled course in one place.
 */
class MyLearningController extends Controller
{
    public function index(MyLearningService $myLearning): \Illuminate\View\View
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(500, 'Authenticated user is not a valid User instance.');
        }

        return view('my-learning.index', [
            'courseCards' => $myLearning->courseCards($user),
        ]);
    }
}
