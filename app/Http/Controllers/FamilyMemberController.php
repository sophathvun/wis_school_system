<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\FamilyPermissions;
use App\Services\FamilyHistoryRecorder;
use App\Services\FamilyStudentLinks;
use Illuminate\Support\Facades\DB;

class FamilyMemberController
{
    public function index(Family $family)
    {
        abort_unless(FamilyPermissions::current()->allows('view'), 403);
        return response()->json(['data' => $family->members()->orderByRaw("CASE relationship_type WHEN 'mother' THEN 0 WHEN 'father' THEN 1 ELSE 2 END")->orderBy('full_name_en')->get()]);
    }

    public function save(Request $request, Family $family)
    {
        $id = $request->integer('family_member_id') ?: null;
        abort_unless(FamilyPermissions::current()->familyAction($family, $id ? 'members-update' : 'members-create'), 403);
        $member = $id ? $family->members()->findOrFail($id) : new FamilyMember(['family_id' => $family->id]);

        $validated = $request->validate([
            'full_name_en' => ['required', 'string', 'max:160'],
            'full_name_kh' => ['nullable', 'string', 'max:160'],
            'relationship_type' => ['required', Rule::in(FamilyMember::RELATIONSHIP_TYPES)],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'is_primary_contact' => ['required', 'boolean'],
            'has_pickup_authorization' => ['required', 'boolean'],
            'has_portal_access' => ['required', 'boolean'],
            'status' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($family, $member, $validated, $id) {
            Family::lockForUpdate()->findOrFail($family->id);
            $audit = app(FamilyHistoryRecorder::class);
            $before = $audit->snapshot($family);
            $member->fill(collect($validated)->except(['full_name_en', 'full_name_kh'])->all());
            $member->full_name_en = $validated['full_name_en'];
            $member->full_name_kh = $validated['full_name_kh'] ?? null;
            $member->save();
            app(FamilyStudentLinks::class)->refreshFamilyMembers($family);
            $audit->record($family, $id ? 'member_updated' : 'member_created', $before, $audit->snapshot($family));
        });

        return response()->json(['status' => 'success', 'message' => $id ? 'Family member updated successfully.' : 'Family member created successfully.', 'data' => $member], $id ? 200 : 201);
    }

    public function delete(Family $family, FamilyMember $member)
    {
        abort_unless((int) $member->family_id === (int) $family->id, 404);
        abort_unless(FamilyPermissions::current()->familyAction($family, 'members-delete'), 403);
        DB::transaction(function () use ($family, $member) {
            Family::lockForUpdate()->findOrFail($family->id);
            $audit = app(FamilyHistoryRecorder::class);
            $before = $audit->snapshot($family);
            $member->delete();
            app(FamilyStudentLinks::class)->refreshFamilyMembers($family);
            $audit->record($family, 'member_deleted', $before, $audit->snapshot($family));
        });

        return response()->json(['status' => 'success', 'message' => 'Family member deleted successfully.']);
    }
}
