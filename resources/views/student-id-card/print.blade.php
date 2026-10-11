<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student ID Cards</title>
    @vite(['resources/css/app.css', 'resources/css/pages/student-id-card-qr.css'])
</head>
<body class="student-id-print-body">
    @php
        $logoPath = $branding?->report_logo_1_path ?: $branding?->report_logo_2_path ?: $branding?->login_logo_path ?: $branding?->sidebar_logo_path;
    @endphp
    <div class="d-print-none p-3"><button type="button" class="btn btn-primary" onclick="window.print()">Print ID Cards</button></div>
    <div class="student-id-page-shell {{ $filters['print_scope'] === 'class' ? 'is-print-class' : 'is-print-student' }}">
        @if($filters['print_scope'] === 'class')
            <div class="student-id-print-sheet">
                @foreach($students as $cardStudent)
                    <div class="student-id-print-card-page">@include('student-id-card._card')</div>
                @endforeach
            </div>
        @else
            <div class="student-id-card-workspace"><div class="student-id-preview-panel"><div class="student-id-card-preview-wrap">
                @include('student-id-card._card', ['cardStudent' => $selectedStudent])
            </div></div></div>
        @endif
    </div>
    <script>
        window.addEventListener('load', async () => {
            await document.fonts.ready;
            document.querySelectorAll('[data-id-card]').forEach(card => {
                if (card.clientWidth) card.style.setProperty('--id-card-scale', card.clientWidth / (54 * 96 / 25.4));
            });
            window.print();
        });
    </script>
</body>
</html>
