@php
    $currentPage = (int) ($pagination['page'] ?? 1);
    $lastPage = (int) ($pagination['lastPage'] ?? 1);
    $pages = $lastPage <= 5
        ? range(1, $lastPage)
        : ($currentPage <= 3
            ? [1, 2, 3, 'ellipsis', $lastPage]
            : ($currentPage >= $lastPage - 2
                ? array_merge([1, 'ellipsis'], range($lastPage - 2, $lastPage))
                : [1, 'ellipsis', $currentPage - 1, $currentPage, $currentPage + 1, 'ellipsis', $lastPage]));
@endphp
<div class="premium-pagination mt-3">
    <ul class="pagination premium-pagination-list m-0">
        <li class="page-item {{ $currentPage === 1 || ($pagination['pageSize'] ?? '') === 'all' ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $currentPage > 1 && ($pagination['pageSize'] ?? '') !== 'all' ? request()->fullUrlWithQuery(['preview_page' => $currentPage - 1]) : '#' }}"><i class="ti ti-chevron-left icon icon-1"></i></a>
        </li>
        @foreach($pages as $page)
            @if($page === 'ellipsis')
                <li class="premium-pagination-ellipsis" aria-hidden="true">&hellip;</li>
            @else
                <li class="page-item {{ $page === $currentPage ? 'active' : '' }} {{ ($pagination['pageSize'] ?? '') === 'all' ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ ($pagination['pageSize'] ?? '') === 'all' ? '#' : request()->fullUrlWithQuery(['preview_page' => $page]) }}">{{ $page }}</a>
                </li>
            @endif
        @endforeach
        <li class="page-item {{ $currentPage === $lastPage || ($pagination['pageSize'] ?? '') === 'all' ? 'disabled' : '' }}">
            <a class="page-link" href="{{ $currentPage < $lastPage && ($pagination['pageSize'] ?? '') !== 'all' ? request()->fullUrlWithQuery(['preview_page' => $currentPage + 1]) : '#' }}"><i class="ti ti-chevron-right icon icon-1"></i></a>
        </li>
    </ul>
    <p class="premium-pagination-info m-0">
        Showing <strong>{{ number_format($pagination['from'] ?? 0) }} to {{ number_format($pagination['to'] ?? 0) }}</strong>
        of <strong>{{ number_format($pagination['total'] ?? 0) }} entries</strong>
    </p>
    <div class="premium-pagination-controls">
        <label class="premium-pagination-select">
            <select class="form-select form-select-sm" aria-label="Entries per page" data-preview-page-size>
                @foreach(($pagination['sizes'] ?? ['all', '25', '50', '75', '100']) as $size)
                    <option value="{{ $size }}" data-url="{{ request()->fullUrlWithQuery(['preview_page_size' => $size, 'preview_page' => 1]) }}" @selected((string) ($pagination['pageSize'] ?? '25') === (string) $size)>
                        {{ $size === 'all' ? 'All / page' : $size . ' / page' }}
                    </option>
                @endforeach
            </select>
        </label>
        @if(($pagination['pageSize'] ?? '') !== 'all')
            <label class="premium-pagination-goto">
                <span>Go to</span>
                <input type="number" class="form-control form-control-sm" min="1" max="{{ $lastPage }}" value="{{ $currentPage }}" data-preview-goto data-preview-goto-url="{{ request()->fullUrlWithQuery(['preview_page' => '__page__']) }}">
                <span>Page</span>
            </label>
        @endif
    </div>
</div>
