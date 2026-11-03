<?php

namespace App\Http\Controllers;

use App\Models\G12ExamRecord;
use App\Services\G12ResultReports;
use App\Support\{G12GradeACertificate, G12NationalExamPermissions as Access, ScholarshipCertificateLayout, ScholarshipCertificateReport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class G12GradeACertificateController
{
    private function scope(Request $request, string $action='reports'): array
    {
        $scope=$request->validate(['academic_year'=>['required','string','regex:/^\d{4}-\d{4}$/'],'campus_id'=>['nullable','integer']]);
        $campus=(int)($scope['campus_id']??0);
        abort_unless(Access::allows($request->user(),$action,$campus?:null),403);
        return ['academic_year'=>$scope['academic_year'],'campus_id'=>$campus];
    }

    private function layout(array $scope): array
    {
        $saved=DB::table('tb_g12_grade_a_layout')->where(['academic_year'=>$scope['academic_year'],'campus_id'=>0])->value('layout');
        if(!$saved&&$scope['campus_id'])$saved=DB::table('tb_g12_grade_a_layout')->where($scope)->value('layout');
        return $saved?array_replace_recursive(G12GradeACertificate::defaults(),json_decode($saved,true)):G12GradeACertificate::defaults();
    }

    private function students(array $scope, Request $request, string $action='reports'): array
    {
        $records=G12ExamRecord::with(['campus','center','certificates'])->where('academic_year',$scope['academic_year'])
            ->whereIn('campus_id',Access::campusIds($request->user(),$action))
            ->when($scope['campus_id'],fn($query,$campus)=>$query->where('campus_id',$campus))->get();
        $report=app(G12ResultReports::class)->prepare($records,'grade-a');
        return array_map(fn($row)=>['id'=>$row['id'],'name'=>$row['name_en'],'name_kh'=>$row['name_kh'],'values'=>G12GradeACertificate::values($row)],$report['rows']);
    }

    public function show(Request $request)
    {
        $scope=$this->scope($request);
        return response()->json(['layout'=>$this->layout($scope),'defaults'=>G12GradeACertificate::defaults(),
            'students'=>$this->students($scope,$request),'fonts'=>ScholarshipCertificateLayout::fonts(),
            'frame'=>asset('images/g12-grade-a-certificate-frame.jpg'),
            'can_save'=>Access::allows($request->user(),'export',$scope['campus_id']?:null)]);
    }

    public function save(Request $request)
    {
        $scope=$this->scope($request,'export');
        $request->validate(['layout'=>['required','array']]);
        $layout=ScholarshipCertificateLayout::validate('exam',$request->input('layout'));
        $request->validate(['layout.issue_date'=>['required','date_format:Y-m-d']]);
        $layout['issue_date']=$request->input('layout.issue_date');
        $scope['campus_id']=0;
        DB::table('tb_g12_grade_a_layout')->updateOrInsert($scope,['layout'=>json_encode($layout,JSON_UNESCAPED_UNICODE),'updated_by'=>$request->user()->id,'updated_at'=>now(),'created_at'=>now()]);
        return response()->json(['message'=>'Certificate layout saved for this academic year.']);
    }

    public function assignNumbers(Request $request)
    {
        $scope=$this->scope($request,'export');
        $count=G12GradeACertificate::assignNumbers($scope['academic_year'],Access::campusIds($request->user(),'export'));
        return response()->json(['message'=>$count.' certificate numbers assigned in descending score rank.']);
    }

    public function pdf(Request $request)
    {
        $scope=$this->scope($request,'export');
        $input=$request->validate(['student_ids'=>['required','array','min:1','max:500'],'student_ids.*'=>['integer','distinct'],'layout'=>['required','array'],'with_frame'=>['required','boolean']]);
        $layout=ScholarshipCertificateLayout::validate('exam',$input['layout']);
        $request->validate(['layout.issue_date'=>['required','date_format:Y-m-d']]);
        $layout['issue_date']=$request->input('layout.issue_date');
        $students=collect($this->students($scope,$request,'export'))->keyBy('id');
        $pages=[];
        foreach($input['student_ids'] as $id){
            abort_unless(isset($students[$id]),422,'Select only Grade A students in this academic year and campus.');
            $values=$students[$id]['values'];
            $values['issue_date']=\Carbon\CarbonImmutable::parse($layout['issue_date'])->format('jS \d\a\y \o\f F Y');
            $pages[]=['layout'=>$layout,'values'=>$values];
        }
        $assets=['frame'=>'data:image/jpeg;base64,'.base64_encode(file_get_contents(public_path('images/g12-grade-a-certificate-frame.jpg'))), 'branding1'=>null,'branding2'=>null,'upload'=>null];
        $pdf=app(ScholarshipCertificateReport::class)->pdf($pages,$assets,$input['with_frame']);
        return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="g12-grade-a-certificates.pdf"']);
    }
}
