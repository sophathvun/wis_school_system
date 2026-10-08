@php
    $lastPage=$records->lastPage();
    $currentPage=$records->currentPage();
    $pageSize=(string)($filters['per_page']??'20');
    $pages=$lastPage<=5?range(1,$lastPage):($currentPage<=3?[1,2,3,'ellipsis',$lastPage]:($currentPage>=$lastPage-2?array_merge([1,'ellipsis'],range($lastPage-2,$lastPage)):[1,'ellipsis',$currentPage-1,$currentPage,$currentPage+1,'ellipsis',$lastPage]));
@endphp
<div class="premium-pagination">
    <ul class="pagination premium-pagination-list m-0">
        <li class="page-item {{ $records->onFirstPage()?'disabled':'' }}">
            <a class="page-link" href="{{ $records->previousPageUrl()??'#' }}" aria-label="Previous page" @if($records->onFirstPage()) aria-disabled="true" tabindex="-1" @endif><i class="ti ti-chevron-left icon icon-1"></i></a>
        </li>
        @foreach($pages as $page)
            @if($page==='ellipsis')
                <li class="premium-pagination-ellipsis" aria-hidden="true">…</li>
            @else
                <li class="page-item {{ $page===$currentPage?'active':'' }}"><a class="page-link" href="{{ $records->url($page) }}" aria-label="Page {{ $page }}" @if($page===$currentPage) aria-current="page" @endif>{{ $page }}</a></li>
            @endif
        @endforeach
        <li class="page-item {{ $records->hasMorePages()?'':'disabled' }}">
            <a class="page-link" href="{{ $records->nextPageUrl()??'#' }}" aria-label="Next page" @if(!$records->hasMorePages()) aria-disabled="true" tabindex="-1" @endif><i class="ti ti-chevron-right icon icon-1"></i></a>
        </li>
    </ul>
    <p class="premium-pagination-info m-0">Showing <strong>{{ $records->firstItem()??0 }} to {{ $records->lastItem()??0 }}</strong> of <strong>{{ $records->total() }} entries</strong></p>
    <div class="premium-pagination-controls">
        <label class="premium-pagination-select">
            <select class="form-select form-select-sm" aria-label="Entries per page" data-pagination-per-page>
                @foreach(['all','20','50','75','100'] as $size)
                    <option value="{{ $size }}" @selected($pageSize===$size)>{{ $size==='all'?'All':$size.' / page' }}</option>
                @endforeach
            </select>
        </label>
        <label class="premium-pagination-goto"><span>Go to</span><input type="number" class="form-control form-control-sm" min="1" max="{{ $lastPage }}" value="{{ $currentPage }}" aria-label="Go to page" data-pagination-goto data-pagination-max="{{ $lastPage }}" @disabled($pageSize==='all')><span>Page</span></label>
    </div>
</div>
