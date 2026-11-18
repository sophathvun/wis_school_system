<?php

namespace App\Http\Middleware;

use App\Support\WmiPermissions;
use Closure;
use Illuminate\Http\Request;

class EnsureWmiPermission
{
    public function handle(Request $request,Closure $next)
    {
        $name=str_replace('wmi-management.','',(string)$request->route()?->getName());
        $suffix=match($name){
            'registrations.store'=>'preliminary-round.pre-registration.create',
            'registrations.update'=>'preliminary-round.pre-registration.update',
            'registrations.destroy'=>'preliminary-round.pre-registration.delete',
            'registrations.export'=>'import-export.export-candidates','registrations.import-ids'=>'import-export.import-ids',
            'registrations.allocate'=>'settings.room-seats.assign',
            default=>'view',
        };
        if($name==='registrations.enrollments'){
            abort_unless(WmiPermissions::current('preliminary-round.pre-registration.create')||WmiPermissions::current('preliminary-round.pre-registration.update'),403);
        }else{
            foreach(['years'=>'academic-year','centers'=>'exam-centers','schools'=>'schools'] as $prefix=>$tab){
                if(str_starts_with($name,$prefix.'.'))$suffix='settings.'.$tab.'.'.match(substr($name,strlen($prefix)+1)){'store'=>'create','update'=>'update','destroy'=>'delete'};
            }
            if($name==='section'){
                $suffix=(string)$request->route('section');
                if($request->filled('tab')&&is_string($request->query('tab')))$suffix.='.'.$request->query('tab');
            }
            abort_unless(WmiPermissions::allows($request->user(),WmiPermissions::ROOT.'.'.$suffix),403);
        }
        return $next($request);
    }
}
