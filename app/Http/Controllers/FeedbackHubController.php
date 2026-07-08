<?php

namespace App\Http\Controllers;

use App\Services\FeedbackSurveyService;
use Illuminate\Support\Facades\Auth;

class FeedbackHubController extends Controller
{
    public function __construct(
        private FeedbackSurveyService $surveys
    ) {}

    public function index()
    {
        $user = Auth::user();

        if ($user->isInstructorOrAdmin()) {
            $surveys = $this->surveys->surveysForAdmin($user);

            return view('feedback.admin.index', compact('surveys'));
        }

        $surveys = $this->surveys->surveysForStudent($user);

        return view('feedback.index', compact('surveys'));
    }
}
