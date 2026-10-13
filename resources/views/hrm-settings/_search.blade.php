            <form method="GET" action="{{ route('hrm.settings.index') }}" class="hrm-settings-header-search" data-hrm-settings-search>
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <input type="hidden" name="per_page" value="{{ $records->perPage() }}">
                <input type="hidden" name="sortBy" value="{{ $sortBy }}"><input type="hidden" name="sortDir" value="{{ $sortDir }}">
                <div class="input-icon hrm-settings-search"><span class="input-icon-addon"><i class="ti ti-search" aria-hidden="true"></i></span><input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search {{ strtolower($entityLabel) }}s" aria-label="Search {{ strtolower($entityLabel) }}s" data-search-normalized="true"></div>
                <noscript><button class="btn btn-primary mt-2">Search</button></noscript>
            </form>
