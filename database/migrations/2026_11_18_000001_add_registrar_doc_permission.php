<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('access_permissions')->insertOrIgnore([
            'code'=>'registrar-doc.view', 'module'=>'registrar-doc', 'action'=>'view',
            'name'=>'View Registrar DOC', 'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public function down(): void
    {
        // Preserve existing assignments if the deployment is rolled back.
    }
};
