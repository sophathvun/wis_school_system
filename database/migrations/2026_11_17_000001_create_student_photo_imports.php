<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_photo_batches', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignId('user_id')->constrained('users');
            $t->boolean('replace_existing')->default(false); $t->string('status')->default('preview');
            $t->timestamp('expires_at'); $t->timestamps();
        });
        Schema::create('student_photo_rows', function (Blueprint $t) {
            $t->id(); $t->foreignUuid('batch_id')->constrained('student_photo_batches')->cascadeOnDelete();
            $t->string('filename'); $t->string('student_code')->nullable();
            $t->unsignedBigInteger('student_id')->nullable()->index();
            $t->string('student_name')->nullable(); $t->string('source_path')->nullable();
            $t->string('before_path')->nullable(); $t->string('after_path')->nullable();
            $t->string('status'); $t->string('reason')->nullable(); $t->timestamps();
        });
        DB::table('access_permissions')->insertOrIgnore([
            'code'=>'students.enrollment.bulk-photos','module'=>'students.enrollment','action'=>'bulk-photos',
            'name'=>'Bulk Upload Student Photos (Own Campus; Central: All Campuses)',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
    public function down(): void
    {
        Schema::dropIfExists('student_photo_rows'); Schema::dropIfExists('student_photo_batches');
        // Leave the permission catalog entry intact to preserve existing assignments.
    }
};
