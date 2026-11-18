<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (in_array($permission, array_column(\App\Support\AdditionalMenuPermissions::catalog(), 'code'), true)) {
            abort_unless(\App\Support\AdditionalMenuPermissions::allows($request->user(), $permission), 403);
            return $next($request);
        }
        $allowed = $permission === 'attendance.view'
            ? ($request->user() && \App\Support\AttendancePermissions::allows($request->user(), 'view'))
            : $request->user()?->hasPermission($permission, $request->attributes->get('campus_id'));
        abort_unless($allowed, 403);

        return $next($request);
    }
}
