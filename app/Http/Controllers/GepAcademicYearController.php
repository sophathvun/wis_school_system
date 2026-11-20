<?php

namespace App\Http\Controllers;

use App\Models\GepAcademicYear;
use App\Support\{GepSettingsPermissions,SettingsQuery};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};

class GepAcademicYearController
{
    private function authorize(Request $request,string $action='view'): void
    {
        abort_unless(GepSettingsPermissions::allows($request->user(),'academic-years',$action),403);
    }

    public function fetch(Request $request)
    {
        $this->authorize($request);
        return response()->json(GepAcademicYear::query()
            ->when(SettingsQuery::search($request),fn($q,$search)=>$q->where('academic_year','like','%'.$search.'%'))
            ->orderByDesc('academic_year')->orderByDesc('id')->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request,?GepAcademicYear $year=null)
    {
        $this->authorize($request,$year?'edit':'create');
        $data=$request->validate([
            'academic_year'=>['required','string','regex:/^[1-9]\d{3}$/',Rule::unique('tb_gep_academic_year','academic_year')->ignore($year?->id)],
        ]);
        $record=DB::transaction(function()use($data,$year){
            GepAcademicYear::orderBy('id')->lockForUpdate()->get(['id']);
            if($year){$record=GepAcademicYear::lockForUpdate()->findOrFail($year->id);$record->update($data);}
            else $record=GepAcademicYear::create([...$data,'status'=>true]);
            return $record;
        });
        return response()->json(['message'=>$year?'GEP academic year updated.':'GEP academic year created.','data'=>$record],$year?200:201);
    }

    public function status(Request $request,GepAcademicYear $year)
    {
        $this->authorize($request,'status');
        $data=$request->validate(['status'=>['required','boolean']]);
        $record=DB::transaction(function()use($data,$year){
            GepAcademicYear::orderBy('id')->lockForUpdate()->get(['id']);
            $record=GepAcademicYear::lockForUpdate()->findOrFail($year->id);
            if(!$data['status'] && \Illuminate\Support\Facades\Schema::hasTable('tb_gep_term_assignment') && DB::table('tb_gep_term_assignment')->where('academic_year_id',$record->id)->where('lifecycle_status','started')->exists())
                throw ValidationException::withMessages(['status'=>'Finish the Started term in Assign Terms before deactivating this academic year.']);
            $record->update($data);return $record;
        });
        return response()->json(['message'=>'GEP academic year status updated.','data'=>$record]);
    }

    public function delete(Request $request,GepAcademicYear $year)
    {
        $this->authorize($request,'delete');
        DB::transaction(function()use($year){
            $record=GepAcademicYear::lockForUpdate()->findOrFail($year->id);
            try{$record->delete();}catch(\Illuminate\Database\QueryException $e){
                if(in_array($e->getCode(),['23000','23503'],true))throw ValidationException::withMessages(['academic_year'=>'This GEP academic year is linked to data and cannot be deleted.']);
                throw $e;
            }
        });
        return response()->json(['message'=>'GEP academic year deleted.']);
    }
}
