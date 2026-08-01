<?php

namespace App\Http\Middleware;

use App\Models\Church;
use App\Services\BreakGlass\BreakGlassService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks platform staff from church prod-data routes without an unexpired grant.
 * Fail-closed via BreakGlassService. Opt-in alias: breakglass.
 */
class EnsureBreakGlassGrant
{
    public function __construct(
        private BreakGlassService $breakGlass,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403, __('workspace.break_glass_denied'));
        }

        $church = $request->route('church');
        if (! $church instanceof Church) {
            abort(403, __('workspace.break_glass_denied'));
        }

        $this->breakGlass->assertAllowed($user, $church);

        return $next($request);
    }
}
