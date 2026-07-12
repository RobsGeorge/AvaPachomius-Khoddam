<?php

namespace App\Http\Middleware;

use App\Services\CourseContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncCourseContextFromRoute
{
    public function __construct(
        private CourseContextService $courseContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $this->courseContext->syncFromRoute($user, $request->route('course'));
        }

        return $next($request);
    }
}
