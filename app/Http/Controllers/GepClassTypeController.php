<?php
namespace App\Http\Controllers;

use App\Models\GepClassType;
use App\Support\{GepSettingsPermissions,SettingsQuery};
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule,ValidationException};

class GepClassTypeController
{
    private function authorize(Request $request,string $action='view'):void
    {
        abort_unless(GepSettingsPermissions::allows($request->user(),'class-types',$action),403);
    }

    public function fetch(Request $request)
    {
        $this->authorize($request);
        return response()->json(GepClassType::query()->when(SettingsQuery::search($request),fn($q,$s)=>$q->where('type_name','like','%'.$s.'%'))
            ->orderBy('sort_order')->orderBy('type_name')->orderBy('id')->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request,?GepClassType $classType=null)
    {
        $this->authorize($request,$classType?'edit':'create');
        $request->merge(['type_name'=>trim((string)$request->input('type_name'))]);
        $data=$request->validate([
            'sort_order'=>['sometimes','required','integer','min:0','max:2147483647'],
            'type_name'=>['required','string','max:100',Rule::unique('tb_gep_class_type','type_name')->ignore($classType?->id)],
        ]);
        // Status changes have a separate permission and endpoint.
        if($classType)$classType->update($data);else $classType=GepClassType::create([...$data,'status'=>true]);
        return response()->json(['message'=>$request->isMethod('put')?'GEP class type updated.':'GEP class type created.','data'=>$classType],$request->isMethod('put')?200:201);
    }

    public function status(Request $request,GepClassType $classType)
    {
        $this->authorize($request,'status');
        $data=$request->validate(['status'=>['required','boolean']]);
        $classType->update($data);
        return response()->json(['message'=>'GEP class type status updated.','data'=>$classType]);
    }

    public function delete(Request $request,GepClassType $classType)
    {
        $this->authorize($request,'delete');
        try{$classType->delete();}catch(\Illuminate\Database\QueryException $e){
            if(in_array($e->getCode(),['23000','23503'],true))throw ValidationException::withMessages(['type_name'=>'This GEP class type is linked to data and cannot be deleted.']);
            throw $e;
        }
        return response()->json(['message'=>'GEP class type deleted.']);
    }
}
