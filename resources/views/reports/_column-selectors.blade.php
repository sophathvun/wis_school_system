<div class="row g-3">
    @foreach(($studentListColumns ?? []) as $groupName => $columns)
        @php($selectedCount = collect($columns)->whereIn('key', $filters['selected_columns'] ?? [])->count())
        <div class="col-lg-4">
            <div class="report-filter-field report-column-dropdown" data-column-dropdown>
                <label class="form-label" id="reportColumnLabel{{ $loop->index }}">{{ $groupName }}</label>
                <button type="button" class="report-filter-toggle" data-column-toggle
                    aria-labelledby="reportColumnLabel{{ $loop->index }} reportColumnSummary{{ $loop->index }}"
                    aria-expanded="false" aria-controls="reportColumnMenu{{ $loop->index }}">
                    <span id="reportColumnSummary{{ $loop->index }}" data-column-summary>{{ $selectedCount ? $selectedCount . ' columns selected' : 'Select columns' }}</span>
                    <i class="ti ti-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="report-column-menu" id="reportColumnMenu{{ $loop->index }}" data-column-menu>
                    <input type="search" class="form-control report-column-search" placeholder="Search {{ $groupName }}"
                        aria-label="Search {{ $groupName }} columns" data-column-search autocomplete="off">
                    <div class="get-student-list-column-options" data-column-options>
                        @foreach($columns as $column)
                            <label class="form-check report-column-option" data-column-option>
                                <input class="form-check-input" type="checkbox" name="selected_columns[]" value="{{ $column['key'] }}"
                                    @checked(in_array($column['key'], $filters['selected_columns'] ?? [], true))>
                                <span class="form-check-label">{{ $column['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="text-secondary small py-2 d-none" data-column-empty>No matching columns.</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
