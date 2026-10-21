<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><title>Family List</title>
<style>@page{size:A4 landscape;margin:12mm}body{font-family:Arial,sans-serif;font-size:10pt}h1{text-align:center;font-size:18pt}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:7px;text-align:left}thead{display:table-header-group}tr{break-inside:avoid}th{background:#dceafa}</style>
</head><body><h1>Family List</h1><table><thead><tr><th>No.</th><th>Family Number</th><th>Mother</th><th>Father</th><th>Children</th><th>Status</th></tr></thead><tbody>
@foreach ($families as $family)
@php($mother = $family->members->firstWhere('relationship_type','mother'))
@php($father = $family->members->firstWhere('relationship_type','father'))
<tr><td>{{ $loop->iteration }}</td><td>{{ $family->family_number }}</td><td>{{ $mother?->full_name_en }}<br>{{ $mother?->phone }}</td><td>{{ $father?->full_name_en }}<br>{{ $father?->phone }}</td><td>@foreach($family->students as $student){{ $student->student_id }} — {{ $student->full_name_en }}<br>@endforeach</td><td>{{ $family->status ? 'Active' : 'Inactive' }}</td></tr>
@endforeach
</tbody></table><script>window.addEventListener('load',()=>window.print());</script></body></html>
