        <div class="report-filter-field g9-settings-field">
            <label class="form-label" for="g9GivenDateDirect">Given Date</label>
            @php
                $givenDateValue = old('given_date', $filters['given_date']??'');
                $givenDateParsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $givenDateValue ?: '');
                $givenDateDisplay = $givenDateParsed && $givenDateParsed->format('Y-m-d') === $givenDateValue ? $givenDateParsed->format('d-m-Y') : $givenDateValue;
            @endphp
            <div class="date-picker g9-given-date-picker" data-premium-date-picker>
                <input type="hidden" form="g9CertificateSettings" id="g9GivenDate" name="given_date" data-premium-date-value value="{{ $givenDateValue }}" @disabled(!($canSaveGivenDate ?? false))>
                <input type="text" class="form-control" form="g9CertificateSettings" id="g9GivenDateDirect" data-premium-date-input required value="{{ $givenDateDisplay }}" placeholder="DD-MM-YYYY" inputmode="numeric" autocomplete="off" aria-label="Given Date in DD-MM-YYYY format" @disabled(!($canSaveGivenDate ?? false))>
                <button type="button" class="date-picker-trigger date-picker-calendar-button" data-premium-date-toggle aria-label="Open Given Date calendar" aria-expanded="false" aria-controls="g9GivenDateCalendar" @disabled(!($canSaveGivenDate ?? false))><i class="ti ti-calendar"></i></button>
                <div class="date-picker-popup d-none" id="g9GivenDateCalendar" role="dialog" aria-label="Choose Given Date">
                    <div class="date-picker-header">
                        <button type="button" class="date-picker-nav" data-premium-date-prev aria-label="Previous month"><i class="ti ti-chevron-left"></i></button>
                        <button type="button" class="date-picker-year-toggle" aria-label="Choose year"></button>
                        <button type="button" class="date-picker-nav" data-premium-date-next aria-label="Next month"><i class="ti ti-chevron-right"></i></button>
                    </div>
                    <div class="date-picker-year-popup d-none"><div class="date-picker-years"></div></div>
                    <div class="date-picker-grid">
                        <div class="date-picker-weekdays"><span>SU</span><span>MO</span><span>TU</span><span>WE</span><span>TH</span><span>FR</span><span>SA</span></div>
                        <div class="date-picker-days"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="report-filter-field g9-settings-field">
            <label class="form-label" for="g9NumberPrefix">Certificate Prefix</label>
            <input type="text" class="form-control" form="g9CertificateSettings" id="g9NumberPrefix" name="number_prefix" maxlength="16" pattern="[A-Za-z0-9-]+" placeholder="e.g. 26G9" required value="{{ old('number_prefix', $filters['number_prefix']??'') }}" @disabled(!($canEditCertificatePrefix ?? false))>
        </div>
