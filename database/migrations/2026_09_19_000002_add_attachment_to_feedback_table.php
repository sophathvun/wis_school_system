<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            if (!Schema::hasColumn('feedback', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('message');
            }
        });

        try {
            DB::statement('ALTER TABLE feedback MODIFY message LONGTEXT NOT NULL');
        } catch (Throwable) {
            // Some database drivers do not support this syntax. Fresh installs use
            // the updated create migration, and existing records still work.
        }
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            if (Schema::hasColumn('feedback', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
        });
    }
};
