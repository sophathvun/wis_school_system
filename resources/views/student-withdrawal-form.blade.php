<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Withdrawal Form</title>
    <style>
        :root { font-family: Arial, sans-serif; color: #111; }
        body { margin: 0; background: #f1f5f9; }
        .toolbar { max-width: 210mm; margin: 18px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { border: 0; border-radius: 6px; padding: 9px 16px; background: #206bc4; color: white; cursor: pointer; }
        .paper { box-sizing: border-box; width: 210mm; min-height: 297mm; margin: 12px auto 24px; padding: 16mm 15mm; background: white; box-shadow: 0 2px 12px #0002; }
        .header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { margin: 0 0 3px; font-size: 22px; }
        .header h2 { margin: 0; font-size: 17px; font-weight: 600; }
        .header p { margin: 7px 0 0; font-size: 13px; }
        .title { text-align: center; margin: 12px 0; font-size: 20px; font-weight: 700; text-transform: uppercase; }
        .title span { display: block; font-size: 16px; margin-top: 3px; }
        .section { border: 1px solid #555; margin-top: 10px; }
        .section-title { background: #eef2f7; border-bottom: 1px solid #555; padding: 6px 8px; font-weight: 700; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); }
        .field { min-height: 29px; padding: 6px 8px; border-right: 1px solid #aaa; border-bottom: 1px solid #aaa; }
        .field:nth-child(2n) { border-right: 0; }
        .field strong { display: inline-block; min-width: 125px; }
        .field small { display: block; color: #555; margin-top: 2px; }
        .reasons { padding: 8px 12px; columns: 2; column-gap: 22px; }
        .reason { break-inside: avoid; margin: 4px 0; }
        .reason .box { display: inline-block; width: 13px; height: 13px; border: 1px solid #222; margin-right: 5px; vertical-align: -2px; }
        .reason.selected .box { background: #111; box-shadow: inset 0 0 0 2px white; }
        .bilingual { display: block; color: #555; font-size: 12px; margin-left: 22px; }
        .value { padding: 8px 10px; min-height: 26px; white-space: pre-wrap; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 35px; }
        .signature { text-align: center; padding-top: 38px; border-top: 1px solid #333; }
        .signature small { display: block; margin-top: 6px; }
        @media print { @page { size: A4; margin: 0; } body { background: white; } .toolbar { display: none; } .paper { margin: 0; box-shadow: none; page-break-after: always; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print Form</button><button type="button" onclick="window.close()">Close</button></div>
    <main class="paper">
        <header class="header">
            <h1>STUDENT INFORMATION SYSTEM</h1>
            <h2>ប្រព័ន្ធព័ត៌មានសិស្ស</h2>
            <p>{{ $history->campus?->campus_name_en ?? 'School Campus' }}</p>
        </header>
        <div class="title">Student Withdrawal / Drop-Out Form<span>បែបបទសិស្សឈប់រៀន / ផ្ទេរសាលា</span></div>
        <section class="section">
            <div class="section-title">Student Information / ព័ត៌មានសិស្ស</div>
            <div class="grid">
                <div class="field"><strong>Student Name</strong>{{ trim(($history->student?->first_name_en ?? '') . ' ' . ($history->student?->last_name_en ?? '')) }}<small>ឈ្មោះសិស្ស៖ {{ trim(($history->student?->first_name_kh ?? '') . ' ' . ($history->student?->last_name_kh ?? '')) }}</small></div>
                <div class="field"><strong>Student ID</strong>{{ $history->student?->student_id ?: ($history->student?->student_no ?? '-') }}<small>លេខសម្គាល់សិស្ស</small></div>
                <div class="field"><strong>Gender</strong>{{ $history->student?->gender ?? '-' }}<small>ភេទ៖ {{ $history->student?->gender_kh ?? '-' }}</small></div>
                <div class="field"><strong>Grade / Class</strong>{{ $history->grade?->grade ?? '-' }} {{ $history->schoolClass?->class_name ?? '' }}<small>កម្រិត / ថ្នាក់</small></div>
                <div class="field"><strong>Group</strong>{{ $history->session?->session_name ?? $history->session?->name ?? '-' }}<small>ក្រុម</small></div>
                <div class="field"><strong>Academic Year</strong>{{ $history->academicYear?->academic_year ?? '-' }}<small>ឆ្នាំសិក្សា</small></div>
            </div>
        </section>
        <section class="section">
            <div class="section-title">Parent / Guardian Information / ព័ត៌មានមាតាបិតា ឬអាណាព្យាបាល</div>
            <div class="grid">
                @forelse($familyMembers as $member)
                    <div class="field"><strong>{{ ucfirst($member->relationship_type) }}</strong>{{ $member->name_en ?: trim(($member->first_name_en ?? '') . ' ' . ($member->last_name_en ?? '')) }}<small>{{ $member->name_kh ?: trim(($member->first_name_kh ?? '') . ' ' . ($member->last_name_kh ?? '')) }} · {{ $member->phone ?? '-' }}</small></div>
                @empty
                    <div class="field" style="grid-column:1/-1"><strong>Name / Phone</strong>-</div>
                @endforelse
            </div>
        </section>
        <section class="section">
            <div class="section-title">Reason(s) for Withdrawal / មូលហេតុនៃការឈប់រៀន</div>
            <div class="reasons">
                @foreach($reasons as $reason)
                    <div class="reason {{ in_array($reason['key'], $history->reasons ?? [], true) ? 'selected' : '' }}"><span class="box"></span>{{ $reason['en'] }}<span class="bilingual">{{ $reason['kh'] }}</span></div>
                @endforeach
                @if($history->other_reason_en || $history->other_reason_kh)
                    <div class="reason selected"><span class="box"></span>Other: {{ $history->other_reason_en }}<span class="bilingual">{{ $history->other_reason_kh }}</span></div>
                @else
                    <div class="reason"><span class="box"></span>Other / ផ្សេងទៀត</div>
                @endif
            </div>
            <div class="field"><strong>Selected reasons</strong>{{ $history->reason }}<small>{{ $history->reason_kh }}</small></div>
        </section>
        <section class="section">
            <div class="section-title">Withdrawal Details / ព័ត៌មានលម្អិត</div>
            <div class="grid">
                <div class="field"><strong>New School</strong>{{ $history->new_school ?: '-' }}<small>សាលាថ្មី</small></div>
                <div class="field"><strong>School Address</strong>{{ $history->new_school_address ?: '-' }}<small>អាសយដ្ឋានសាលា</small></div>
                <div class="field"><strong>Date</strong>{{ optional($history->effective_on)->format('d-m-Y') }}<small>កាលបរិច្ឆេទ</small></div>
                <div class="field"><strong>Status</strong>{{ $history->dropout_type === 'dropped_out' ? 'Has dropped out' : 'Will officially leave' }}<small>ស្ថានភាព</small></div>
            </div>
            <div class="field"><strong>Additional Comments</strong>{{ $history->additional_comments ?: ($history->notes ?: '-') }}<small>មតិយោបល់បន្ថែម</small></div>
        </section>
        <div class="signatures">
            <div class="signature">Parent / Guardian Signature<small>ហត្ថលេខាមាតាបិតា ឬអាណាព្យាបាល</small><small>Date: __________________</small></div>
            <div class="signature">SP / VSP Signature<small>ហត្ថលេខា SP / VSP</small><small>Date: __________________</small></div>
        </div>
    </main>
</body>
</html>
