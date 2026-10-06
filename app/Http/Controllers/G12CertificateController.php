<?php

namespace App\Http\Controllers;

use App\Services\G12CertificateReport;
use Illuminate\Http\Request;

class G12CertificateController
{
    public function saveG12Certificates(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('tb_academic_year', 'id')->where('period_type', 'regular')->whereNull('deleted_at')],
            'action' => ['required', 'in:save_date,assign,update_prefix,save_style,reset_style'],
        ]);
        if ($data['action'] === 'save_style') {
            $data += $request->validate(\App\Support\G12CertificateTypography::rules());
        } elseif ($data['action'] !== 'reset_style') {
            $data += $request->validate([
                'given_date'=>[$data['action'] === 'save_date' ? 'required' : 'nullable','date_format:Y-m-d'],
                'number_prefix'=>[$data['action'] === 'update_prefix' ? 'required' : 'nullable','string','max:16','regex:/^[A-Za-z0-9-]+$/'],
            ]);
        }
        $count = app(G12CertificateReport::class)->save($request, $data);
        $message = match ($data['action']) {
            'save_style' => 'Certificate fonts saved for this academic year.',
            'reset_style' => 'Certificate fonts restored to the original style for this academic year.',
            'assign' => "Assigned {$count} new certificate numbers. Given Date saved.",
            'update_prefix' => $count ? "Prefix updated on {$count} certificates. Student sequence numbers and Given Date kept unchanged." : 'Certificate Prefix saved for this academic year.',
            default => 'Given Date saved for this academic year.',
        };
        return redirect()->route('reports.index', ['type' => 'g12-certificate-wis', 'academic_year_id' => $data['academic_year_id']] + $request->only(['campus_id', 'grade_class', 'certificate_student_id', 'certificate_show_qr']))
            ->with('success', $message)->with('g12_action', $data['action']);
    }

    public function saveG12CertificateTemplate(Request $request)
    {
        $data = $request->validate(['template_data'=>['required','json','max:30000'],'template_version'=>['required','integer','min:0']]);
        $layout = json_decode($data['template_data'],true);
        $validated = \Illuminate\Support\Facades\Validator::make(is_array($layout)?$layout:[],\App\Support\G12CertificateLayout::rules())->validate();
        app(G12CertificateReport::class)->saveTemplate($request,$validated,(int)$data['template_version']);
        return redirect()->route('reports.index',['type'=>'g12-certificate-wis']+$request->only(['academic_year_id','campus_id','grade_class','certificate_student_id','certificate_show_qr']))
            ->with('success','G12 certificate template saved. It will be used for certificates in all academic years.')->with('g12_action','save_template');
    }

}
