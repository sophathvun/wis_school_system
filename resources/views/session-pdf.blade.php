<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Sessions</title>

    <style>
        {!! file_get_contents(resource_path('css/pages/session-pdf.css')) !!}
    </style>
</head>

<body>
    <h2>Sessions</h2>
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Session Name</th>
                <th>Short Name</th>
                <th>Order</th>
                <th>Description</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sessions as $index => $session)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $session->session_name }}</td>
                    <td>{{ $session->session_short_name }}</td>
                    <td>{{ $session->session_order }}</td>
                    <td>{{ $session->description }}</td>
                    <td>{{ $session->status ? 'Active' : 'Inactive' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
