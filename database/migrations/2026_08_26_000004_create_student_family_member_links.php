<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tb_student_family_member')) {
            Schema::create('tb_student_family_member', function (Blueprint $table) {
                $table->foreignId('student_id')->constrained('tb_student')->cascadeOnDelete();
                $table->foreignId('family_member_id')->constrained('tb_family_member')->cascadeOnDelete();
                $table->string('relationship_type', 40);
                $table->boolean('is_primary_contact')->default(false);
                $table->timestamps();
                $table->primary(['student_id', 'family_member_id']);
                $table->index(['student_id', 'relationship_type']);
            });
        }

        DB::table('tb_student_family_member')
            ->insertUsing(
                ['student_id', 'family_member_id', 'relationship_type', 'is_primary_contact', 'created_at', 'updated_at'],
                DB::table('tb_family_student as source')
                    ->join('tb_family_member as member', 'member.family_id', '=', 'source.family_id')
                    ->select('source.student_id', 'member.id', 'member.relationship_type', 'member.is_primary_contact', DB::raw('CURRENT_TIMESTAMP'), DB::raw('CURRENT_TIMESTAMP'))
                    ->whereIn('member.relationship_type', ['mother', 'father', 'guardian'])
            );
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_student_family_member');
    }
};
