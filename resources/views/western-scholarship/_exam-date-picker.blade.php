<div class="col-md-4 scholarship-exam-date-field">
    <label class="form-label" for="examDate">Exam Date <span class="text-danger">*</span></label>
    <div class="date-picker scholarship-exam-date" data-premium-date-picker data-premium-date-format="DD-MMM-YYYY" data-premium-date-boundary=".scholarship-forms" data-premium-date-placement="auto">
        <input id="examDate" type="text" class="form-control @error('exam_date') is-invalid @enderror" data-premium-date-input placeholder="DD-MMM-YYYY" autocomplete="off" aria-label="Exam Date in DD-MMM-YYYY format" required>
        <input type="hidden" name="exam_date" data-premium-date-value value="{{ old('exam_date',$editingExam?->exam_date?->format('Y-m-d')) }}">
        <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open Exam Date calendar" aria-expanded="false" aria-controls="examDateCalendar"><i class="ti ti-calendar" aria-hidden="true"></i></button>
        <div class="date-picker-popup d-none" id="examDateCalendar" role="dialog" aria-label="Choose Exam Date">
            <div class="date-picker-header">
                <button type="button" class="date-picker-nav" data-premium-date-prev aria-label="Previous month"><i class="ti ti-chevron-left" aria-hidden="true"></i></button>
                <button type="button" class="date-picker-year-toggle" aria-label="Choose year"></button>
                <button type="button" class="date-picker-nav" data-premium-date-next aria-label="Next month"><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
            </div>
            <div class="date-picker-year-popup d-none"><div class="date-picker-years"></div></div>
            <div class="date-picker-grid">
                <div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div>
                <div class="date-picker-days"></div>
            </div>
            <div class="scholarship-calendar-footer"><button type="button" class="btn btn-sm btn-primary" data-premium-date-today>Today</button></div>
        </div>
    </div>
    @error('exam_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>