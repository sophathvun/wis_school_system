<?php
namespace App\Http\Controllers;

use App\Models\GepClass;
use App\Support\{GepSettingsPermissions,SettingsQuery};
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule,ValidationException};

class GepClassController
{
    private function authorize(Request $request,string $action='view'):void
    {
        abort_unless(GepSettingsPermissions::allows($request->user(),'classes',$action),403);
    }

    public function fetch(Request $request)
    {
        $this->authorize($request);
        return response()->json(GepClass::query()->when(SettingsQuery::search($request),fn($q,$s)=>$q->where('class_name','like','%'.$s.'%'))
            ->orderBy('sort_order')->orderBy('class_name')->orderBy('id')->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request,?GepClass $class=null)
    {
        $this->authorize($request,$class?'edit':'create');
        $request->merge(['class_name'=>trim((string)$request->input('class_name'))]);
        $data=$request->validate([
            'sort_order'=>['sometimes','required','integer','min:0','max:2147483647'],
            'class_name'=>['required','string','max:100',Rule::unique('tb_gep_class','class_name')->ignore($class?->id)],
        ]);
        // Status changes have a separate permission and endpoint.
        if($class)$class->update($data);else $class=GepClass::create([...$data,'status'=>true]);
        return response()->json(['message'=>$request->isMethod('put')?'GEP class updated.':'GEP class created.','data'=>$class],$request->isMethod('put')?200:201);
    }

    public function status(Request $request,GepClass $class)
    {
        $this->authorize($request,'status');
        $data=$request->validate(['status'=>['required','boolean']]);
        $class->update($data);
        return response()->json(['message'=>'GEP class status updated.','data'=>$class]);
    }

    public function delete(Request $request,GepClass $class)
    {
        $this->authorize($request,'delete');
        try{$class->delete();}catch(\Illuminate\Database\QueryException $e){
            if(in_array($e->getCode(),['23000','23503'],true))throw ValidationException::withMessages(['class_name'=>'This GEP class is linked to data and cannot be deleted.']);
            throw $e;
        }
        return response()->json(['message'=>'GEP class deleted.']);
    }
}
