<?php
namespace App\Http\Controllers;

use App\Support\GepStudentPermissions;
use Illuminate\Http\Request;

class GepStudentController
{
    public function index(Request $request,?string $studentSection=null)
    {
        $sections=array_filter(GepStudentPermissions::SECTIONS,fn($key)=>GepStudentPermissions::allows($request->user(),$key),ARRAY_FILTER_USE_KEY);
        if($studentSection===null){abort_unless($sections,403);return redirect()->route('gep.students.section',['studentSection'=>array_key_first($sections)]);}
        abort_unless(isset(GepStudentPermissions::SECTIONS[$studentSection]),404);
        abort_unless(isset($sections[$studentSection]),403);
        if($studentSection==='enrollment')return app(GepEnrollmentController::class)->index($request);
        if($studentSection==='import-export')return app(GepDataTransferController::class)->index($request);
        return view('gep.students',['current'=>GepStudentPermissions::SECTIONS[$studentSection]]);
    }
}
