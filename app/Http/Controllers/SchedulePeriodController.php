<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};

class SchedulePeriodController
{
    private function authorize(Request $request): void
    {
        abort_unless($request->user()->hasPermission('schedules.setup'),403);
    }

    public function index(Request $request)
    {
        $this->authorize($request);
        return view('schedules.session-setup');
    }

    public function listing(Request $request)
    {
        $this->authorize($request);
        return response()->json(['periods'=>DB::table('schedule_periods')->whereNull('deleted_at')->orderBy('display_order')->orderBy('starts_at')->get()->map(function($period){$period->applicable_groups=json_decode($period->applicable_groups??'[]',true)?:[];return $period;})]);
    }

    public function save(Request $request)
    {
        $this->authorize($request);
        $data=$request->validate([
            'id'=>['nullable','integer'],'version'=>['required','integer','min:0'],
            'name'=>['required','string','max:80'],'session'=>['required',Rule::in(['morning','afternoon'])],
            'starts_at'=>['required','date_format:H:i'],'ends_at'=>['required','date_format:H:i','after:starts_at'],
            'display_order'=>['required','integer','between:0,999'],'active'=>['required','boolean'],
            'applicable_groups'=>['required','array','min:1'],'applicable_groups.*'=>[Rule::in(['M','A','III'])],
            'full_day_grades'=>['sometimes','array'],'full_day_grades.*'=>[Rule::in(array_merge(['N','K1','K2','K3'],array_map('strval',range(1,12))))],
        ]);
        $data['name']=trim($data['name']);
        if($data['name']==='')throw ValidationException::withMessages(['name'=>'Enter a period name.']);
        if(($data['session']==='morning'&&$data['ends_at']>'12:00')||($data['session']==='afternoon'&&$data['starts_at']<'12:00')){
            throw ValidationException::withMessages(['session'=>'Morning periods must end by noon. Afternoon periods must start at or after noon.']);
        }
        DB::transaction(function()use($request,$data){
            DB::table('access_permissions')->where('code','schedules.setup')->lockForUpdate()->first();
            $existing=!empty($data['id'])?DB::table('schedule_periods')->where('id',$data['id'])->whereNull('deleted_at')->lockForUpdate()->firstOrFail():null;
            abort_if($existing&&$existing->version!==$data['version'],409,'This period changed. Reload before editing.');
            if($data['active']&&DB::table('schedule_periods')->where('active',true)->whereNull('deleted_at')->when($existing,fn($q)=>$q->where('id','!=',$existing->id))
                ->where('starts_at','<',$data['ends_at'])->where('ends_at','>',$data['starts_at'])->exists()){
                throw ValidationException::withMessages(['starts_at'=>'An active period already overlaps this time.']);
            }
            unset($data['id']);
            $data['applicable_groups']=json_encode(array_values(array_unique($data['applicable_groups'])));
            if(array_key_exists('full_day_grades',$data))$data['full_day_grades']=json_encode(array_values(array_unique($data['full_day_grades'])));
            $data['version']=($existing?->version??0)+1;
            $data['updated_by']=$request->user()->id;$data['updated_at']=now();
            if($existing)DB::table('schedule_periods')->where('id',$existing->id)->update($data);
            else DB::table('schedule_periods')->insert($data+['created_at'=>now()]);
        });
        return response()->json(['message'=>'Shared period saved. Existing published schedules retain their original times.']);
    }
}
