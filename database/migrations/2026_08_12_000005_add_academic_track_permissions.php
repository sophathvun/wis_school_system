<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'academic-tracks.view' => ['View Academic Tracks', 'view'],
            'academic-tracks.create' => ['Create Academic Tracks', 'create'],
            'academic-tracks.update' => ['Update Academic Tracks', 'update'],
            'academic-tracks.delete' => ['Delete Academic Tracks', 'delete'],
            'academic-tracks.status' => ['Activate / Deactivate Academic Tracks', 'status'],
        ] as $code => [$name, $action]) {
            Permission::firstOrCreate(['code' => $code], ['name' => $name, 'module' => 'academic-tracks', 'action' => $action]);
        }

        $superAdmin = Role::where('code', 'super-admin')->orWhere('name', 'Super Administrator')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->syncWithoutDetaching(Permission::pluck('id')->all());
        }
    }

    public function down(): void
    {
        Permission::where('module', 'academic-tracks')->delete();
    }
};
