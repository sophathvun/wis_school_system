<?php

namespace App\Http\Middleware;

use App\Services\CampusContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCampusContext
{
    public function __construct(private readonly CampusContext $campusContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $campus = $this->campusContext->resolve($request, $request->user());
            $request->attributes->set('campus', $campus);
            $request->attributes->set('campus_id', $campus?->id);
        }

        return $next($request);
    }
}
