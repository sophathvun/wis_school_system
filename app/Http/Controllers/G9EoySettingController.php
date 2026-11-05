<?php

namespace App\Http\Controllers;

use App\Support\G9NationalExamPermissions as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class G9EoySettingController
{
    private function input(Request $request,bool $save=false): array
    {
        $data=$request->validate(['academic_year_id'=>['required','integer','exists:tb_academic_year,id'],
            'campus_id'=>['required','integer','exists:tb_school_info,id']]+($save?[
                'version'=>['required','integer','min:0'],'compiler_name'=>['present','nullable','string','max:255'],'director_name'=>['present','nullable','string','max:255'],
            ]:[]));
        abort_unless(Access::allows($request->user(),$save?'eoy-settings-manage':'settings',(int)$data['campus_id']),403);
        return $data;
    }
    public function show(Request $request)
    {
        $input=$this->input($request);
        $row=DB::table('tb_g9_eoy_campus_setting')->where('academic_year_id',$input['academic_year_id'])->where('campus_id',$input['campus_id'])->first();
        return response()->json(['version'=>$row?->version??0,'compiler_name'=>$row?->compiler_name??'','director_name'=>$row?->director_name??'',
            'can_manage'=>Access::allows($request->user(),'eoy-settings-manage',(int)$input['campus_id'])]);
    }
    public function save(Request $request)
    {
        $input=$this->input($request,true);
        DB::transaction(function()use($input,$request){
            // Serialize first writes as well as updates; the unique year/campus key is preserved.
            DB::table('tb_academic_year')->where('id',$input['academic_year_id'])->lockForUpdate()->first();
            $where=['academic_year_id'=>$input['academic_year_id'],'campus_id'=>$input['campus_id']];
            $row=DB::table('tb_g9_eoy_campus_setting')->where($where)->lockForUpdate()->first();
            abort_if(($row?->version??0)!==$input['version'],409,'These campus names changed in another window. Reload before saving.');
            $names=['compiler_name'=>trim($input['compiler_name']??''),'director_name'=>trim($input['director_name']??'')];
            DB::table('tb_g9_eoy_campus_setting')->updateOrInsert($where,$names+['version'=>$input['version']+1,'updated_by'=>$request->user()->id,'created_at'=>$row?->created_at??now(),'updated_at'=>now()]);
            DB::table('tb_g9_exam_setting_event')->insert(['academic_year_id'=>$input['academic_year_id'],'user_id'=>$request->user()->id,'action'=>'eoy-campus-names',
                'details'=>json_encode(['campus_id'=>$input['campus_id'],'before'=>['compiler_name'=>$row?->compiler_name,'director_name'=>$row?->director_name],'after'=>$names],JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        });
        return $this->show($request);
    }
}
