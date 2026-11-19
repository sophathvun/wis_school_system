<?php
namespace App\Http\Controllers;

use App\Models\GepGroup;
use App\Support\{GepSettingsPermissions,SettingsQuery};
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule,ValidationException};

class GepGroupController
{
    private function authorize(Request $request,string $action='view'):void
    {
        abort_unless(GepSettingsPermissions::allows($request->user(),'groups',$action),403);
    }

    public function fetch(Request $request)
    {
        $this->authorize($request);
        return response()->json(GepGroup::query()->when(SettingsQuery::search($request),fn($q,$s)=>$q->where(fn($q)=>$q->where('session_name','like','%'.$s.'%')->orWhere('group_code','like','%'.$s.'%')))
            ->orderBy('sort_order')->orderBy('session_name')->orderBy('id')->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request,?GepGroup $group=null)
    {
        $this->authorize($request,$group?'edit':'create');
        $request->merge(['session_name'=>trim((string)$request->input('session_name'))]);
        $data=$request->validate([
            'sort_order'=>['sometimes','required','integer','min:0','max:2147483647'],
            'session_name'=>['required',Rule::in(['Morning','Afternoon','Evening']),Rule::unique('tb_gep_group','session_name')->ignore($group?->id)],
        ]);
        $data['group_code']=['Morning'=>'M','Afternoon'=>'A','Evening'=>'E'][$data['session_name']];
        // Status changes have a separate permission and endpoint.
        if($group)$group->update($data);else $group=GepGroup::create([...$data,'status'=>true]);
        return response()->json(['message'=>$request->isMethod('put')?'GEP group updated.':'GEP group created.','data'=>$group],$request->isMethod('put')?200:201);
    }

    public function status(Request $request,GepGroup $group)
    {
        $this->authorize($request,'status');
        $data=$request->validate(['status'=>['required','boolean']]);
        $group->update($data);
        return response()->json(['message'=>'GEP group status updated.','data'=>$group]);
    }

    public function delete(Request $request,GepGroup $group)
    {
        $this->authorize($request,'delete');
        try{$group->delete();}catch(\Illuminate\Database\QueryException $e){
            if(in_array($e->getCode(),['23000','23503'],true))throw ValidationException::withMessages(['session_name'=>'This GEP group is linked to data and cannot be deleted.']);
            throw $e;
        }
        return response()->json(['message'=>'GEP group deleted.']);
    }
}
