<?php
namespace App\Http\Controllers;

use App\Models\StudentDocumentType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentDocumentTypeController
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $sortBy = $request->query('sortBy', 'sort_order');
        $sortDir = strtolower((string) $request->query('sortDir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortColumns = [
            'sort_order' => 'sort_order',
            'name_en' => 'name_en',
            'name_kh' => 'name_kh',
            'type_key' => 'type_key',
            'status' => 'status',
        ];
        $sortColumn = $sortColumns[$sortBy] ?? 'sort_order';

        $types = StudentDocumentType::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name_en', 'like', "%{$search}%")
                        ->orWhere('name_kh', 'like', "%{$search}%")
                        ->orWhere('type_key', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $sortDir)
            ->when($sortColumn !== 'sort_order', fn ($query) => $query->orderBy('sort_order'))
            ->orderBy('name_en')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return view('student-document-types', ['types' => $types]);
    }

    public function save(Request $request)
    {
        $id = $request->integer('type_id') ?: null;
        $data = $request->validate([
            'type_key' => ['required', 'alpha_dash', 'max:80', Rule::unique('tb_student_document_type', 'type_key')->ignore($id)],
            'name_en' => ['required', 'string', 'max:180'],
            'name_kh' => ['nullable', 'string', 'max:180'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'boolean'],
        ]);
        $type = $id ? StudentDocumentType::findOrFail($id) : new StudentDocumentType();
        $type->fill($data)->save();
        return back()->with('success', $id ? 'Document type updated successfully.' : 'Document type created successfully.');
    }

    public function delete(StudentDocumentType $studentDocumentType)
    {
        $studentDocumentType->update(['status' => false]);
        return back()->with('success', 'Document type deactivated successfully.');
    }
}
