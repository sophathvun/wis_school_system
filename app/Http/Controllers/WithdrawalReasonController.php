<?php

namespace App\Http\Controllers;

use App\Models\WithdrawalReason;
use App\Support\SettingsQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WithdrawalReasonController
{
    public function index(Request $request)
    {
        return view('withdrawal-reasons', ['reasons' => WithdrawalReason::when(SettingsQuery::search($request), fn ($q, $term) => $q->where('name_en', 'like', "%{$term}%")->orWhere('name_kh', 'like', "%{$term}%"))->orderBy('sort_order')->orderBy('name_en')->paginate(SettingsQuery::perPage($request))->withQueryString()]);
    }

    public function save(Request $request)
    {
        $id = $request->integer('reason_id') ?: null;
        $data = $request->validate([
            'reason_key' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('tb_withdrawal_reason', 'reason_key')->ignore($id)],
            'name_en' => ['required', 'string', 'max:255'], 'name_kh' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'], 'status' => ['required', 'boolean'],
        ]);
        $reason = $id ? WithdrawalReason::findOrFail($id) : new WithdrawalReason();
        $reason->fill($data)->save();
        return back()->with('success', $id ? 'Withdrawal reason updated successfully.' : 'Withdrawal reason created successfully.');
    }

    public function delete(WithdrawalReason $withdrawalReason)
    {
        $withdrawalReason->update(['status' => false]);
        return back()->with('success', 'Withdrawal reason deactivated successfully. Existing forms are unchanged.');
    }
}
