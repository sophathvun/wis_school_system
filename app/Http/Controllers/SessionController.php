<?php

namespace App\Http\Controllers;

use App\Models\Session;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\SettingsQuery;

class SessionController
{
    public function index()
    {
        return view('session');
    }

    public function fetchData(Request $request)
    {
        $perPage = SettingsQuery::perPage($request);
        $search = SettingsQuery::search($request);

        $sessions = Session::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('session_name', 'like', "%{$search}%")
                        ->orWhere('session_short_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('CAST(session_order AS UNSIGNED) ASC')->orderBy('id')->paginate($perPage);

        return response()->json($sessions);
    }

    public function exportPdf()
    {
        $sessions = Session::orderBy('session_order')->orderBy('id')->get();
        return Pdf::loadView('session-pdf', compact('sessions'))->stream('sessions.pdf');
    }

    public function save(Request $request)
    {
        $sessionId = $request->input('session_id');
        $sessionNameValue = $request->input('session_name');
        $shortNameValue = $request->input('session_short_name');

        $sessionNameExists = filled($sessionNameValue) && Session::query()
            ->where('session_name', $sessionNameValue)
            ->when($sessionId, fn ($query) => $query->where('id', '!=', $sessionId))
            ->exists();

        $shortNameExists = filled($shortNameValue) && Session::query()
            ->where('session_short_name', $shortNameValue)
            ->when($sessionId, fn ($query) => $query->where('id', '!=', $sessionId))
            ->exists();

        if ($sessionNameExists || $shortNameExists) {
            if ($sessionNameExists && $shortNameExists) {
                $message = "Unable to save Session. Session Name '{$sessionNameValue}' and Short Name '{$shortNameValue}' are already existed.";
            } elseif ($sessionNameExists) {
                $message = "Unable to save Session. Session Name '{$sessionNameValue}' already existed.";
            } else {
                $message = "Unable to save Session. Short Name '{$shortNameValue}' already existed.";
            }

            return response()->json(['status' => 'error', 'message' => $message], 422);
        }

        $validated = $request->validate([
            'session_name' => ['required', 'string', 'max:20', Rule::unique('tb_session', 'session_name')->ignore($sessionId)],
            'session_short_name' => ['required', 'string', 'max:20', Rule::unique('tb_session', 'session_short_name')->ignore($sessionId)],
            'session_order' => ['nullable', 'string', 'max:3'],
            'description' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
        ]);

        $session = $sessionId ? Session::findOrFail($sessionId) : new Session();
        $session->fill($validated);
        $session->save();

        return response()->json([
            'status' => 'success',
            'message' => $sessionId ? 'Session updated successfully.' : 'Session created successfully.',
            'data' => $session,
        ], $sessionId ? 200 : 201);
    }

    public function delete($id)
    {
        $session = Session::find($id);
        if (!$session) return response()->json(['status' => 'error', 'message' => 'Session not found.'], 404);

        $session->delete();
        return response()->json(['status' => 'success', 'message' => 'Session deleted successfully.']);
    }
}
