<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'code' => 'dashboard.customize',
                'module' => 'dashboard',
                'action' => 'customize',
                'name' => 'Customize Own Dashboard',
            ],
            [
                'code' => 'dashboard.reset',
                'module' => 'dashboard',
                'action' => 'reset',
                'name' => 'Reset Own Dashboard',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['code' => $permission['code']],
                $permission
            );
        }
    }

    public function down(): void
    {
        Permission::whereIn('code', [
            'dashboard.customize',
            'dashboard.reset',
        ])->delete();
    }
};
