<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $allowed = $permission === 'attendance.view'
            ? ($request->user() && \App\Support\AttendancePermissions::allows($request->user(), 'view'))
            : $request->user()?->hasPermission($permission, $request->attributes->get('campus_id'));
        abort_unless($allowed, 403);

        return $next($request);
    }
}
