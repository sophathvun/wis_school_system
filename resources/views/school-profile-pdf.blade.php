ï»¿<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>School Profile List</title>
    <style>
        {!! file_get_contents(resource_path('css/pages/school-profile-pdf.css')) !!}
    </style>
</head>

<body data-pdf-print-mode="{{ ($printMode ?? false) ? '1' : '0' }}">
    <div class="report-header">
        <div class="logo-row">
            @if ($logoDataUri ?? false)
                <img src="{{ $logoDataUri }}" alt="School Logo 1" class="school-logo">
            @endif
        </div>
        <div class="title-row">
            <h1 class="khmer-title">តារាងព័ត៌មានសាលា</h1>
            <h2 class="english-title">School Profile List</h2>
        </div>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Logo</th>
                <th>School Name</th>
                <th>Campus</th>
                <th>Phone Number</th>
                <th>Address</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($schools as $index => $school)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        @if ($school->logo_path)
                            <img src="{{ asset('storage/' . $school->logo_path) }}" alt="Logo" class="table-logo">
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        <div class="khmer-cell">{{ $school->school_name_kh }}</div>
                        <div>{{ $school->school_name_en }}</div>
                    </td>
                    <td>
                        <div class="khmer-cell">{{ $school->campus_name_kh }}</div>
                        <div>{{ $school->campus_name_en }}</div>
                    </td>
                    <td>{{ $school->phone }}</td>
                    <td class="text-left">{{ $school->address }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="generated-date">Generated: {{ now()->format('d-M-Y h:i A') }}</p>
    @vite('resources/js/pdfPrint.js')
</body>

</html>

