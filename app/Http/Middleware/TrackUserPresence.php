<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackUserPresence
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && (int) $request->user()->status !== 1) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['login' => 'Your account is inactive. Please contact an administrator.']);
        }

        if ($request->user() && (!$request->user()->last_seen_at || $request->user()->last_seen_at->lt(now()->subMinute()))) {
            $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
