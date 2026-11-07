<?php

namespace App\Http\Controllers;

use App\Services\G9CertificateNumbers;
use Illuminate\Http\Request;

class G9CertificateNumberController
{
    private function year(Request $request): int
    {
        return (int)$request->validate(['academic_year'=>'required|integer|exists:tb_academic_year,id'])['academic_year'];
    }

    public function settings(Request $request,G9CertificateNumbers $numbers)
    {
        return response()->json($numbers->configuration($request->user(),$this->year($request)))->header('Cache-Control','private, no-store');
    }

    public function save(Request $request,G9CertificateNumbers $numbers)
    {
        return response()->json($numbers->saveSettings($request->user(),$this->year($request),$request->all()))->header('Cache-Control','private, no-store');
    }

    public function preview(Request $request,G9CertificateNumbers $numbers)
    {
        return response()->json($numbers->preview($request->user(),$this->year($request)))->header('Cache-Control','private, no-store');
    }

    public function generate(Request $request,G9CertificateNumbers $numbers)
    {
        $year=$this->year($request);$input=$request->validate(['preview_token'=>'required|string|max:10000']);
        return response()->json($numbers->generate($request->user(),$year,$input['preview_token']))->header('Cache-Control','private, no-store');
    }
}
