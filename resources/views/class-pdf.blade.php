<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Class List</title>

    <style>
        {!! file_get_contents(resource_path('css/pages/class-pdf.css')) !!}
    </style>
</head>

<body>
    <div class="report-header">
        <div class="logo-row">
            @if ($logoSrc ?? false)
                <img src="{{ $logoSrc }}" alt="School Logo 1" class="school-logo">
            @endif
        </div>
        <div class="title-row">
            <h1 class="khmer-title">តារាងថ្នាក់រៀន</h1>
            <h2 class="english-title">Class List</h2>
        </div>
    </div>

    @if ($printMode ?? false)
        <script>
            window.addEventListener('load', function() {
                window.setTimeout(function() {
                    window.print();
                }, 150);
            });

            window.addEventListener('afterprint', function() {
                window.close();
                window.setTimeout(function() {
                    if (!window.closed) {
                        window.location.replace(@json(route('classes.index')));
                    }
                }, 100);
            });
        </script>
    @endif

    <table class="report-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>Class Name</th>
                <th>Order</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($classes as $index => $class)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $class->class_name }}</td>
                    <td>{{ $class->class_order }}</td>
                    <td>{{ $class->status ? 'Active' : 'Inactive' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="generated-date">Generated: {{ now()->format('d-M-Y h:i A') }}</p>
</body>

</html>
