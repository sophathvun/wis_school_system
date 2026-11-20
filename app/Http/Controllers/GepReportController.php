<?php

namespace App\Http\Controllers;

use App\Support\GepReportPermissions;
use Illuminate\Http\Request;

class GepReportController
{
    public function index(Request $request, ?string $reportSection = null)
    {
        $sections = array_filter(GepReportPermissions::SECTIONS, fn ($key) => GepReportPermissions::allows($request->user(), $key), ARRAY_FILTER_USE_KEY);
        if ($reportSection === null) {
            abort_unless($sections, 403);
            return redirect()->route('gep.reports.section', ['reportSection' => array_key_first($sections)]);
        }
        abort_unless(isset(GepReportPermissions::SECTIONS[$reportSection]), 404);
        abort_unless(isset($sections[$reportSection]), 403);
        return view('gep.reports', ['current' => GepReportPermissions::SECTIONS[$reportSection]]);
    }
}
