<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('access_permissions')->updateOrInsert(['code' => 'photo-editor.view'], [
            'module' => 'photo-editor', 'action' => 'view',
            'name' => 'Use Photo Editor (Crop, Adjust, Background and Download)',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('access_permissions')->where('code', 'photo-editor.view')->delete();
    }
};
