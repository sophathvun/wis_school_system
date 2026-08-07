<?php

namespace App\Http\Controllers;

use App\Models\Occupation;
use App\Support\SettingsQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OccupationController
{
    public function index() { return view('occupation'); }

    public function fetchData(Request $request)
    {
        $search = SettingsQuery::search($request);
        return response()->json(Occupation::query()
            ->when($search, fn ($query) => $query->where(fn ($sub) => $sub->where('occupation_name_en', 'like', "%{$search}%")->orWhere('occupation_name_kh', 'like', "%{$search}%")))
            ->orderBy('occupation_name_en')->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request)
    {
        $id = $request->integer('occupation_id') ?: null;
        $data = $request->validate([
            'occupation_name_en' => ['required', 'string', 'max:120', Rule::unique('tb_occupation', 'occupation_name_en')->ignore($id)],
            'occupation_name_kh' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'boolean'],
        ]);
        $occupation = $id ? Occupation::findOrFail($id) : new Occupation();
        $occupation->fill($data)->save();
        return response()->json(['status' => 'success', 'message' => $id ? 'Occupation updated successfully.' : 'Occupation created successfully.', 'data' => $occupation], $id ? 200 : 201);
    }

    public function delete(Occupation $occupation)
    {
        abort_if($occupation->familyMembers()->exists(), 422, 'This occupation is already assigned and cannot be deleted. Deactivate it instead.');
        $occupation->delete();
        return response()->json(['status' => 'success', 'message' => 'Occupation deleted successfully.']);
    }
}
