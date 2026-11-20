<?php

namespace App\Http\Controllers;

use App\Support\GepSettingsPermissions;
use Illuminate\Http\Request;

class GepSettingsController
{
    public function index(Request $request, ?string $section = null)
    {
        $sections=array_filter(GepSettingsPermissions::SECTIONS,fn($key)=>GepSettingsPermissions::allows($request->user(),$key),ARRAY_FILTER_USE_KEY);
        if($section===null){
            abort_unless($sections,403);
            return redirect()->route('gep.settings.section',['section'=>array_key_first($sections)]);
        }
        abort_unless(isset(GepSettingsPermissions::SECTIONS[$section]),404);
        abort_unless(isset($sections[$section]),403);
        if($section==='academic-years')return view('gep.academic-years',[
            'permissions'=>collect(['create','edit','delete','status'])->mapWithKeys(fn($action)=>[$action=>GepSettingsPermissions::allows($request->user(),$section,$action)])->all(),
        ]);
        if($section==='terms')return view('gep.terms',['permissions'=>collect(['create','edit','delete','status'])->mapWithKeys(fn($action)=>[$action=>GepSettingsPermissions::allows($request->user(),$section,$action)])->all()]);
        if($section==='levels')return view('gep.levels',['permissions'=>collect(['create','edit','delete','status'])->mapWithKeys(fn($action)=>[$action=>GepSettingsPermissions::allows($request->user(),$section,$action)])->all()]);
        if($section==='classes')return view('gep.classes',['permissions'=>collect(['create','edit','delete','status'])->mapWithKeys(fn($action)=>[$action=>GepSettingsPermissions::allows($request->user(),$section,$action)])->all()]);
        if($section==='groups')return view('gep.groups',['permissions'=>collect(['create','edit','delete','status'])->mapWithKeys(fn($action)=>[$action=>GepSettingsPermissions::allows($request->user(),$section,$action)])->all()]);
        if($section==='class-types')return view('gep.class-types',['permissions'=>collect(['create','edit','delete','status'])->mapWithKeys(fn($action)=>[$action=>GepSettingsPermissions::allows($request->user(),$section,$action)])->all()]);
        if($section==='assign-terms')return view('gep.assign-terms',['permissions'=>collect(['create','edit','delete','start','finish'])->mapWithKeys(fn($action)=>[$action=>GepSettingsPermissions::allows($request->user(),$section,$action)])->all()]);
        return view('gep.settings',['sections'=>$sections,'section'=>$section,'current'=>GepSettingsPermissions::SECTIONS[$section]]);
    }
}
