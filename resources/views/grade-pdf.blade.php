<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Grade List</title>

    <style>
        {!! file_get_contents(resource_path('css/pages/grade-pdf.css')) !!}
    </style>
</head>

<body>
    <div class="report-header">
        <div class="logo-row">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" alt="School Logo 1" class="school-logo">
            @endif
        </div>
        <div class="report-title">
            <h2 class="khmer-title">តារាងកម្រិតថ្នាក់</h2>
            <h2 class="english-title">Grade List</h2>
        </div>
    </div>

    @if ($printMode ?? false)
        <script>
            document.title = '';

            window.addEventListener('load', function() {
                window.setTimeout(function() {
                    window.print();
                }, 150);
            });
            window.addEventListener('afterprint', function() {
                window.close();
            });
        </script>
    @endif
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Grade</th>
                <th>Short Name</th>
                <th>Order</th>
                <th>Description</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($grades as $index => $grade)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $grade->grade }}</td>
                    <td>{{ $grade->grade_short_name }}</td>
                    <td>{{ $grade->grade_order }}</td>
                    <td>{{ $grade->description }}</td>
                    <td>{{ $grade->status ? 'Active' : 'Inactive' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="report-footer">
        {{ now()->format('d-M-Y h:i A') }}
    </div>
</body>

</html>
