@php($branding = \App\Models\BrandingSetting::current())
<!doctype html>
<style>@font-face{font-family:'Tacteing';src:url('/fonts/khmer/Tacteing.ttf') format('truetype');font-weight:normal;font-style:normal}</style>
<html lang="{{ ($filters['print_format'] ?? 'internal') === 'moeys' ? 'km' : 'en' }}">
<head><meta charset="utf-8"><title>{{ $title }}</title>@vite('resources/js/khmer-calendar.js')<style>
@page{size:A4 portrait;margin:12mm}*{box-sizing:border-box}body{margin:0;color:#172b4d;font-family:Arial,sans-serif;font-size:11px}.toolbar{display:flex;gap:8px;margin-bottom:14px}.toolbar button{border:0;border-radius:4px;background:#206bc4;color:#fff;padding:8px 14px;cursor:pointer}.a4-page{min-height:270mm;page-break-after:always}.a4-page:last-child{page-break-after:auto}.report-header{position:relative;min-height:39mm;border-bottom:2px solid #206bc4;padding:0 20mm 7px;text-align:center}.report-header .logo{position:absolute;top:0;left:0;max-width:34mm;max-height:28mm;object-fit:contain}.report-header .motto{position:static;width:auto;text-align:center;font-family:"Khmer OS Muol Light","Khmer OS Muol",serif;font-size:11px;line-height:1.55;margin:0 auto 4px}.report-header .motto::after{content:"KINGDOM OF CAMBODIA\A NATION RELIGION KING";display:block;white-space:pre;font-family:Arial,sans-serif;font-size:9px;line-height:1.35;font-weight:700}.report-header h1{margin:4px 0;font-size:18px}.report-header h2{margin:2px 0;font-size:14px}.report-header p{margin:3px 0}.report-header .small{color:#52627a;font-size:9px}.report-table{width:100%;border-collapse:collapse;margin-top:10px}.report-table th,.report-table td{border:1px solid #9aa8ba;padding:5px 6px;text-align:center;vertical-align:middle}.report-table th{background:#eaf2fb;font-weight:700}.report-table .left{text-align:left}.report-table .khmer{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}.report-table .english{font-size:10px;margin-top:2px}.signature{display:flex;justify-content:space-between;margin-top:24px}.signature span{border-top:1px solid #65748b;padding-top:5px;width:160px;text-align:center}.empty{text-align:center;padding:20px}@media print{.toolbar{display:none}}
</style></head>
<body data-report-type="{{ $type }}" data-report-print-format="{{ $filters['print_format'] ?? 'internal' }}" data-report-date="{{ $filters['report_date'] ?? now()->format('Y-m-d') }}" data-report-academic-year="{{ $academicYear?->academic_year ?? '' }}" data-report-campus-kh="{{ $enrollments->first()?->campus?->campus_name_kh ?? $campus?->campus_name_kh ?? '' }}" data-report-campus-en="{{ $enrollments->first()?->campus?->campus_name_en ?? $campus?->campus_name_en ?? '' }}" data-report-campus-address="{{ $enrollments->first()?->campus?->address ?? $campus?->address ?? '' }}">
<div class="toolbar"><button type="button" data-report-action="print">Print</button><button type="button" data-report-action="close">Close</button></div>
@if($type === 'student-list')
@php($printFormat = $filters['print_format'] ?? 'internal')
@php($classGroups = $enrollments->groupBy(fn ($row) => $row->grade_id . ':' . $row->class_id))
@forelse($classGroups as $classRows)
@php($first = $classRows->first())
<section class="a4-page"><header class="report-header">@if($branding?->report_logo_2_path)<img class="logo" src="{{ asset('storage/'.$branding->report_logo_2_path) }}" alt="School Logo 2">@endif<div class="motto">áž–áŸ’ážšáŸ‡ážšáž¶áž‡áž¶ážŽáž¶áž…áž€áŸ’ážšáž€áž˜áŸ’áž–áž»áž‡áž¶<br>áž‡áž¶ážáž· ážŸáž¶ážŸáž“áž¶ áž–áŸ’ážšáŸ‡áž˜áž áž¶áž€áŸ’ážŸážáŸ’ážš</div><h2>{{ $first->campus?->school_name_kh ?: $first->campus?->campus_name_kh ?: '' }}</h2>@if($printFormat === 'moeys')<h1 class="khmer">áž”áž‰áŸ’áž‡áž¸ážˆáŸ’áž˜áŸ„áŸ‡ážŸáž·ážŸáŸ’ážŸ</h1><p class="khmer">áž†áŸ’áž“áž¶áŸ†ážŸáž·áž€áŸ’ážŸáž¶ {{ $academicYear?->academic_year ?? '' }} | ážáŸ’áž“áž¶áž€áŸ‹ {{ $first->grade?->grade }}{{ $first->schoolClass?->class_name }}</p>@else<h1>Internal Student List</h1><p>{{ $academicYear?->academic_year ?? 'All Academic Years' }} | {{ $first->campus?->campus_name_en }} | Class {{ $first->grade?->grade }}{{ $first->schoolClass?->class_name }}</p>@endif<p class="small">{{ $first->campus?->campus_name_en }} Â· {{ now('Asia/Phnom_Penh')->format('d M Y H:i') }}</p></header>
<table class="report-table"><thead><tr><th>{{ $printFormat === 'moeys' ? 'áž›.ážš' : 'No.' }}</th><th>{{ $printFormat === 'moeys' ? 'áž¢ážáŸ’ážáž›áŸážážŸáž·ážŸáŸ’ážŸ' : 'Student ID' }}</th><th class="left">{{ $printFormat === 'moeys' ? 'áž‚áŸ„ážáŸ’ážáž“áž¶áž˜ áž“áž·áž„áž“áž¶áž˜' : 'Student Name' }}</th><th>{{ $printFormat === 'moeys' ? 'áž—áŸáž‘' : 'Gender' }}</th><th>{{ $printFormat === 'moeys' ? 'ážáŸ’áž“áž¶áž€áŸ‹' : 'Class' }}</th><th>{{ $printFormat === 'moeys' ? 'áž€áŸ’ážšáž»áž˜' : 'Group' }}</th></tr></thead><tbody>@foreach($classRows as $index => $row)<tr><td>{{ $index + 1 }}</td><td>{{ $row->student?->student_id ?? '-' }}</td><td class="left"><div class="khmer">{{ $row->student?->full_name_kh ?: '-' }}</div>@if($printFormat !== 'moeys')<div class="english">{{ $row->student?->full_name_en ?: '-' }}</div>@endif</td><td>{{ strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M' }}</td><td>{{ ($row->grade?->grade ?? '') . ($row->schoolClass?->class_name ?? '') }}</td><td>{{ $row->session?->session_short_name ?: '-' }}</td></tr>@endforeach</tbody></table><div class="signature"><span>{{ $printFormat === 'moeys' ? 'áž‚áŸ’ážšáž¼áž”áž“áŸ’áž‘áž»áž€ážáŸ’áž“áž¶áž€áŸ‹' : 'Class Teacher' }}</span><span>{{ $printFormat === 'moeys' ? 'ážáŸ’ážšáž½ážáž–áž·áž“áž·ážáŸ’áž™' : 'Checked By' }}</span><span>{{ $printFormat === 'moeys' ? 'áž€áž¶áž›áž”ážšáž·áž…áŸ’áž†áŸáž‘' : 'Date' }}</span></div></section>
@empty
<section class="a4-page"><header class="report-header"><div class="motto">áž–áŸ’ážšáŸ‡ážšáž¶áž‡áž¶ážŽáž¶áž…áž€áŸ’ážšáž€áž˜áŸ’áž–áž»áž‡áž¶<br>áž‡áž¶ážáž· ážŸáž¶ážŸáž“áž¶ áž–áŸ’ážšáŸ‡áž˜áž áž¶áž€áŸ’ážŸážáŸ’ážš</div><h1>{{ $printFormat === 'moeys' ? 'áž”áž‰áŸ’áž‡áž¸ážˆáŸ’áž˜áŸ„áŸ‡ážŸáž·ážŸáŸ’ážŸ' : 'Internal Student List' }}</h1></header><div class="empty">No students found.</div></section>
@endforelse
@elseif($type === 'student-statistics')
<header class="report-header">@if($branding?->report_logo_2_path)<img class="logo" src="{{ asset('storage/'.$branding->report_logo_2_path) }}" alt="School Logo 2">@endif<div class="motto">áž–áŸ’ážšáŸ‡ážšáž¶áž‡áž¶ážŽáž¶áž…áž€áŸ’ážšáž€áž˜áŸ’áž–áž»áž‡áž¶<br>áž‡áž¶ážáž· ážŸáž¶ážŸáž“áž¶ áž–áŸ’ážšáŸ‡áž˜áž áž¶áž€áŸ’ážŸážáŸ’ážš</div><h1>{{ $title }}</h1><p>{{ $academicYear?->academic_year ?? 'All Academic Years' }}</p></header><table class="report-table"><thead><tr><th class="left">Campus</th>@foreach($statistics['columns'] as $column)<th>{{ $column }}</th>@endforeach<th>Total</th></tr></thead><tbody>@forelse($statistics['rows'] as $row)<tr><th class="left">{{ $row['campus'] }}</th>@foreach($row['cells'] as $cell)<td>{{ $cell }}</td>@endforeach<td>{{ $row['total'] }}</td></tr>@empty<tr><td colspan="{{ count($statistics['columns']) + 2 }}">No students found.</td></tr>@endforelse</tbody><tfoot><tr><th>Grand Total</th>@foreach($statistics['columnTotals'] as $total)<td>{{ $total }}</td>@endforeach<td>{{ $statistics['grandTotal'] }}</td></tr></tfoot></table>
@else
@php($days = $type === 'attendance-list' ? cal_days_in_month(CAL_GREGORIAN,(int)substr($filters['month'],5,2),(int)substr($filters['month'],0,4)) : 0)
<header class="report-header">@if($branding?->report_logo_2_path)<img class="logo" src="{{ asset('storage/'.$branding->report_logo_2_path) }}" alt="School Logo 2">@endif<div class="motto">áž–áŸ’ážšáŸ‡ážšáž¶áž‡áž¶ážŽáž¶áž…áž€áŸ’ážšáž€áž˜áŸ’áž–áž»áž‡áž¶<br>áž‡áž¶ážáž· ážŸáž¶ážŸáž“áž¶ áž–áŸ’ážšáŸ‡áž˜áž áž¶áž€áŸ’ážŸážáŸ’ážš</div><h1>{{ $title }}</h1><p>{{ $academicYear?->academic_year ?? 'All Academic Years' }} @if($type === 'attendance-list') | {{ $filters['month'] }} @endif</p></header><table class="report-table"><thead><tr><th>#</th><th>Student ID</th><th class="left">Student Name</th><th>Gender</th><th>Academic Year</th><th>Campus</th><th>Grade</th><th>Class</th><th>Group</th>@if($type === 'student-contact-list')<th>Contact / Phone</th><th>Address</th>@elseif($type === 'score-list')@for($i=1;$i<=$filters['score_columns'];$i++)<th>Score {{ $i }}</th>@endfor<th>Total</th><th>Remarks</th>@elseif($type === 'attendance-list')@for($i=1;$i<=$days;$i++)<th>{{ $i }}</th>@endfor<th>Present</th><th>Absent</th>@endif</tr></thead><tbody>@forelse($enrollments as $index => $row)<tr><td>{{ $index + 1 }}</td><td>{{ $row->student?->student_id ?? '-' }}</td><td class="left">{{ $row->student?->full_name_en ?: $row->student?->full_name_kh }}</td><td>{{ strtoupper(substr((string) $row->student?->gender, 0, 1)) === 'F' ? 'F' : 'M' }}</td><td>{{ $row->academicYear?->academic_year }}</td><td>{{ $row->campus?->campus_name_en }}</td><td>{{ $row->grade?->grade }}</td><td>{{ $row->schoolClass?->class_name }}</td><td>{{ $row->session?->session_short_name ?? '-' }}</td>@if($type === 'student-contact-list')<td>{{ $row->student?->contacts?->pluck('contact_value')->filter()->join(', ') ?: $row->student?->home_phone ?: '-' }}</td><td>{{ $row->student?->current_address_en ?: '-' }}</td>@elseif($type === 'score-list')@for($i=1;$i<=$filters['score_columns'];$i++)<td></td>@endfor<td></td><td></td>@elseif($type === 'attendance-list')@for($i=1;$i<=$days;$i++)<td></td>@endfor<td></td><td></td>@endif</tr>@empty<tr><td colspan="20">No students found.</td></tr>@endforelse</tbody></table>
@endif
<style>
    .moeys-date-footer {
        width: 92mm;
        margin: 12px 0 0 auto;
        text-align: center;
        font-family: "Khmer OS Siemreap", "Khmer OS Siem Reap", sans-serif;
        font-size: 12px;
        line-height: 1.6;
    }
    .moeys-date-footer div { margin: 0; }
</style>

<style>.report-header .motto{position:absolute;top:0;right:0;width:62mm;text-align:center;margin:0;font-size:13px}.report-header .motto::after{font-size:13px}.a4-page .report-header{padding-top:30mm}.a4-page .report-header h2,.a4-page .report-header .small{display:none}.report-table .khmer-name{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}</style>
<style>.report-table th{text-align:center !important}.report-table .khmer-name{text-align:left}</style>
<style>.a4-page .report-header{border-bottom:0}.a4-page .signature{display:none}</style>
<style>.a4-page .report-table thead th:nth-child(4){font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}.a4-page .report-table .khmer-name{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}</style>

<style>.a4-page .report-header{padding-top:48mm}.a4-page .report-header .logo{top:22mm;left:50%;transform:translateX(-50%);max-width:28mm;max-height:20mm}</style>
<style>.report-header .tacteing-number{font-family:Tacteing,"Tacteing Khmer",sans-serif;font-size:13px;line-height:1.2;margin-top:2px}</style>
<style>.report-header .tacteing-number{font-size:32px !important;line-height:.8;margin-top:4px}</style>

<style>.a4-page .report-header .logo{left:0;transform:none}</style>
<style>.a4-page .report-header .tacteing-number{position:absolute;top:27mm;right:0;width:62mm;text-align:center;margin:0}</style>
<style>.a4-page .report-header .tacteing-number{top:24mm;line-height:.55;margin-top:0}</style>
<style>.a4-page .report-header .logo{top:48mm;left:0;transform:none}.a4-page .report-header h1,.a4-page .report-header p{position:relative;z-index:1;text-align:center}</style>
<style>.a4-page .report-header{min-height:85mm;padding-top:38mm}.a4-page .report-header .logo{top:38mm;max-width:56mm;max-height:40mm}.a4-page .report-table{margin-top:14mm}</style>

<style>.a4-page .report-header{min-height:82mm;padding:38mm 10mm 7px}.a4-page .report-header .logo{top:6mm;left:4mm;max-width:38mm;max-height:27mm}.a4-page .report-header .logo-caption{position:absolute;top:33mm;left:0;width:48mm;text-align:center;font-family:Arial,sans-serif;font-size:10px;line-height:1.25;font-weight:700}.a4-page .report-header .logo-school{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif;font-size:11px;margin-bottom:3px}.a4-page .report-header h1{font-size:20px;margin:0 0 5px}.a4-page .report-header p{font-size:14px;margin:0}.a4-page .report-table{margin-top:10mm}</style>
<style>.a4-page .report-header .logo-caption{display:none}.a4-page .report-table{margin-top:5px}</style>
<style>.a4-page .report-header{min-height:0;padding-bottom:5px}.a4-page .report-table{margin-top:5px}</style>
<style>.print-office-footer{margin-top:10px;text-align:center;font-family:Arial,sans-serif;font-size:12px;line-height:1.45}</style>
<style>.print-office-footer{text-align:left}</style>


<style>.a4-page .report-table thead th{background:#dbeafe !important}</style>


<style>.a4-page .report-table th,.a4-page .report-table td{font-size:12px !important}</style>
<style>.report-header .motto::after{display:none !important}</style>
<style>.a4-page .report-header .moeys-title{font-family:"Khmer OS Muol Light","Khmer OS Siemreap",sans-serif;font-weight:300}</style>
<style>.a4-page .report-header .moeys-title,.a4-page .report-header .moeys-academic-year,.a4-page .report-header .moeys-campus{font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif}.a4-page .report-header .moeys-title{font-family:"Khmer OS Muol Light","Khmer OS Siemreap",sans-serif;font-weight:300}.a4-page .report-header .moeys-academic-year,.a4-page .report-header .moeys-campus{font-size:14px}</style>




<style>html:not([lang="km"]) .report-header .motto::after{display:block !important}</style>
<style>.moeys-date-footer{margin-top:12px;text-align:left;font-family:"Khmer OS Siemreap","Khmer OS Siem Reap",sans-serif;font-size:12px;line-height:1.6}.moeys-date-footer div{margin:0}</style>
<style>
    .moeys-date-footer {
        width: 92mm !important;
        margin: 12px 0 0 auto !important;
        text-align: center !important;
    }
    .moeys-date-footer div:last-child {
        font-family: "Khmer OS Muol Light", "Khmer OS Muol", serif;
    }
    .moeys-date-footer div:not(:last-child) {
        font-family: "Khmer OS Siem Reap", "Khmer OS Siemreap", sans-serif;
    }
</style>
    @vite('resources/js/reportsPrint.js')
</body></html>
