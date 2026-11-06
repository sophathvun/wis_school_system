<?php

namespace App\Http\Controllers;

use App\Support\G9NationalExamPermissions as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class G9EoySettingController
{
    public function listing(Request $request)
    {
        abort_unless(Access::allows($request->user(),'settings'),403);
        $input=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id']]);
        $campuses=\App\Models\SchoolInfo::whereIn('id',Access::campusIds($request->user(),'settings'))->orderBy('campus_name_en')->get()->keyBy('id');
        $manageable=$campuses->keys()->filter(fn($id)=>Access::allows($request->user(),'eoy-settings-manage',(int)$id))->values();
        $year=\App\Models\AcademicYear::findOrFail($input['academic_year_id']);
        $shared=DB::table('tb_g9_eoy_campus_default')->whereIn('campus_id',$campuses->keys())->get()->keyBy('campus_id');
        $annual=DB::table('tb_g9_eoy_campus_setting')->where('academic_year_id',$input['academic_year_id'])->whereIn('campus_id',$campuses->keys())->get()->keyBy('campus_id');
        $rows=[];
        foreach($campuses as $campus){
            foreach(['all'=>$shared->get($campus->id),'year'=>$annual->get($campus->id)] as $scope=>$row){
                if(!$row)continue;
                $rows[]=['campus_id'=>$campus->id,'campus_name'=>$campus->campus_name_en,'scope'=>$scope,
                    'applies_to'=>$scope==='all'?'All academic years':$year->academic_year,
                    'id'=>$row->id,'version'=>$row->version,'compiler_name'=>$row->compiler_name??'','director_name'=>$row->director_name??'',
                    'can_manage'=>$manageable->contains($campus->id)];
            }
        }
        return response()->json(['rows'=>$rows,'academic_year'=>$year->academic_year,'manageable_campus_ids'=>$manageable,
            'can_create'=>$manageable->isNotEmpty()])->header('Cache-Control','private, no-store');
    }

    private function input(Request $request,bool $save=false): array
    {
        $data=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],
            'campus_id'=>['required','integer','exists:tb_school_info,id'],'scope'=>['sometimes','in:year,all']]+($save?[
                'version'=>['required','integer','min:0'],'compiler_name'=>['present','nullable','string','max:255'],'director_name'=>['present','nullable','string','max:255'],
            ]:[]));
        abort_unless(Access::allows($request->user(),$save?'eoy-settings-manage':'settings',(int)$data['campus_id']),403);
        return $data+['scope'=>'year'];
    }
    public function show(Request $request)
    {
        $input=$this->input($request);
        $year=DB::table('tb_g9_eoy_campus_setting')->where('academic_year_id',$input['academic_year_id'])->where('campus_id',$input['campus_id'])->first();
        $shared=DB::table('tb_g9_eoy_campus_default')->where('campus_id',$input['campus_id'])->first();
        $row=$input['scope']==='all'?$shared:$year;
        // Suggest the selected year's existing names when creating an all-year default.
        $names=$row??($input['scope']==='all'?$year:$shared);
        $source=$row?$input['scope']:($names?($input['scope']==='all'?'year-suggestion':'all'):'none');
        return response()->json(['version'=>$row?->version??0,'scope'=>$input['scope'],'source'=>$source,
            'compiler_name'=>$names?->compiler_name??'','director_name'=>$names?->director_name??'',
            'can_manage'=>Access::allows($request->user(),'eoy-settings-manage',(int)$input['campus_id'])])->header('Cache-Control','private, no-store');
    }
    public function save(Request $request)
    {
        $input=$this->input($request,true);
        DB::transaction(function()use($input,$request){
            // Serialize first writes and updates for both scopes within this campus.
            DB::table('tb_school_info')->where('id',$input['campus_id'])->lockForUpdate()->first();
            $table=$input['scope']==='all'?'tb_g9_eoy_campus_default':'tb_g9_eoy_campus_setting';
            $where=['campus_id'=>$input['campus_id']]+($input['scope']==='year'?['academic_year_id'=>$input['academic_year_id']]:[]);
            $row=DB::table($table)->where($where)->lockForUpdate()->first();
            abort_if(($row?->version??0)!==$input['version'],409,'These campus names changed in another window. Reload before saving.');
            $names=['compiler_name'=>trim($input['compiler_name']??''),'director_name'=>trim($input['director_name']??'')];
            DB::table($table)->updateOrInsert($where,$names+['version'=>$input['version']+1,'updated_by'=>$request->user()->id,'created_at'=>$row?->created_at??now(),'updated_at'=>now()]);
            DB::table('tb_g9_exam_setting_event')->insert(['academic_year_id'=>$input['academic_year_id'],'user_id'=>$request->user()->id,'action'=>$input['scope']==='all'?'eoy-campus-names-default':'eoy-campus-names',
                'details'=>json_encode(['campus_id'=>$input['campus_id'],'scope'=>$input['scope'],'before'=>['compiler_name'=>$row?->compiler_name,'director_name'=>$row?->director_name],'after'=>$names],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        });
        return $this->show($request);
    }

    public function destroy(Request $request)
    {
        $input=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],
            'campus_id'=>['required','integer','exists:tb_school_info,id'],'scope'=>['required','in:year,all'],
            'id'=>['required','integer','min:1'],'version'=>['required','integer','min:1']]);
        abort_unless(Access::allows($request->user(),'eoy-settings-manage',(int)$input['campus_id']),403);
        DB::transaction(function()use($input,$request){
            DB::table('tb_school_info')->where('id',$input['campus_id'])->lockForUpdate()->first();
            $table=$input['scope']==='all'?'tb_g9_eoy_campus_default':'tb_g9_eoy_campus_setting';
            $where=['campus_id'=>$input['campus_id'],'id'=>$input['id']]+($input['scope']==='year'?['academic_year_id'=>$input['academic_year_id']]:[]);
            $row=DB::table($table)->where($where)->lockForUpdate()->first();
            abort_if(!$row||$row->version!==$input['version'],409,'This configuration changed or was removed in another window. Refresh the list before removing it.');
            DB::table($table)->where($where)->delete();
            DB::table('tb_g9_exam_setting_event')->insert(['academic_year_id'=>$input['academic_year_id'],'user_id'=>$request->user()->id,
                'action'=>'remove-eoy-campus-names','details'=>json_encode(['campus_id'=>$input['campus_id'],'scope'=>$input['scope'],
                    'before'=>['id'=>$row->id,'version'=>$row->version,'compiler_name'=>$row->compiler_name,'director_name'=>$row->director_name],'after'=>null],JSON_THROW_ON_ERROR),
                'created_at'=>now(),'updated_at'=>now()]);
        });
        return response()->json(['message'=>'Campus name configuration removed.']);
    }
}
