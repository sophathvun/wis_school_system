<div class="skipping-report-toolbar">
    <button type="button" data-skipping-print>Print A4 Report</button>
    <a href="{{ route('student-skipping-grade.report.export', ['kind'=>$reportKind, 'format'=>'xlsx'] + $exportFilters) }}">Excel</a>
    <a href="{{ route('student-skipping-grade.report.export', ['kind'=>$reportKind, 'format'=>'pdf'] + $exportFilters) }}">PDF</a>
    @if($reportKind === 'statistics')<button type="button" data-skipping-share-statistics>Share / A4 Image</button>@endif
    <button type="button" data-skipping-close>Close</button>
</div>
