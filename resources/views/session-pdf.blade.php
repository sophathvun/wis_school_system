<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Sessions</title>

    <style>
        @font-face {
            font-family: "Khmer OS Muol Light";
            font-style: normal;
            font-weight: 300;
            font-display: block;
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSmuollight.ttf'))) }}") format("truetype");
        }

        @font-face {
            font-family: "Khmer OS Siemreap";
            font-style: normal;
            font-weight: 400;
            font-display: block;
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSsiemreap.ttf'))) }}") format("truetype");
        }

        {!! file_get_contents(resource_path('css/pages/session-pdf.css')) !!}
    </style>
</head>

<body>
    <div class="report-title">
        <h2 class="khmer-title">តារាងវេនសិក្សា</h2>
        <h2 class="english-title">Sessions</h2>
    </div>
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
