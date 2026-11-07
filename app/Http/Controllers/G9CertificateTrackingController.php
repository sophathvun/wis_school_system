<?php

namespace App\Http\Controllers;

use App\Models\{AcademicYear,G9ExamCandidate,G9ExamCertificateFile};
use App\Services\G9CertificateTracking;
use App\Support\G9NationalExamPermissions as Access;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class G9CertificateTrackingController
{
    public const FILTERS=['exam_center','campus','room','grade','student'];

    private function campuses(Request $request): array
    {
        $ids=Access::campusIds($request->user(),'certificates');abort_unless($ids,403);return $ids;
    }

    public function index(Request $request)
    {
        $ids=$this->campuses($request);
        return view('g9-national-exams.certificate-tracking',[
            'years'=>AcademicYear::whereIn('id',G9ExamCandidate::whereIn('campus_id',$ids)->select('academic_year_id'))->orderByDesc('academic_year')->get(),
            'canNumberSettings'=>Access::allows($request->user(),'certificates-number-manage'),
        ]);
    }

    private function query(Request $request,bool $filtered=true)
    {
        $input=$request->validate([
            'academic_year'=>'required|integer|exists:tb_academic_year,id','exam_center'=>'nullable|string|max:255','campus'=>'nullable|integer',
            'room'=>'nullable|string|max:255','grade'=>'nullable|string|max:255','student'=>'nullable|integer',
            'status'=>['nullable',Rule::in(['awaiting','printed_signed','received','not_issued','review'])],
            'search'=>'nullable|string|max:255','per_page'=>['nullable',Rule::in(['15','25','50','100'])],'page'=>'nullable|integer|min:1',
        ]);
        $query=G9ExamCandidate::whereIn('campus_id',$this->campuses($request))->where('academic_year_id',$input['academic_year']);
        if($filtered){
            foreach(self::FILTERS as $filter)$this->filter($query,$filter,$input[$filter]??null);
            if($search=trim($input['search']??''))$query->where(fn($q)=>$q->where('student_code','like','%'.$search.'%')->orWhere('name_kh','like','%'.$search.'%')->orWhere('name_en','like','%'.$search.'%'));
        }
        return $query;
    }

    private function filter($query,string $filter,mixed $value): void
    {
        if($value!==null&&$value!=='')$query->where(['campus'=>'campus_id','student'=>'id','room'=>'data->room_number'][$filter]??'data->'.$filter,$value);
    }

    public function options(Request $request)
    {
        $query=$this->query($request,false);$choices=[];
        foreach(self::FILTERS as $filter){
            if($filter==='campus')$choices[$filter]=(clone $query)->with('campus')->get(['campus_id'])->unique('campus_id')->map(fn($r)=>['id'=>$r->campus_id,'label'=>$r->campus?->campus_name_en??''])->sortBy('label',SORT_NATURAL)->values();
            elseif($filter==='student')$choices[$filter]=(clone $query)->orderBy('name_kh')->orderBy('name_en')->get(['id','student_code','name_kh','name_en'])->map(fn($r)=>['id'=>$r->id,'label'=>implode(' | ',array_filter([$r->student_code,$r->name_kh,$r->name_en]))]);
            else $choices[$filter]=(clone $query)->select('data->'.($filter==='room'?'room_number':$filter).' as choice')->distinct()->pluck('choice')->filter(fn($v)=>$v!==null&&$v!=='')->sort(SORT_NATURAL)->values();
            $this->filter($query,$filter,$request->input($filter));
        }
        return response()->json($choices)->header('Cache-Control','private, no-store');
    }

    public function data(Request $request,G9CertificateTracking $tracking)
    {
        $records=$this->query($request)->with(['campus','academicYear','certificateTracking','certificateFiles','certificateNumber'])->get()
            ->sort(fn($a,$b)=>strnatcasecmp($a->data['exam_center']??'',$b->data['exam_center']??'')?:strnatcasecmp($a->data['room_number']??'',$b->data['room_number']??'')?:strnatcasecmp($a->data['seat_number']??'',$b->data['seat_number']??'')?:($a->id<=>$b->id))
            ->map(fn($r)=>$tracking->summary($r,$request->user()))->filter(fn($r)=>!$request->filled('status')||match($request->input('status')){
                'not_issued'=>!$r['eligible'],'review'=>$r['tracking']['needs_review'],default=>$r['eligible']&&$r['tracking']['status']===$request->input('status'),
            })->values();
        $eligible=$records->where('eligible',true);
        $summary=['total'=>$records->count(),'passed'=>$eligible->count(),'failed'=>$records->where('outcome','failed')->count(),
            'printed_signed'=>$eligible->whereIn('tracking.status',['printed_signed','received'])->count(),
            'received'=>$eligible->where('tracking.status','received')->count(),'awaiting'=>$eligible->where('tracking.status','awaiting')->count(),'not_issued'=>$records->where('eligible',false)->count()];
        $size=(int)($request->input('per_page')?:25);$page=max(1,min($request->integer('page',1),(int)ceil(max(1,$records->count())/$size)));
        return response()->json(['summary'=>$summary,'records'=>(new LengthAwarePaginator($records->forPage($page,$size)->values(),$records->count(),$size,$page))->toArray()])->header('Cache-Control','private, no-store');
    }

    public function show(Request $request,G9ExamCandidate $candidate,G9CertificateTracking $tracking)
    {
        $tracking->authorize($request->user(),'certificates',$candidate->campus_id);
        return response()->json($tracking->summary($candidate->load(['campus','academicYear','certificateTracking','certificateFiles','certificateNumber']),$request->user(),true))->header('Cache-Control','private, no-store');
    }

    public function save(Request $request,G9ExamCandidate $candidate,G9CertificateTracking $tracking)
    {
        $tracking->authorize($request->user(),'certificates-manage',$candidate->campus_id);
        $request->validate(['provisional_file'=>'nullable|file|max:20480','receipt_file'=>'nullable|file|max:20480']);
        $tracking->save($request->user(),$candidate,$request->except(['provisional_file','receipt_file']),[
            'provisional'=>$request->file('provisional_file'),'receipt'=>$request->file('receipt_file'),
        ]);
        return response()->json(['message'=>'G9 certificate documents and tracking saved.'])->header('Cache-Control','private, no-store');
    }

    public function file(Request $request,G9ExamCertificateFile $file,G9CertificateTracking $tracking)
    {
        $candidate=$file->candidate;abort_unless($candidate,404);
        $tracking->authorize($request->user(),'certificates-documents-view',$candidate->campus_id);
        $path=$request->boolean('thumbnail')&&$file->thumbnail_path?$file->thumbnail_path:$file->path;
        abort_unless(Storage::disk('local')->exists($path),404);
        if($request->boolean('jpg')){
            abort_unless(str_starts_with($file->mime,'image/'),422);
            $jpeg=app(\App\Services\G12CertificateImage::class)->jpeg(Storage::disk('local')->get($file->path));
            return response($jpeg)->header('Content-Type','image/jpeg')->header('Content-Disposition','attachment; filename="g9-certificate-'.$file->id.'.jpg"')->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
        }
        $name='g9-'.$file->kind.'-'.$file->id.'.'.($file->mime==='application/pdf'?'pdf':'webp');
        return Storage::disk('local')->response($path,$name,['Content-Type'=>$file->mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'],$request->boolean('download')?'attachment':'inline');
    }
}
