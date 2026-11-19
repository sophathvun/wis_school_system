<?php
namespace App\Http\Controllers;

use App\Models\GepLevel;
use App\Support\{GepSettingsPermissions,SettingsQuery};
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule,ValidationException};

class GepLevelController
{
    private function authorize(Request $request,string $action='view'):void
    {
        abort_unless(GepSettingsPermissions::allows($request->user(),'levels',$action),403);
    }

    public function fetch(Request $request)
    {
        $this->authorize($request);
        return response()->json(GepLevel::query()->when(SettingsQuery::search($request),fn($q,$s)=>$q->where(fn($q)=>$q->where('level_name','like','%'.$s.'%')->orWhere('short_name','like','%'.$s.'%')->orWhere('type','like','%'.$s.'%')->orWhere('program','like','%'.$s.'%')))
            ->orderBy('sort_order')->orderBy('level_name')->orderBy('id')->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request,?GepLevel $level=null)
    {
        $this->authorize($request,$level?'edit':'create');
        $request->merge(['level_name'=>trim((string)$request->input('level_name')),'short_name'=>trim((string)$request->input('short_name'))]);
        $data=$request->validate([
            'type'=>['nullable','string','max:100'],
            'level_name'=>['required','string','max:100',Rule::unique('tb_gep_level','level_name')->ignore($level?->id)],
            'short_name'=>['required','string','max:30',Rule::unique('tb_gep_level','short_name')->ignore($level?->id)],
            'program'=>['nullable','string','max:100'],
            'sort_order'=>['sometimes','required','integer','min:0','max:2147483647'],
        ]);
        // Status changes have a separate permission and endpoint.
        if($level)$level->update($data);else $level=GepLevel::create([...$data,'status'=>true]);
        return response()->json(['message'=>$request->isMethod('put')?'GEP level updated.':'GEP level created.','data'=>$level],$request->isMethod('put')?200:201);
    }

    public function status(Request $request,GepLevel $level)
    {
        $this->authorize($request,'status');
        $data=$request->validate(['status'=>['required','boolean']]);
        $level->update($data);
        return response()->json(['message'=>'GEP level status updated.','data'=>$level]);
    }

    public function delete(Request $request,GepLevel $level)
    {
        $this->authorize($request,'delete');
        try{$level->delete();}catch(\Illuminate\Database\QueryException $e){
            if(in_array($e->getCode(),['23000','23503'],true))throw ValidationException::withMessages(['level_name'=>'This GEP level is linked to data and cannot be deleted.']);
            throw $e;
        }
        return response()->json(['message'=>'GEP level deleted.']);
    }
}
