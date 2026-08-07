<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tb_withdrawal_reason', function (Blueprint $table) {
            $table->id();
            $table->string('reason_key', 80)->unique();
            $table->string('name_en', 255);
            $table->string('name_kh', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
        });

        $reasons = [
            ['transfer', 'Transfer to another school'], ['study_abroad', 'Study abroad'], ['change_residence', 'Change of residence'],
            ['academic_difficulty', 'Academic difficulty'], ['behavior_difficulty', 'Behavior difficulty'], ['dislike_school', 'Dislike of the school experience'],
            ['economic', 'Economic reasons'], ['employment', 'Employment status'], ['transportation', 'Transportation difficulties'],
            ['lack_interest', 'Lack of interest or motivation'], ['physical', 'Physical illness or disability'], ['staff_relationship', 'Poor student/staff relationship'],
            ['student_relationship', 'Poor relationship with fellow students'], ['expelled', 'Expelled'],
        ];
        DB::table('tb_withdrawal_reason')->insert(array_map(fn ($reason, $index) => [
            'reason_key' => $reason[0], 'name_en' => $reason[1], 'name_kh' => null, 'sort_order' => $index + 1, 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ], $reasons, array_keys($reasons)));
    }

    public function down(): void { Schema::dropIfExists('tb_withdrawal_reason'); }
};
