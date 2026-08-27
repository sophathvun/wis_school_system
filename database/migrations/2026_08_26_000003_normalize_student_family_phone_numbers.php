<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function normalize($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') return $value;
        if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
        if (str_starts_with($digits, '855')) return '+' . $digits;
        if (str_starts_with($digits, '0')) return '+855' . substr($digits, 1);
        return '+855' . $digits;
    }

    public function up(): void
    {
        DB::table('tb_student')->whereNotNull('home_phone')->orderBy('id')->eachById(function ($student) {
            DB::table('tb_student')->where('id', $student->id)->update(['home_phone' => $this->normalize($student->home_phone)]);
        });
        DB::table('tb_family_member')->whereNotNull('phone')->orderBy('id')->eachById(function ($member) {
            DB::table('tb_family_member')->where('id', $member->id)->update(['phone' => $this->normalize($member->phone)]);
        });
    }

    public function down(): void
    {
        // Phone values are intentionally not reverted because the original format is not recoverable.
    }
};
