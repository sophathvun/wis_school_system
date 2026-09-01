<!doctype html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <title>បញ្ជីឈ្មោះអ្នកប្រើប្រាស់</title>
    <style>
        @font-face {
            font-family: "Khmer OS Muol Light";
            font-style: normal;
            font-weight: 300;
            font-display: block;
            src: url("data:font/truetype;charset=utf-8;base64,{{ base64_encode(file_get_contents(public_path('fonts/khmer/KhmerOSmuollight.ttf'))) }}") format("truetype");
        }

        body {
            margin: 0;
            color: #172b4d;
            font-family: Arial, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @page {
            size: A4 landscape;
            margin: 14mm 10mm 18mm;
        }

        .logo-row {
            text-align: left;
            margin-bottom: 8px;
        }

        .school-logo {
            width: 230px;
            height: auto;
        }

        .report-title {
            margin-bottom: 14px;
            text-align: center;
        }

        .khmer-title {
            margin: 0;
            color: #4f6380;
            font-family: "Khmer OS Muol Light", "Noto Sans Khmer", sans-serif;
            font-size: 22px;
            font-weight: 300;
        }

        .english-title {
            margin: 3px 0 0;
            font-size: 20px;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #9aa4b2;
            padding: 6px;
            text-align: center;
            vertical-align: middle;
            font-size: 10px;
        }

        th {
            background: #e6f1fb !important;
            font-weight: 700;
        }

        .text-start {
            text-align: left;
        }

        .report-footer {
            position: fixed;
            bottom: 4mm;
            left: 0;
            color: #52657e;
            font-size: 10px;
        }
    </style>
</head>

<body data-pdf-print-mode="{{ ($printMode ?? false) ? '1' : '0' }}">
    <div class="logo-row">
        @if ($logoSrc)
            <img src="{{ $logoSrc }}" alt="School Logo 1" class="school-logo">
        @endif
    </div>
    <div class="report-title">
        <h3 class="khmer-title">បញ្ជីឈ្មោះអ្នកប្រើប្រាស់</h3>
        <h3 class="english-title">User List</h3>
    </div>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Staff ID</th>
                <th>Staff Full Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Phone Number</th>
                <th>Position</th>
                <th>Campus Assignment</th>
                <th>Role</th>
                <th>Login</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $index => $user)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $user->staff_id ?: '-' }}</td>
                    <td class="text-start">{{ $user->name }}</td>
                    <td>{{ $user->username }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->phone ?: '-' }}</td>
                    <td>{{ $user->position?->name ?: '-' }}</td>
                    <td>{{ $user->is_global ? 'All Campuses' : ($user->campuses->pluck('campus_name_en')->filter()->join(', ') ?: '-') }}</td>
                    <td>{{ $user->roles->pluck('name')->unique()->join(', ') ?: '-' }}</td>
                    <td>{{ $user->login_identifier === 'both' ? 'Username / Email' : ucfirst((string) $user->login_identifier) }}</td>
                    <td>{{ $user->status ? 'Active' : 'Inactive' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="report-footer">{{ now('Asia/Phnom_Penh')->format('d-M-Y h:i A') }}</div>
    @vite('resources/js/pdfPrint.js')
</body>

</html>

