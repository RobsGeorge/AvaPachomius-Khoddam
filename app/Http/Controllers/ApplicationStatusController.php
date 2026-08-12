<?php

namespace App\Http\Controllers;

use App\Models\RegistrationApplication;
use App\Models\RegistrationApplicationFieldReview;
use App\Models\User;
use App\Services\RegistrationApplicationService;
use App\Services\RegistrationReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ApplicationStatusController extends Controller
{
    public function __construct(
        private RegistrationApplicationService $applications,
        private RegistrationReviewService $review
    ) {}

    public function status()
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->registration_intent_course_id) {
            $courseApps = app(\App\Services\CourseApplicationService::class);
            $courseId = (int) $user->registration_intent_course_id;

            return redirect()->route(
                $courseApps->redirectRouteFor($user, $courseId),
                $courseApps->redirectParamsFor($courseId)
            );
        }

        $application = $this->applications->latestForUser($user);
        $application?->load('fieldReviews');

        $rejectedFields = $application
            ? $application->fieldReviews
                ->where('status', RegistrationApplicationFieldReview::STATUS_REJECTED)
                ->map(fn ($review) => [
                    'field_key' => $review->field_key,
                    'comment' => $review->comment,
                ])->values()
            : collect();

        return view('application.status', [
            'user' => $user,
            'application' => $application,
            'rejectedFields' => $rejectedFields,
        ]);
    }

    public function edit()
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->application_status !== RegistrationApplication::STATUS_NEEDS_CORRECTION) {
            return redirect()->route('application.status');
        }

        $application = $this->applications->latestForUser($user);
        $application?->load('fieldReviews');

        return view('application.edit', [
            'user' => $user,
            'application' => $application,
            'rejectedFields' => $application?->fieldReviews
                ->where('status', 'rejected')
                ->pluck('field_key')
                ->all() ?? [],
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->application_status !== RegistrationApplication::STATUS_NEEDS_CORRECTION) {
            return redirect()->route('application.status');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'regex:/^[\p{Arabic}\s]+$/u', 'max:50'],
            'second_name' => ['required', 'regex:/^[\p{Arabic}\s]+$/u', 'max:50'],
            'third_name' => ['required', 'regex:/^[\p{Arabic}\s]+$/u', 'max:50'],
            'national_id' => ['required', 'digits:14'],
            'email' => ['required', 'email', 'max:255'],
            'job' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'mobile_number' => ['required', 'numeric', 'digits:10'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $profilePhotoPath = null;
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            $profilePhotoPath = $request->file('profile_photo')->store('profile_photos', 'public');
        }

        $this->review->resubmit($user, $validated, $profilePhotoPath);

        return redirect()
            ->route('application.status')
            ->with('success', __('registration_review.resubmitted_success'));
    }
}
