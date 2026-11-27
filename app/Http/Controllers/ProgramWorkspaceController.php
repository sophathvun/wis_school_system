<?php

namespace App\Http\Controllers;

use App\Support\ProgramWorkspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramWorkspaceController
{
    public function switch(Request $request)
    {
        $data=$request->validate(['program'=>['required',Rule::in(array_keys(ProgramWorkspace::LABELS))]]);
        abort_unless(isset(ProgramWorkspace::available($request->user())[$data['program']]),403,'You do not have access to this program.');
        $request->user()->forceFill(['preferred_program'=>$data['program']])->saveQuietly();
        $request->session()->put('selected_program',$data['program']);
        return redirect()->route($data['program']==='gep'?'gep.dashboard':'dashboard');
    }

    public function gep(Request $request)
    {
        abort_unless(isset(ProgramWorkspace::available($request->user())['gep']),403);
        return view('gep.dashboard',app(\App\Services\GepDashboard::class)->data($request));
    }
}
