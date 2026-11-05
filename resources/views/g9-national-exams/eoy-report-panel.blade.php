        @if($type==='cover')
            <div class="eoy-motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
            <div class="eoy-ornament">5</div>
            <div class="eoy-cover-exam">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ</div>
            <div class="eoy-cover-date">សម័យប្រឡង៖ {{ $page['exam_date'] }}</div>
            <div class="eoy-cover-center">មណ្ឌលប្រឡង៖ {{ $page['center'] }}</div>
            <div class="eoy-cover-title">សលាកបត្រពិន្ទុប្រចាំឆ្នាំ</div>
            <div class="eoy-seal {{ $side===2?'eoy-red':'' }}">អក្សរសម្ងាត់</div>
            <div class="eoy-cover-room">@if($side===1)លេខបន្ទប់ &nbsp; ៖ &nbsp; <span class="eoy-red eoy-number">{{ \App\Support\G9CandidateReport::roomHeading((string)$page['room']) }}</span>@else &nbsp; @endif</div>
            <div class="eoy-cover-total">ចំនួនបេក្ខជនសរុប៖ &nbsp; <span class="eoy-red eoy-number">{{ \App\Support\G9CandidateReport::digits($page['total']) }}</span> &nbsp; នាក់</div>
        @elseif($side===1)
            <div class="eoy-campus">{{ $page['campus'] }}</div>
            <table width="100%" class="eoy-card-heading"><tr><td width="52%" class="eoy-card-title">សលាកបត្រពិន្ទុប្រចាំឆ្នាំ</td><td width="48%" class="eoy-seal-cell">អក្សរសម្ងាត់</td></tr></table>
            <table width="100%" class="eoy-identity">
                <tr><td width="23%" class="eoy-nowrap">គោត្តនាម និង នាម</td><td width="61%" class="eoy-filled eoy-name">{{ $page['name'] }}</td><td width="7%" class="eoy-nowrap">ភេទ</td><td width="9%" class="eoy-filled">{{ $page['gender'] }}</td></tr>
                <tr><td class="eoy-nowrap">ថ្ងៃ ខែ ឆ្នាំកំណើត</td><td colspan="3" class="eoy-filled eoy-center">{{ $page['dob'] }}</td></tr>
            </table>
            <table width="100%" class="eoy-detail-table"><tr><td width="18%" class="eoy-nowrap">សិស្សថ្នាក់ទី</td><td width="9%" class="eoy-filled">{{ $page['grade'] }}</td><td width="17%" class="eoy-nowrap">នៃវិទ្យាល័យ</td><td width="56%" class="eoy-filled eoy-school">{{ $page['school'] }}</td></tr></table>
            <div class="eoy-detail">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិចំណេះទូទៅ</div>
            <table width="100%" class="eoy-detail-table eoy-exam-row"><tr><td width="20%" class="eoy-nowrap">សម័យប្រឡង</td><td width="34%" class="eoy-filled eoy-red">{{ $page['exam_date'] }}</td><td width="17%" class="eoy-nowrap">លេខបន្ទប់</td><td width="9%" class="eoy-filled eoy-red eoy-room-seat">{{ \App\Support\G9CandidateReport::roomHeading((string)$page['room']) }}</td><td width="11%" class="eoy-nowrap">លេខតុ</td><td width="9%" class="eoy-filled eoy-red eoy-room-seat">{{ \App\Support\G9CandidateReport::digits((string)$page['seat']) }}</td></tr></table>
            <table width="100%" class="eoy-scores" style="width:115mm;margin-left:14mm;margin-top:2mm">
                <tr><td class="eoy-score-label" style="width:45mm;white-space:nowrap">- មធ្យមភាគពិន្ទុឆមាសទី១</td><td class="eoy-score-colon" style="width:3mm">៖</td><td class="eoy-filled eoy-red eoy-number" style="width:67mm">{{ $page['semester_1']??'—' }}</td></tr>
                <tr><td class="eoy-score-label">- មធ្យមភាគពិន្ទុឆមាសទី២</td><td class="eoy-score-colon">៖</td><td class="eoy-filled eoy-red eoy-number">{{ $page['semester_2']??'—' }}</td></tr>
                <tr><td class="eoy-score-label">- ពិន្ទុប្រចាំឆ្នាំ</td><td class="eoy-score-colon">៖</td><td class="eoy-filled eoy-red eoy-number">{{ $page['sum']??'—' }}</td></tr>
            </table>
            <div class="eoy-director"><div class="eoy-date-value">{{ $page['solar'] }}</div><strong>នាយកវិទ្យាល័យ</strong><div class="eoy-director-name eoy-red">{{ $page['director'] }}</div></div>
            <div class="eoy-compiler-line"><span data-eoy-fit="11.5" style="font-size:{{ $page['instruction_font_size']??11.5 }}pt">{{ \App\Support\G9EoyReport::CARD_INSTRUCTION }}</span></div>
            <table width="100%" class="eoy-detail-table eoy-compiler-row"><tr><td width="17%" class="eoy-nowrap">ប្រឡងដោយ</td><td width="48%" class="eoy-filled eoy-compiler-name">{{ $page['compiler'] }}</td><td width="15%" class="eoy-nowrap">ហត្ថលេខា</td><td width="20%" class="eoy-filled">&nbsp;</td></tr></table>
            <div class="eoy-notes"><strong>កំណត់សម្គាល់</strong><br>- ពិន្ទុមិនត្រូវមានស្នាមកោសលុប ឬសរសេរត្រួតឡើយ។<br>- មិនត្រូវមានសញ្ញាសម្គាល់អ្វីមួយនៅលើទំព័រទី២ឡើយ។<br>- ពិន្ទុប្រចាំឆ្នាំត្រូវយកតែផ្នែកគត់ប៉ុណ្ណោះ។<br><span data-eoy-fit="11.5" style="font-size:{{ $page['final_note_font_size']??11.5 }}pt">{{ \App\Support\G9EoyReport::CARD_FINAL_NOTE }}</span></div>
        @else
            <div class="eoy-back-heading"><div class="eoy-seal eoy-red">អក្សរសម្ងាត់</div></div>
            <div class="eoy-circle"><span>{{ $page['eoy']??'—' }}</span></div>
        @endif
        @unless($pdfExport??false)<div class="eoy-page-label {{ $side===2?'eoy-red':'' }}">ទំព័រទី{{ $side===1?'១':'២' }}</div>@endunless
