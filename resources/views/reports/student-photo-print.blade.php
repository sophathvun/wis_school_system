<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Stu. Photo</title>
    @vite(['resources/css/pages/student-photo-report.css', 'resources/js/studentPhotoPrint.js'])
</head>
<body class="student-photo-document student-photo-size-{{ $filters['photo_size'] }}">
    <div class="student-photo-toolbar">
        <button type="button" data-student-photo-print disabled>Loading photos…</button>
        <button type="button" data-student-photo-close>Close</button>
        <span data-student-photo-status>Print at 100% / Actual Size on A4 portrait paper.</span>
    </div>
    @forelse($photoPages as $page)
        <section class="student-photo-sheet">
            <h1>{{ $page->first()->campus_name_en }} · {{ $page->first()->grade_label }} · Photo size: {{ $filters['photo_size'] }}cm</h1>
            <div class="student-photo-print-grid">
                @foreach($page as $student)<img class="student-photo-print-image" src="{{ $student->photo_url }}" alt="{{ $student->full_name_en }}" data-student-id="{{ $student->student_id }}" loading="eager">@endforeach
            </div>
        </section>
    @empty
        <p class="student-photo-empty">{{ $hasDataFilter ? 'No available student photos to print.' : 'Select an Academic Year first.' }}</p>
    @endforelse
</body>
</html>
