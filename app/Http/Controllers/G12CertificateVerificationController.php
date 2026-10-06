<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class G12CertificateVerificationController
{
    public function show(string $token)
    {
        $certificate = DB::table('tb_g12_certificate as certificate')
            ->join('tb_g12_certificate_year as year', 'year.id', '=', 'certificate.certificate_year_id')
            ->join('tb_academic_year as academic_year', 'academic_year.id', '=', 'year.academic_year_id')
            ->join('tb_student as student', 'student.id', '=', 'certificate.student_id')
            ->join('tb_student_enrollment as enrollment', 'enrollment.id', '=', 'certificate.enrollment_id')
            ->join('tb_school_info as campus', 'campus.id', '=', 'enrollment.campus_id')
            ->join('tb_class as class', 'class.id', '=', 'enrollment.class_id')
            ->where('certificate.qr_token', $token)
            ->first(['certificate.certificate_number', 'student.full_name_en', 'student.full_name_kh',
                'academic_year.academic_year', 'year.given_date', 'campus.campus_name_en', 'class.class_name']);
        abort_unless($certificate, 404);

        return response()->view('reports.g12-certificate-verification', compact('certificate'))
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
