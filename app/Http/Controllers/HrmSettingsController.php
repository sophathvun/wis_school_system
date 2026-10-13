<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HrmSettingsController
{
    public function index(Request $request)
    {
        abort_unless(\App\Support\HrmPermissions::allows($request->user(),'hrm-settings'),403);
        $tabs = [
            'departments' => ['label' => 'Department', 'icon' => 'ti-building-community', 'controller' => DepartmentManagementController::class],
            'positions' => ['label' => 'Position', 'icon' => 'ti-briefcase', 'controller' => PositionManagementController::class],
            'import-export' => ['label' => 'Import / Export', 'icon' => 'ti-file-import', 'controller' => StaffDataController::class],
        ];
        $tabs=array_filter($tabs,fn($tab,$key)=>$key==='import-export'?StaffDataController::allows($request,'view'):\App\Support\HrmPermissions::allows($request->user(),$key),ARRAY_FILTER_USE_BOTH);
        abort_if(empty($tabs),403);
        $activeTab = $request->query('tab', array_key_first($tabs));
        if (is_string($activeTab) && in_array($activeTab,['departments','positions','import-export'],true) && !isset($tabs[$activeTab])) abort(403);
        abort_unless(is_string($activeTab) && isset($tabs[$activeTab]), 404);

        $data = app($tabs[$activeTab]['controller'])->index($request)->getData();

        return view('hrm-settings.index', $data + [
            'settingTabs' => $tabs,
            'activeTab' => $activeTab,
        ]);
    }
}
