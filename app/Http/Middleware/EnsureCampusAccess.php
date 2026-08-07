<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCampusAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $campusId = $request->attributes->get('campus_id');

        abort_unless($user, 401);
        abort_unless($campusId && $user->canAccessCampus((int) $campusId), 403, 'Campus access is required.');

        return $next($request);
    }
}
