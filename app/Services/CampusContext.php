<?php

namespace App\Services;

use App\Models\SchoolInfo;
use App\Models\User;
use Illuminate\Http\Request;

class CampusContext
{
    public const REQUEST_KEY = 'campus_id';

    public function resolve(Request $request, User $user): ?SchoolInfo
    {
        $requestedId = $request->integer('campus_id')
            ?: $request->session()->get('active_campus_id')
            ?: $user->active_campus_id;

        if ($requestedId && $user->canAccessCampus($requestedId)) {
            return SchoolInfo::query()->find($requestedId);
        }

        $campus = $user->accessibleCampuses()->orderBy('campus_name_en')->first();

        if ($campus) {
            $user->forceFill(['active_campus_id' => $campus->id])->saveQuietly();
        }

        return $campus;
    }

    public function set(Request $request, User $user, int $campusId): SchoolInfo
    {
        abort_unless($user->canAccessCampus($campusId), 403, 'You do not have access to this campus.');

        $campus = SchoolInfo::query()->findOrFail($campusId);
        $user->forceFill(['active_campus_id' => $campus->id])->save();
        $request->session()->put('active_campus_id', $campus->id);

        return $campus;
    }
}
