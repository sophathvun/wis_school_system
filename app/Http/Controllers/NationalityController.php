<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Nationality;
use App\Support\SettingsQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NationalityController
{
    public function index() { return view('nationality'); }

    public function fetchData(Request $request)
    {
        $search = SettingsQuery::search($request);
        return response()->json(Nationality::with('country')->when($search, fn ($query) => $query->where(fn ($sub) => $sub->where('nationality_name_en', 'like', "%{$search}%")->orWhere('nationality_name_kh', 'like', "%{$search}%")))->orderBy('nationality_name_en')->paginate(SettingsQuery::perPage($request)));
    }

    public function options()
    {
        return response()->json(['countries' => Country::where('status', 1)->orderBy('country_name_en')->get(['id', 'country_name_en', 'country_name_kh', 'flag_path'])]);
    }

    public function save(Request $request)
    {
        $id = $request->integer('nationality_id') ?: null;
        $data = $request->validate([
            'country_id' => ['nullable', 'exists:tb_country,id'],
            'nationality_name_en' => ['required', 'string', 'max:100', Rule::unique('tb_nationality', 'nationality_name_en')->ignore($id)],
            'nationality_name_kh' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
        ]);
        $nationality = $id ? Nationality::findOrFail($id) : new Nationality();
        $nationality->fill($data)->save();
        return response()->json(['status' => 'success', 'message' => $id ? 'Nationality updated successfully.' : 'Nationality created successfully.', 'data' => $nationality->load('country')], $id ? 200 : 201);
    }

    public function delete(Nationality $nationality)
    {
        abort_if($nationality->familyMembers()->exists(), 422, 'This nationality is already assigned. Deactivate it instead.');
        $nationality->delete();
        return response()->json(['status' => 'success', 'message' => 'Nationality deleted successfully.']);
    }
}
