<?php

namespace App\Http\Middleware;

use App\Support\AcademicReportPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcademicReportPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $type = match ($request->route()?->getName()) {
            'reports.k3-certificates.save', 'reports.k3-certificates.template' => 'k3-certificate-wis',
            'reports.g9-certificates.save', 'reports.g9-certificates.template' => 'g9-certificate-wis',
            'reports.g12-certificates.save', 'reports.g12-certificates.template' => 'g12-certificate-wis',
            'reports.transcript-template' => 'moeys-sikkhakarik-book',
            default => $request->route('type') ?? $request->query('type'),
        };
        if ($type === null) {
            abort_unless(AcademicReportPermissions::visibleTypes($request->user()), 403);
        } else {
            abort_unless(is_string($type) && isset(AcademicReportPermissions::REPORTS[$type]), 404);
            abort_unless(AcademicReportPermissions::canView($request->user(), $type), 403);
        }

        return $next($request);
    }
}
