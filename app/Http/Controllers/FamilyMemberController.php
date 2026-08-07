<?php

namespace App\Http\Controllers;

use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FamilyMemberController
{
    public function index(Family $family)
    {
        return response()->json(['data' => $family->members()->orderByRaw("FIELD(relationship_type, 'mother', 'father', 'guardian')")->orderBy('first_name_en')->get()]);
    }

    public function save(Request $request, Family $family)
    {
        $id = $request->integer('family_member_id') ?: null;
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

        $member->fill(collect($validated)->except(['full_name_en', 'full_name_kh'])->all());
        $member->full_name_en = $validated['full_name_en'];
        $member->full_name_kh = $validated['full_name_kh'] ?? null;
        $member->first_name_en = $validated['full_name_en'];
        $member->last_name_en = '';
        $member->first_name_kh = $validated['full_name_kh'] ?? null;
        $member->last_name_kh = null;
        $member->name_en = $validated['full_name_en'];
        $member->name_kh = $validated['full_name_kh'] ?? null;
        $member->save();

        return response()->json(['status' => 'success', 'message' => $id ? 'Family member updated successfully.' : 'Family member created successfully.', 'data' => $member], $id ? 200 : 201);
    }

    public function delete(Family $family, FamilyMember $member)
    {
        abort_unless($member->family_id === $family->id, 404);
        $member->delete();

        return response()->json(['status' => 'success', 'message' => 'Family member deleted successfully.']);
    }
}
