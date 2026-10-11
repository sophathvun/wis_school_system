<table class="skipping-statistics-table">
    <colgroup><col style="width:3%"><col style="width:6%">@foreach($statistics['columns'] as $column)<col><col>@endforeach<col style="width:4%"><col style="width:4%"></colgroup>
    <thead>
        <tr><th colspan="2" class="statistics-campus">Campus</th>
            @foreach($statistics['groups'] as $group=>$columns)
                @foreach($columns as $column)<th colspan="2" class="statistics-{{ $group }} statistics-grade-header"><span class="statistics-grade-path">{{ $statistics['headers'][$column]['source'] }}@if($statistics['headers'][$column]['target'])<span class="statistics-grade-arrow">→</span>{{ $statistics['headers'][$column]['target'] }}@endif</span></th>@endforeach
            @endforeach
            <th colspan="2" class="statistics-total">Total</th></tr>
        <tr><th class="statistics-campus">No.</th><th class="statistics-campus">Campus</th>
            @foreach($statistics['groups'] as $group=>$columns)
                @foreach($columns as $column)<th class="statistics-{{ $group }}">Stu.</th><th class="statistics-{{ $group }}">F</th>@endforeach
            @endforeach
            <th class="statistics-total">Stu.</th><th class="statistics-total">F</th></tr>
    </thead>
    <tbody>@foreach($statistics['rows'] as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row['campus'] }}</td>@foreach($statistics['columns'] as $column)<td>{{ $row['cells'][$column]['total'] ?: '' }}</td><td>{{ $row['cells'][$column]['female'] ?: '' }}</td>@endforeach<td>{{ $row['total'] }}</td><td>{{ $row['female'] }}</td></tr>@endforeach</tbody>
    <tfoot>
        <tr><th colspan="2" rowspan="2" class="statistics-campus">Grand<br>Total</th>
            @foreach($statistics['groups'] as $group=>$columns)
                @foreach($columns as $column)<th class="statistics-{{ $group }}">{{ $statistics['totals'][$column]['total'] }}</th><th class="statistics-{{ $group }}">{{ $statistics['totals'][$column]['female'] }}</th>@endforeach
            @endforeach
            <th rowspan="2" class="statistics-total">{{ $statistics['total'] }}</th><th rowspan="2" class="statistics-total">{{ $statistics['female'] }}</th></tr>
        <tr>@foreach($statistics['groupTotals'] as $group=>$total)<th colspan="{{ $total['span']/2 }}" class="statistics-{{ $group }}">Total: {{ $total['total'] }}</th><th colspan="{{ $total['span']/2 }}" class="statistics-{{ $group }}">Female: {{ $total['female'] }}</th>@endforeach</tr>
    </tfoot>
</table>
