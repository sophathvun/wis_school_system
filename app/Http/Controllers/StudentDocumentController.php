<?php
namespace App\Http\Controllers;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentDocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class StudentDocumentController {
 public function index(){return view('student-documents');}
 public function options(Request $request){$term=trim((string)$request->query('search'));$students=Student::where('status',1)->when($term,fn($q)=>$q->where(fn($s)=>$s->where('student_id','like',"%{$term}%")->orWhere('student_no','like',"%{$term}%")->orWhere('first_name_en','like',"%{$term}%")->orWhere('last_name_en','like',"%{$term}%")))->orderBy('first_name_en')->limit(300)->get(['id','student_id','student_no','first_name_en','last_name_en']);return response()->json(['students'=>$students,'types'=>StudentDocumentType::where('status',1)->orderBy('sort_order')->get(['id','name_en','name_kh'])]);}
 public function fetch(Student $student){return response()->json($student->documents()->with(['type','uploadedBy:id,name'])->latest('id')->get());}
 public function save(Request $request){$data=$request->validate(['student_id'=>['required','exists:tb_student,id'],'document_type_id'=>['required','exists:tb_student_document_type,id'],'title'=>['nullable','string','max:180'],'document_number'=>['nullable','string','max:100'],'issue_date'=>['nullable','date'],'expiry_date'=>['nullable','date','after_or_equal:issue_date'],'description'=>['nullable','string','max:5000'],'file'=>['required','file','mimes:pdf,jpg,jpeg,png,doc,docx','max:20480']]);$file=$request->file('file');$path=$file->store('student-documents','public');$doc=StudentDocument::create($data+['document_type'=>StudentDocumentType::find($data['document_type_id'])?->name_en,'file_path'=>$path,'original_filename'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'file_size'=>$file->getSize(),'uploaded_by'=>auth()->id(),'status'=>1]);return response()->json(['status'=>'success','message'=>'Student document uploaded successfully.','data'=>$doc->load('type')]);}
 public function download(StudentDocument $document){abort_unless($document->file_path && Storage::disk('public')->exists($document->file_path),404);return Storage::disk('public')->download($document->file_path,$document->original_filename);}
 public function delete(StudentDocument $document){if($document->file_path)Storage::disk('public')->delete($document->file_path);$document->delete();return response()->json(['status'=>'success','message'=>'Student document deleted successfully.']);}
}
