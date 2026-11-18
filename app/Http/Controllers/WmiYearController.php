<?php

namespace App\Http\Controllers;

use App\Models\WmiYear;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WmiYearController
{
    private function validated(Request $request, ?WmiYear $year=null): array
    {
        return $request->validate(['year'=>['required','integer','digits:4',Rule::unique('wmi_years','year')->ignore($year?->id)]]);
    }

    public function store(Request $request)
    {
        $year=WmiYear::create($this->validated($request));
        return response()->json(['message'=>'WMI year created successfully.','id'=>$year->id],201);
    }

    public function update(Request $request, WmiYear $year)
    {
        $year->update($this->validated($request,$year));
        return response()->json(['message'=>'WMI year updated successfully.']);
    }

    public function destroy(WmiYear $year)
    {
        try {
            DB::transaction(function () use ($year) {
                $record=WmiYear::whereKey($year->id)->lockForUpdate()->firstOrFail();
                abort_if(WmiYear::linkedYearIds([$record->id])!==[],409,'This WMI year is linked to data and cannot be deleted.');
                $record->delete();
            });
        } catch (QueryException $exception) {
            if (in_array((string)$exception->getCode(),['23000','23503'],true)) {
                abort(409,'This WMI year is linked to data and cannot be deleted.');
            }
            throw $exception;
        }
        return response()->json(['message'=>'WMI year deleted successfully.']);
    }
}
