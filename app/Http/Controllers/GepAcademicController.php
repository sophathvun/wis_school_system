<?php

namespace App\Http\Controllers;

use App\Support\GepAcademicPermissions;
use Illuminate\Http\Request;

class GepAcademicController
{
    public function index(Request $request, string $academicMenu)
    {
        abort_unless(isset(GepAcademicPermissions::MENUS[$academicMenu]), 404);
        abort_unless(GepAcademicPermissions::allows($request->user(), $academicMenu), 403);
        return view('gep.academic-module', ['current' => GepAcademicPermissions::MENUS[$academicMenu]]);
    }
}
