<?php

namespace App\Http\Middleware;

use App\Support\ProgramWorkspace;
use Closure;
use Illuminate\Http\Request;

class ResolveProgramWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs('app.*', 'staff-card.*', 'student-id-card.public*', 'g9-certificate.verify', 'k3-certificate.verify', 'g12-certificate.verify', 'placement-test.*')) return $next($request);
        if (!$request->user()) return $next($request);
        $selected=ProgramWorkspace::selected($request->user());
        $request->attributes->set('active_program', $selected);
        if ($request->is('program-workspace/switch', 'logout', 'profile', 'profile/*',
            'notifications', 'notifications/*', 'communication/*', 'push/*', 'help', 'help/*', 'feedback',
            'login', 'login/*', 'forgot-password', 'reset-password', 'reset-password/*',
            'app/*', 'staff-card/*', 'student-card/*', 'certificates/*', 'placement-test/*',
            'settings/users', 'settings/users/*', 'settings/roles', 'settings/roles/*',
            'settings/access', 'settings/access/*', 'settings/departments', 'settings/departments/*', 'settings/positions', 'settings/positions/*')) {
            return $next($request);
        }
        abort_unless($selected, 403, 'You do not have access to a program workspace.');
        if ($request->is('gep', 'gep/*')) {
            abort_unless($selected==='gep' && isset(ProgramWorkspace::available($request->user())['gep']),403,'Switch to an authorized GEP workspace first.');
        } elseif ($selected==='gep') {
            if ($request->is('/', 'dashboard') && $request->isMethod('get') && !$request->expectsJson()) return redirect()->route('gep.dashboard');
            abort(403, 'This page belongs to WIS. Switch to an authorized WIS workspace first.');
        }
        return $next($request);
    }
}
