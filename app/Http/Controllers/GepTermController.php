<?php
namespace App\Http\Controllers;

use App\Models\GepTerm;
use App\Support\{GepSettingsPermissions,SettingsQuery};
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule,ValidationException};

class GepTermController
{
    private function authorize(Request $request,string $action='view'):void
    {
        abort_unless(GepSettingsPermissions::allows($request->user(),'terms',$action),403);
    }

    public function fetch(Request $request)
    {
        $this->authorize($request);
        return response()->json(GepTerm::query()->when(SettingsQuery::search($request),fn($q,$s)=>$q->where(fn($q)=>$q->where('term_name','like','%'.$s.'%')->orWhere('short_name','like','%'.$s.'%')))
            ->orderBy('term_name')->orderBy('id')->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request,?GepTerm $term=null)
    {
        $this->authorize($request,$term?'edit':'create');
        $request->merge(['term_name'=>trim((string)$request->input('term_name')),'short_name'=>trim((string)$request->input('short_name'))]);
        $data=$request->validate([
            'term_name'=>['required','string','max:100',Rule::unique('tb_gep_term','term_name')->ignore($term?->id)],
            'short_name'=>['required','string','max:30',Rule::unique('tb_gep_term','short_name')->ignore($term?->id)],
        ]);
        // Status changes have a separate permission and endpoint.
        if($term)$term->update($data);else $term=GepTerm::create([...$data,'status'=>true]);
        return response()->json(['message'=>$request->isMethod('put')?'GEP term updated.':'GEP term created.','data'=>$term],$request->isMethod('put')?200:201);
    }

    public function status(Request $request,GepTerm $term)
    {
        $this->authorize($request,'status');
        $data=$request->validate(['status'=>['required','boolean']]);
        $term->update($data);
        return response()->json(['message'=>'GEP term status updated.','data'=>$term]);
    }

    public function delete(Request $request,GepTerm $term)
    {
        $this->authorize($request,'delete');
        try{$term->delete();}catch(\Illuminate\Database\QueryException $e){
            if(in_array($e->getCode(),['23000','23503'],true))throw ValidationException::withMessages(['term_name'=>'This GEP term is linked to data and cannot be deleted.']);
            throw $e;
        }
        return response()->json(['message'=>'GEP term deleted.']);
    }
}
