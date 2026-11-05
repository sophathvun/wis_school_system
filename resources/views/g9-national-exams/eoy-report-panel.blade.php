        @if($type==='cover')
            <div class="eoy-motto">ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</div>
            <div class="eoy-ornament">5</div>
            <div class="eoy-cover-exam">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិ</div>
            <div class="eoy-cover-date">សម័យប្រឡង៖ {{ $page['exam_date'] }}</div>
            <div class="eoy-cover-center">មណ្ឌលប្រឡង៖ {{ $page['center'] }}</div>
            <div class="eoy-cover-title">សលាកបត្រពិន្ទុប្រចាំឆ្នាំ</div>
            <div class="eoy-seal {{ $side===2?'eoy-red':'' }}">អក្សរសម្ងាត់</div>
            <div class="eoy-cover-room">@if($side===1)បន្ទប់លេខ &nbsp; ៖ &nbsp; <span class="eoy-red eoy-number">{{ $page['room'] }}</span>@else &nbsp; @endif</div>
            <div class="eoy-cover-total">ចំនួនបេក្ខជនសរុប៖ &nbsp; <span class="eoy-red eoy-number">{{ $page['total'] }}</span> &nbsp; នាក់</div>
        @elseif($side===1)
            <div class="eoy-campus">{{ $page['campus'] }}</div>
            <table class="eoy-card-heading"><tr><td class="eoy-card-title">សលាកបត្រពិន្ទុប្រចាំឆ្នាំ</td><td class="eoy-seal-cell">អក្សរសម្ងាត់</td></tr></table>
            <table class="eoy-identity">
                <tr><td class="eoy-nowrap">គោត្តនាម និង នាម</td><td class="eoy-filled eoy-name">{{ $page['name'] }}</td><td class="eoy-nowrap">ភេទ</td><td class="eoy-filled">{{ $page['gender'] }}</td></tr>
                <tr><td class="eoy-nowrap">ថ្ងៃ ខែ ឆ្នាំកំណើត</td><td colspan="3" class="eoy-filled eoy-center">{{ $page['dob'] }}</td></tr>
            </table>
            <div class="eoy-detail">សិស្សថ្នាក់ទី <span class="eoy-inline-value">{{ $page['grade'] }}</span> នៃវិទ្យាល័យ <span class="eoy-school">{{ $page['school'] }}</span></div>
            <div class="eoy-detail">ប្រឡងសញ្ញាបត្រមធ្យមសិក្សាបឋមភូមិចំណេះទូទៅ</div>
            <div class="eoy-detail">សម័យប្រឡង <span class="eoy-red">{{ $page['exam_date'] }}</span> &nbsp; បន្ទប់លេខ <span class="eoy-red eoy-inline-value">{{ $page['room'] }}</span> លេខតុ <span class="eoy-red eoy-inline-value">{{ $page['seat'] }}</span></div>
            <table class="eoy-scores">
                <tr><td>- មធ្យមភាគពិន្ទុឆមាសទី១</td><td class="eoy-filled eoy-red eoy-number">{{ $page['semester_1']??'—' }}</td></tr>
                <tr><td>- មធ្យមភាគពិន្ទុឆមាសទី២</td><td class="eoy-filled eoy-red eoy-number">{{ $page['semester_2']??'—' }}</td></tr>
                <tr><td>- ពិន្ទុប្រចាំឆ្នាំ</td><td class="eoy-filled eoy-red eoy-number">{{ $page['sum']??'—' }}</td></tr>
            </table>
            <div class="eoy-director"><div>{{ $page['lunar'] }}</div><div>{{ $page['solar'] }}</div><strong>នាយកសាខា</strong><div class="eoy-director-name eoy-red">{{ $page['director'] }}</div></div>
            <div class="eoy-compiler-line">ស្រង់ពិន្ទុសលាកបត្រពិន្ទុប្រចាំឆ្នាំចំនួនពីរទំព័រ សម្រាប់ប្រឡងថ្នាក់ទី៩<br>អ្នកស្រង់ពិន្ទុ៖ <span class="eoy-compiler-name">{{ $page['compiler'] }}</span></div>
            <div class="eoy-detail">ប្រឡងដោយ........................................... លេខតុ........................</div>
            <div class="eoy-notes"><strong>កំណត់សម្គាល់</strong><br>- ពិន្ទុឆមាសនីមួយៗត្រូវស្រង់តាមបញ្ជីពិន្ទុផ្លូវការ។<br>- ពិន្ទុប្រចាំឆ្នាំជាផលបូកនៃពិន្ទុឆមាសទាំងពីរ។<br>- ពិន្ទុប្រចាំឆ្នាំត្រូវបង្គត់ជាចំនួនគត់នៅទំព័រទី២។<br>- សូមពិនិត្យឈ្មោះ បន្ទប់ និងលេខតុឱ្យបានត្រឹមត្រូវ។</div>
        @else
            <div class="eoy-back-heading"><div class="eoy-seal eoy-red">អក្សរសម្ងាត់</div></div>
            <div class="eoy-circle"><span>{{ $page['eoy']??'—' }}</span></div>
        @endif
        @unless($pdfExport??false)<div class="eoy-page-label {{ $side===2?'eoy-red':'' }}">ទំព័រទី{{ $side===1?'១':'២' }}</div>@endunless
