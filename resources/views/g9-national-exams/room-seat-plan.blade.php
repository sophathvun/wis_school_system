@if($page['logo'])<div class="g9-seat-plan-logo"><img src="{{ $page['logo'] }}" alt="School logo"></div>@endif
<div class="g9-seat-plan-title">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ</div>
<div class="g9-seat-plan-meta">សម័យប្រឡង៖ {{ $page['examDate'] }}<br>មណ្ឌលប្រឡង៖ {{ $page['center'] }}</div>
<div class="g9-seat-plan-caption">ប្លង់តុតាមបន្ទប់ប្រឡង</div>
<div class="g9-seat-plan-frame">
    <div class="g9-seat-plan-room">បន្ទប់លេខ៖ <span class="g9-seat-red g9-room-number">{{ \App\Support\G9CandidateReport::roomHeading((string)$page['room']) }}</span></div>
    <div class="g9-whiteboard">ក្តារខៀន</div>
    <table class="g9-room-seats" cellspacing="3" cellpadding="0">
        @foreach(range(0,4) as $row)<tr>
            @foreach(range(0,4) as $column)
                @php($student = $page['rows'][$column*5 + ($column%2 === 0 ? $row : 4-$row)] ?? null)
                <td width="20%" @if(!$student) class="g9-seat-empty" @endif>
                    @if($student)
                        <div class="g9-room-seat-number">{{ \App\Support\G9CandidateReport::digits($student['seat']) }}</div>
                        <div class="g9-room-seat-name"><span data-fit-seat-name data-base-size="9" style="font-size:{{ $student['seat_name_size'] ?? 9 }}pt">{{ $student['name'] }}</span></div>
                        <div class="g9-room-seat-name-english"><span data-fit-seat-name data-base-size="8" style="font-size:{{ $student['seat_english_size'] ?? 8 }}pt">{{ $student['name_en'] }}</span></div>
                        <div class="g9-room-seat-grade">{{ implode('-',array_filter([$student['campus'] ?? '',$student['grade'] ?? ''],fn($value)=>$value !== '')) }}</div>
                    @endif
                </td>
            @endforeach
        </tr>@endforeach
    </table>
</div>
