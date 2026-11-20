<?php
namespace App\Http\Controllers;

use App\Models\{GepAcademicYear,GepTerm,GepTermAssignment};
use App\Support\{GepSettingsPermissions,SettingsQuery};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};

class GepTermAssignmentController
{
    private function authorize(Request $request,string $action='view'):void
    {
        abort_unless(GepSettingsPermissions::allows($request->user(),'assign-terms',$action),403);
    }
    public function options(Request $request)
    {
        $this->authorize($request);
        return response()->json(['years'=>GepAcademicYear::orderByDesc('academic_year')->get(['id','academic_year','status']),
            'terms'=>GepTerm::orderBy('term_name')->get(['id','term_name','status'])]);
    }
    public function fetch(Request $request)
    {
        $this->authorize($request);
        $data=$request->validate(['academic_year_id'=>['nullable','integer',Rule::exists('tb_gep_academic_year','id')]]);
        $yearId=$data['academic_year_id']??null;
        $query=GepTermAssignment::with(['academicYear','term'])->when($yearId,fn($q)=>$q->where('academic_year_id',$yearId));
        $rows=$query->when(SettingsQuery::search($request),fn($q,$s)=>$q->where(fn($q)=>$q->whereHas('term',fn($q)=>$q->where('term_name','like','%'.$s.'%'))->orWhereHas('academicYear',fn($q)=>$q->where('academic_year','like','%'.$s.'%'))))
            ->orderByDesc('academic_year_id')->orderBy('sort_order')->paginate(SettingsQuery::perPage($request));
        $rows->getCollection()->each(function($row){
            $today=today()->toDateString();
            $row->schedule_notice=$row->lifecycle_status==='finished'?'':($today>$row->end_date?'Past End Date':($row->lifecycle_status==='pending'?($today<$row->start_date?'Upcoming':'Due to Start'):''));
        });
        $year=$yearId?GepAcademicYear::find($yearId):GepAcademicYear::where('status',true)->orderByDesc('academic_year')->first();
        $scope=fn()=>GepTermAssignment::with('term')->where('academic_year_id',$year?->id??0);
        return response()->json([...$rows->toArray(),'schedule_year'=>$year,'current_term'=>$scope()->where('lifecycle_status','started')->first(),
            'next_term'=>$scope()->where('lifecycle_status','pending')->orderBy('sort_order')->first()]);
    }
    public function save(Request $request,?GepTermAssignment $assignment=null)
    {
        $this->authorize($request,$assignment?'edit':'create');
        $data=$request->validate([
            'academic_year_id'=>['required','integer',Rule::exists('tb_gep_academic_year','id')],
            'term_id'=>['required','integer',Rule::exists('tb_gep_term','id')],
            'sort_order'=>['required','integer','min:1','max:2147483647'],
            'start_date'=>['required','date_format:Y-m-d'],
            'end_date'=>['required','date_format:Y-m-d','after_or_equal:start_date'],
        ]);
        $record=DB::transaction(function()use($data,$assignment){
            // Share the academic-year lock with year and term lifecycle changes.
            GepAcademicYear::orderBy('id')->lockForUpdate()->get(['id']);
            $record=$assignment?GepTermAssignment::lockForUpdate()->findOrFail($assignment->id):new GepTermAssignment;
            if($record->exists && $record->lifecycle_status!=='pending' && ($record->academic_year_id!=$data['academic_year_id'] || $record->term_id!=$data['term_id'] || $record->sort_order!=$data['sort_order']))
                throw ValidationException::withMessages(['term_id'=>'The year, term and order cannot change after a term has started.']);
            $term=GepTerm::findOrFail($data['term_id']);
            if(!$term->status && (!$record->exists || $record->term_id!=$term->id))throw ValidationException::withMessages(['term_id'=>'Select an enabled GEP term.']);
            $others=fn()=>GepTermAssignment::where('academic_year_id',$data['academic_year_id'])->when($record->exists,fn($q)=>$q->where('id','!=',$record->id));
            if($others()->where('term_id',$data['term_id'])->exists())throw ValidationException::withMessages(['term_id'=>'This term is already assigned to the selected year.']);
            if($others()->where('sort_order',$data['sort_order'])->exists())throw ValidationException::withMessages(['sort_order'=>'This order number is already used in the selected year.']);
            if((!$record->exists || $record->lifecycle_status==='pending') && $others()->whereIn('lifecycle_status',['started','finished'])->where('sort_order','>=',$data['sort_order'])->exists())
                throw ValidationException::withMessages(['sort_order'=>'Pending terms must follow terms that have already started or finished.']);
            $record->fill($data)->save();return $record;
        });
        return response()->json(['message'=>$assignment?'Term assignment updated.':'Term assigned.','data'=>$record->fresh(['academicYear','term'])],$assignment?200:201);
    }
    public function start(Request $request,GepTermAssignment $assignment)
    {
        $this->authorize($request,'start');
        $request->validate(['confirm_finish_current'=>['sometimes','boolean']]);
        DB::transaction(function()use($request,$assignment){
            GepAcademicYear::orderBy('id')->lockForUpdate()->get(['id']);
            $record=GepTermAssignment::lockForUpdate()->findOrFail($assignment->id);
            if($record->lifecycle_status!=='pending')throw ValidationException::withMessages(['term_id'=>'Only a Pending term can be started.']);
            if(!GepAcademicYear::findOrFail($record->academic_year_id)->status)throw ValidationException::withMessages(['academic_year_id'=>'Activate this GEP academic year first.']);
            if(!$record->term->status)throw ValidationException::withMessages(['term_id'=>'Enable this GEP term before starting it.']);
            $next=GepTermAssignment::where('academic_year_id',$record->academic_year_id)->where('lifecycle_status','pending')->orderBy('sort_order')->first();
            if($next->id!==$record->id)throw ValidationException::withMessages(['sort_order'=>'Start the next Pending term in order first.']);
            $current=GepTermAssignment::where('academic_year_id',$record->academic_year_id)->where('lifecycle_status','started')->lockForUpdate()->first();
            if($current){
                $this->authorize($request,'finish');
                if(!$request->boolean('confirm_finish_current'))throw ValidationException::withMessages(['confirm_finish_current'=>'Confirm finishing the current term before starting the next term.']);
                $current->forceFill(['lifecycle_status'=>'finished','started_slot'=>null])->save();
            }
            $record->forceFill(['lifecycle_status'=>'started','started_slot'=>1])->save();
        });
        return response()->json(['message'=>'GEP term started.']);
    }
    public function finish(Request $request,GepTermAssignment $assignment)
    {
        $this->authorize($request,'finish');
        DB::transaction(function()use($assignment){
            GepAcademicYear::orderBy('id')->lockForUpdate()->get(['id']);
            $record=GepTermAssignment::lockForUpdate()->findOrFail($assignment->id);
            if($record->lifecycle_status!=='started')throw ValidationException::withMessages(['term_id'=>'Only a Started term can be finished.']);
            $record->forceFill(['lifecycle_status'=>'finished','started_slot'=>null])->save();
        });
        return response()->json(['message'=>'GEP term finished.']);
    }
    public function delete(Request $request,GepTermAssignment $assignment)
    {
        $this->authorize($request,'delete');
        DB::transaction(function()use($assignment){
            GepAcademicYear::orderBy('id')->lockForUpdate()->get(['id']);
            $record=GepTermAssignment::lockForUpdate()->findOrFail($assignment->id);
            if($record->lifecycle_status!=='pending')throw ValidationException::withMessages(['term_id'=>'Only Pending term assignments can be deleted.']);
            try{$record->delete();}catch(\Illuminate\Database\QueryException $e){
                if(in_array($e->getCode(),['23000','23503'],true))throw ValidationException::withMessages(['term_id'=>'This assignment is linked to data and cannot be deleted.']);throw $e;
            }
        });
        return response()->json(['message'=>'Term assignment deleted.']);
    }
}
