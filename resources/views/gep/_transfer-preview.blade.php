@php($result=session('gep_transfer_result')??($preview['result']??null))
@if($result)
<section class="card mb-3" aria-label="Import result">
    <div class="card-body">
        <div class="alert {{ $result['applied']?'alert-success':'alert-warning' }} mb-0" role="status">
            <strong>{{ $result['applied']?'Import completed':'Preview completed — no records saved' }}</strong>:
            {{ ['students'=>'Student Information','enrollments'=>'Enrollment','families'=>'Family'][$result['type']] }} — {{ $result['rows'] }} rows, {{ $result['created'] }} new, {{ $result['updated'] }} existing records.
            @if($result['unresolved_names']??0)<div>{{ $result['unresolved_names'] }} names could not be linked uniquely. Their original text is retained for review.</div>@endif
            @unless($result['applied'])<div>Review the list below, then click <strong>Apply previewed file</strong> to save. You do not need to select the CSV again.</div>
                @if(empty($preview['preview_token']))<div>Select the CSV and click Preview once more to enable Apply.</div>@endif
            @endunless
        </div>
        @unless($result['applied'])@include('gep._transfer-apply')@endunless
    </div>
    @if(!$result['applied'] && isset($preview['rows']))
        <div class="px-3 pb-2"><strong>{{ $preview['filename'] }}</strong> · Showing {{ count($preview['rows']) }} of {{ $result['rows'] }} rows. All {{ $result['rows'] }} rows passed validation.</div>
        <div class="table-responsive" style="max-height:28rem;overflow:auto">
            <table class="table table-striped table-bordered table-vcenter mb-0">
                <thead class="sticky-top bg-body"><tr><th>No.</th>@foreach($preview['columns'] as $column)<th class="text-nowrap">{{ $column==='student_identifier'?'Student ID':ucwords(str_replace('_',' ',$column)) }}</th>@endforeach</tr></thead>
                <tbody>@foreach($preview['rows'] as $row)<tr><td>{{ $loop->iteration }}</td>@foreach($preview['columns'] as $column)<td class="text-nowrap">{{ $row[$column]??'' }}</td>@endforeach</tr>@endforeach</tbody>
            </table>
        </div>
    @endif
</section>
@endif
