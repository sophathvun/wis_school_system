@extends('layouts.app')

@section('title', 'Withdrawal Reasons')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administrator</div>
                <h2 class="page-title">Withdrawal Reasons</h2>
            </div>
            <div class="col-auto d-flex gap-2">
                <a class="btn btn-outline-primary" href="{{ route('withdrawal-reasons.print') }}" target="_blank" rel="noopener"><i class="ti ti-printer icon"></i> Print</a>
                <a class="btn btn-outline-success" href="{{ route('withdrawal-reasons.excel') }}"><i class="ti ti-file-spreadsheet icon"></i> Excel</a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reasonModal"><i
                        class="ti ti-plus me-1"></i> New Reason</button>
            </div>
        </div>
    </div>
@endsection

@section('content')

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Withdrawal Reason List</h3>
        </div>
        <div class="card-body border-bottom py-3">
            <div class="row col-12 g-2 align-items-center justify-content-end">
                <div class="col-auto d-flex flex-wrap gap-2 align-items-center">
                    <div class="input-icon"><span class="input-icon-addon"><i class="ti ti-search icon"></i></span>
                        <form><input class="form-control form-control-sm" name="search" value="{{ request('search') }}"
                                placeholder="Search withdrawal reasons"></form>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    @php
                        $sortUrl = function (string $field) {
                            $currentSort = request('sortBy', 'sort_order');
                            $currentDir = request('sortDir', 'asc');
                            $nextDir = $currentSort === $field && $currentDir === 'asc' ? 'desc' : 'asc';

                            return route('withdrawal-reasons.index', array_merge(request()->query(), [
                                'sortBy' => $field,
                                'sortDir' => $nextDir,
                                'page' => 1,
                            ]));
                        };
                        $sortIcon = function (string $field) {
                            if (request('sortBy', 'sort_order') !== $field) {
                                return '↕';
                            }

                            return request('sortDir', 'asc') === 'asc' ? '↑' : '↓';
                        };
                    @endphp
                    <tr>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('sort_order') }}">ORDER {{ $sortIcon('sort_order') }}</a></th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('name_en') }}">REASON (ENGLISH) {{ $sortIcon('name_en') }}</a></th>
                        <th><a class="table-sort-button" href="{{ $sortUrl('name_kh') }}">មូលហេតុ {{ $sortIcon('name_kh') }}</a></th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('status') }}">STATUS {{ $sortIcon('status') }}</a></th>
                        <th class="text-center text-uppercase">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reasons as $reason)
                        <tr>
                            <td>{{ $reason->sort_order }}</td>
                            <td>{{ $reason->name_en }}<div class="text-secondary small">{{ $reason->reason_key }}</div>
                            </td>
                            <td class="school-profile-khmer">{{ $reason->name_kh ?: '—' }}</td>
                            <td><span
                                    class="badge {{ $reason->status ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' }}">{{ $reason->status ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="text-center"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                    data-bs-target="#reasonModal" data-reason='@json($reason)'><i
                                        class="ti ti-edit"></i></button>
                                @if ($reason->status)
                                    <form class="d-inline" method="POST"
                                        action="{{ route('withdrawal-reasons.delete', $reason) }}"
                                        data-withdrawal-reason-delete-form>@csrf
                                        @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i
                                                class="ti ti-ban"></i></button></form>
                                @endif
                            </td>
                        </tr>
                    @empty <tr>
                            <td colspan="5" class="text-center py-5 text-secondary">No withdrawal reasons found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div class="row g-2 justify-content-center justify-content-sm-between">
                <div class="col-12 d-flex justify-content-center">
                    <div class="premium-pagination">
                        <ul class="pagination premium-pagination-list m-0">
                            <li class="page-item {{ $reasons->onFirstPage() ? 'disabled' : '' }}"><a class="page-link"
                                    href="{{ $reasons->previousPageUrl() ?: '#' }}"><i
                                        class="ti ti-chevron-left icon icon-1"></i></a></li>
                            @for ($page = 1; $page <= $reasons->lastPage(); $page++)
                                <li class="page-item {{ $page === $reasons->currentPage() ? 'active' : '' }}"><a
                                        class="page-link" href="{{ $reasons->url($page) }}">{{ $page }}</a></li>
                            @endfor
                            <li class="page-item {{ $reasons->currentPage() === $reasons->lastPage() ? 'disabled' : '' }}">
                                <a class="page-link" href="{{ $reasons->nextPageUrl() ?: '#' }}"><i
                                        class="ti ti-chevron-right icon icon-1"></i></a></li>
                        </ul>
                        <p class="premium-pagination-info m-0">Showing <strong>{{ $reasons->firstItem() ?: 0 }} to
                                {{ $reasons->lastItem() ?: 0 }}</strong> of <strong>{{ $reasons->total() }}
                                entries</strong></p>
                        <div class="premium-pagination-controls"><label class="premium-pagination-goto"><span>Go
                                    to</span><input type="number" min="1" max="{{ $reasons->lastPage() }}"
                                    value="{{ $reasons->currentPage() }}"
                                    onchange="location.href='{{ route('withdrawal-reasons.index') }}?page='+this.value+'&perPage={{ request('perPage', 10) }}&search='+encodeURIComponent('{{ request('search') }}')"><span>Page</span></label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="reasonModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content">
                <form method="POST" action="{{ route('withdrawal-reasons.save') }}">
                    <div class="modal-header">
                        <h3 class="modal-title">Withdrawal Reason</h3><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">@csrf<input type="hidden" name="reason_id" id="reason_id">
                        <div class="mb-3"><label class="form-label">System Key *</label><input class="form-control"
                                name="reason_key" id="reason_key" required placeholder="example: family_reason"></div>
                        <div class="mb-3"><label class="form-label">Reason (English) *</label><input class="form-control"
                                name="name_en" id="reason_name_en" required></div>
                        <div class="mb-3"><label class="form-label">Reason (Khmer)</label><input
                                class="form-control school-profile-khmer" name="name_kh" id="reason_name_kh"></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">Display Order *</label><input type="number"
                                    min="0" class="form-control" name="sort_order" id="reason_sort_order"
                                    value="0" required></div>
                            <div class="col-6"><label class="form-label">Status *</label><select class="form-select"
                                    name="status" id="reason_status">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn me-auto"
                            data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Reason</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@vite('resources/css/pages/withdrawal-reasons.css')
@push('scripts')
    <script>
        document.getElementById('reasonModal')?.addEventListener('show.bs.modal', event => {
            const data = event.relatedTarget?.dataset.reason ? JSON.parse(event.relatedTarget.dataset.reason) :
            null;
            document.getElementById('reason_id').value = data?.id || '';
            document.getElementById('reason_key').value = data?.reason_key || '';
            document.getElementById('reason_name_en').value = data?.name_en || '';
            document.getElementById('reason_name_kh').value = data?.name_kh || '';
            document.getElementById('reason_sort_order').value = data?.sort_order || 0;
            document.getElementById('reason_status').value = data?.status ? 1 : 0;
        });
        document.querySelector('.premium-pagination-controls')?.insertAdjacentHTML('afterbegin',
            '<label class="premium-pagination-select"><select class="form-select form-select-sm" aria-label="Entries per page" onchange="location.href=\'' +
            {{ Js::from(route('withdrawal-reasons.index')) }} +
            '?page=1&perPage=\'+this.value+\'&search=\'+encodeURIComponent(\'' + {{ Js::from(request('search', '')) }} +
            '\')"><option value="10" {{ request('perPage', 10) == 10 ? 'selected' : '' }}>10 / page</option><option value="25" {{ request('perPage') == 25 ? 'selected' : '' }}>25 / page</option><option value="50" {{ request('perPage') == 50 ? 'selected' : '' }}>50 / page</option><option value="100" {{ request('perPage') == 100 ? 'selected' : '' }}>100 / page</option></select></label>'
            );

        document.querySelectorAll('[data-withdrawal-reason-delete-form]').forEach(form => {
            form.addEventListener('submit', async event => {
                if (form.dataset.confirmed === 'true') {
                    return;
                }

                event.preventDefault();

                const confirmAction = window.schoolShowConfirm
                    ? window.schoolShowConfirm(
                        'Deactivate Withdrawal Reason',
                        'Are you sure you want to deactivate this withdrawal reason?',
                        'Deactivate',
                        'Cancel',
                    )
                    : window.Swal?.fire({
                        title: 'Deactivate Withdrawal Reason',
                        text: 'Are you sure you want to deactivate this withdrawal reason?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Deactivate',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#d63939',
                    });

                const result = confirmAction ? await confirmAction : {
                    isConfirmed: confirm('Deactivate this withdrawal reason?'),
                };

                if (!result.isConfirmed) {
                    return;
                }

                form.dataset.confirmed = 'true';
                form.submit();
            });
        });
    </script>
@endpush
