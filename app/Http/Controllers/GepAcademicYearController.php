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
            'academic_year'=>['required','string','regex:/^\d{4}-\d{4}$/',Rule::unique('tb_gep_academic_year','academic_year')->ignore($year?->id)],
            'lifecycle_status'=>['required',Rule::in(['pending','started','finished'])],
        ]);
        [$first,$last]=array_map('intval',explode('-',$data['academic_year']));
        if($last!==$first+1)throw ValidationException::withMessages(['academic_year'=>'Use consecutive years, for example 2026-2027.']);
        if($data['lifecycle_status']==='started' && (!$year || $year->lifecycle_status!=='started'))$this->authorize($request,'start');
        $record=$this->persist($data,$year);
        return response()->json(['message'=>$year?'GEP academic year updated.':'GEP academic year created.','data'=>$record],$year?200:201);
    }

    public function start(Request $request,GepAcademicYear $year)
    {
        $this->authorize($request,'start');
        return response()->json(['message'=>'GEP academic year started.','data'=>$this->persist(['academic_year'=>$year->academic_year,'lifecycle_status'=>'started'],$year)]);
    }

    private function persist(array $data,?GepAcademicYear $year): GepAcademicYear
    {
        return DB::transaction(function()use($data,$year){
            GepAcademicYear::orderBy('id')->lockForUpdate()->get(['id']);
            if($data['lifecycle_status']==='started')GepAcademicYear::where('lifecycle_status','started')->when($year,fn($q)=>$q->where('id','!=',$year->id))->update(['lifecycle_status'=>'finished','started_slot'=>null]);
            $record=$year?GepAcademicYear::lockForUpdate()->findOrFail($year->id):new GepAcademicYear;
            $record->fill($data)->forceFill(['started_slot'=>$data['lifecycle_status']==='started'?1:null])->save();
            return $record;
        });
    }

    public function delete(Request $request,GepAcademicYear $year)
    {
        $this->authorize($request,'delete');
        DB::transaction(function()use($year){
            $record=GepAcademicYear::lockForUpdate()->findOrFail($year->id);
            if($record->lifecycle_status==='started')throw ValidationException::withMessages(['academic_year'=>'Finish this GEP academic year before deleting it.']);
            try{$record->delete();}catch(\Illuminate\Database\QueryException $e){
                if(in_array($e->getCode(),['23000','23503'],true))throw ValidationException::withMessages(['academic_year'=>'This GEP academic year is linked to data and cannot be deleted.']);
                throw $e;
            }
        });
        return response()->json(['message'=>'GEP academic year deleted.']);
    }
}
