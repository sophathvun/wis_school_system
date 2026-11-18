<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WmiManagementController
{
    public const SECTIONS = [
        'preliminary-round'=>['label'=>'Preliminary Round','icon'=>'ti-flag','tabs'=>[
            'pre-registration'=>['Pre Registration','ti-user-plus','Prepare student registrations for the preliminary round.'],
            'candidates'=>['List of Candidates','ti-users','Review the candidates registered for the preliminary round.'],
            'exam-card'=>['Exam Card','ti-id-badge','Prepare candidate examination cards.'],
            'scoring'=>['Scoring','ti-pencil-check','Manage preliminary examination scores.'],
            'result'=>['Result','ti-chart-bar','Review preliminary round results.'],
            'certificates'=>['Certificates','ti-certificate','Prepare certificates for the preliminary round.'],
        ]],
        'final-round'=>['label'=>'Final Round','icon'=>'ti-trophy','tabs'=>[]],
        'settings'=>['label'=>'Settings','icon'=>'ti-settings','tabs'=>[
            'academic-year'=>['WMI Year','ti-calendar','Manage the years used for WMI examinations.'],
            'exam-centers'=>['Assign Exam Center','ti-building','Organize examination centers for WMI candidates.'],
            'schools'=>['Assign Schools','ti-school','Organize schools participating in the WMI examination.'],
            'room-seats'=>['Assign Rooms & Seats','ti-armchair','Allocate rooms and seats to registered candidates.'],
            'date-time'=>['Assign Date/Time','ti-clock','Plan examination dates and times.'],
            'awarding-ceremony'=>['Awarding Ceremony','ti-award','Plan the WMI awarding ceremony.'],
        ]],
        'import-export'=>['label'=>'Import/Export Data','icon'=>'ti-file-import','tabs'=>[]],
    ];

    public function index()
    {
        foreach(self::SECTIONS as $section=>$config)if(\App\Support\WmiPermissions::current($section))return redirect()->route('wmi-management.section',$section);
        abort(403);
    }

    public function section(Request $request, string $section)
    {
        abort_unless(isset(self::SECTIONS[$section]),404);
        $config=self::SECTIONS[$section];
        $requested=$request->query('tab');
        abort_if($requested!==null && (!is_string($requested) || !isset($config['tabs'][$requested])),404);
        abort_unless(\App\Support\WmiPermissions::current($section),403);
        $hadTabs=(bool)$config['tabs'];
        $config['tabs']=array_filter($config['tabs'],fn($key)=>\App\Support\WmiPermissions::current($section.'.'.$key),ARRAY_FILTER_USE_KEY);
        $tab=$requested??array_key_first($config['tabs']);
        abort_if(($hadTabs&&$tab===null)||($tab!==null&&!isset($config['tabs'][$tab])),403);
        $years=null; $linkedYearIds=[];
        if ($section==='settings' && $tab==='academic-year') {
            $years=\App\Models\WmiYear::orderByDesc('year')->paginate(25)->withQueryString();
            $linkedYearIds=\App\Models\WmiYear::linkedYearIds($years->modelKeys());
        }
        $examCenters=null; $centerYears=collect(); $linkedCenterIds=[];
        if ($section==='settings' && $tab==='exam-centers') {
            $request->validate(['wmi_year_id'=>['nullable','integer','exists:wmi_years,id'],'per_page'=>['nullable','in:all,15,50,75,100']]);
            $pageSize=(string)($request->query('per_page')??'15');
            $centerYears=\App\Models\WmiYear::orderByDesc('year')->get();
            $centerQuery=\App\Models\WmiExamCenter::with('wmiYear')
                ->when($request->filled('wmi_year_id'),fn($q)=>$q->where('wmi_year_id',$request->integer('wmi_year_id')))
                ->orderByDesc(\App\Models\WmiYear::select('year')->whereColumn('wmi_years.id','wmi_exam_centers.wmi_year_id'))
                ->orderBy('name')->orderBy('id');
            $examCenters=$centerQuery->paginate($pageSize==='all'?max(1,(clone $centerQuery)->count()):(int)$pageSize,['*'],'page',$pageSize==='all'?1:null)->withQueryString();
            $linkedCenterIds=\App\Models\WmiExamCenter::linkedIds($examCenters->modelKeys());
        }
        $schools=null; $schoolProvinces=collect(); $assignedSchoolProvinces=collect(); $linkedSchoolIds=[];
        if ($section==='settings' && $tab==='schools') {
            $request->validate(['province_id'=>['nullable','integer','exists:tb_province,id'],'per_page'=>['nullable','in:all,15,50,75,100']]);
            $pageSize=(string)($request->query('per_page')??'15');
            $schoolProvinces=\App\Models\Province::orderBy('province_name_en')->get();
            $assignedSchoolProvinces=\App\Models\Province::whereIn('id',\App\Models\WmiSchool::select('province_id')->whereNotNull('province_id'))->orderBy('province_name_en')->get();
            $query=\App\Models\WmiSchool::with('province')->when($request->filled('province_id'),fn($q)=>$q->where('province_id',$request->integer('province_id')))->orderBy('name_en')->orderBy('id');
            $schools=$query->paginate($pageSize==='all'?max(1,(clone $query)->count()):(int)$pageSize,['*'],'page',$pageSize==='all'?1:null)->withQueryString();
            $linkedSchoolIds=\App\Models\WmiSchool::linkedIds($schools->modelKeys());
        }
        $registrationData=$section==='preliminary-round' && in_array($tab,['pre-registration','candidates'],true)?app(WmiRegistrationController::class)->pageData($request):[];
        if($section==='import-export'||($section==='settings'&&$tab==='room-seats')){
            $registrationData=['registrationYears'=>\App\Models\WmiYear::orderByDesc('year')->get(),'registrationCenters'=>\App\Models\WmiExamCenter::orderBy('name')->get()];
        }
        return view('wmi-management',array_merge($registrationData,[
            'schools'=>$schools,'schoolProvinces'=>$schoolProvinces,'assignedSchoolProvinces'=>$assignedSchoolProvinces,'linkedSchoolIds'=>$linkedSchoolIds,
            'examCenters'=>$examCenters,'centerYears'=>$centerYears,'linkedCenterIds'=>$linkedCenterIds,
            'years'=>$years,'linkedYearIds'=>$linkedYearIds,
            'section'=>$section,'config'=>$config,'tab'=>$tab,
            'current'=>$tab!==null?$config['tabs'][$tab]:['Final Round','ti-trophy','The final-round workspace is ready for its registration, examination and results workflow to be defined.'],
        ]));
    }
}
