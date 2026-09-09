@extends('layouts.app')

@section('title', 'Withdrawal Reasons')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administrator</div>
                <h2 class="page-title">Withdrawal Reasons</h2>
            </div>
            <div class="col-auto d-flex gap-2 withdrawal-reason-page-actions">
                <a class="btn btn-outline-primary" href="{{ route('withdrawal-reasons.print') }}" target="_blank" rel="noopener"><i class="ti ti-printer icon"></i> Print</a>
                <a class="btn btn-outline-success" href="{{ route('withdrawal-reasons.excel') }}"><i class="ti ti-file-spreadsheet icon"></i> Excel</a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reasonModal"><i
                        class="ti ti-plus me-1"></i> New Reason</button>
            </div>
        </div>
    </div>
@endsection

@section('content')

    <div class="card withdrawal-reasons-list-card">
        <div class="card-header">
            <h3 class="card-title">WITHDRAWAL REASON LIST</h3>
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
                                return '&varr;';
                            }

                            return request('sortDir', 'asc') === 'asc' ? '&uarr;' : '&darr;';
                        };
                    @endphp
                    <tr>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('sort_order') }}">ORDER {!! $sortIcon('sort_order') !!}</a></th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('name_en') }}">REASON (ENGLISH) {!! $sortIcon('name_en') !!}</a></th>
                        <th><a class="table-sort-button school-profile-khmer" href="{{ $sortUrl('name_kh') }}">&#x1798;&#x17bc;&#x179b;&#x17a0;&#x17c1;&#x178f;&#x17bb;&#x1794;&#x17c4;&#x17c7;&#x1794;&#x1784;&#x17cb;&#x1780;&#x17b6;&#x179a;&#x179f;&#x17b7;&#x1780;&#x17d2;&#x179f;&#x17b6; {!! $sortIcon('name_kh') !!}</a></th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('status') }}">STATUS {!! $sortIcon('status') !!}</a></th>
                        <th class="text-center text-uppercase">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reasons as $reason)
                        <tr>
                            <td>{{ $reason->sort_order }}</td>
                            <td>{{ $reason->name_en }}<div class="text-secondary small">{{ $reason->reason_key }}</div>
                            </td>
                            <td class="school-profile-khmer">{{ $reason->name_kh ?: '-' }}</td>
                            <td><button type="button" class="status-toggle {{ $reason->status ? 'is-active' : '' }}" data-status-toggle data-status-entity="withdrawal-reason" data-status-id="{{ $reason->id }}" data-status="{{ $reason->status ? 1 : 0 }}" aria-pressed="{{ $reason->status ? 'true' : 'false' }}"><span class="status-toggle-label">{{ $reason->status ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span></button></td>
                            <td class="text-center"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                    data-bs-target="#reasonModal" data-reason='@json($reason)'><i
                                        class="ti ti-edit"></i></button>
                                @if ($reason->status)
                                    <form class="d-inline" method="POST"
                                        action="{{ route('withdrawal-reasons.delete', $reason) }}"
                                        data-withdrawal-reason-delete-form>@csrf
                                        @method('DELETE')<button type="submit" class="btn btn-danger btn-sm" aria-label="Delete withdrawal reason"><i
                                                class="ti ti-trash"></i></button></form>
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
        <div class="withdrawal-reason-mobile-cards">
            @forelse($reasons as $reason)
                <div class="withdrawal-reason-mobile-card">
                    <div class="withdrawal-reason-card-top">
                        <div class="withdrawal-reason-card-number">{{ str_pad($reasons->firstItem() + $loop->index, 2, '0', STR_PAD_LEFT) }}</div>
                        <button type="button" class="status-toggle {{ $reason->status ? 'is-active' : '' }}"
                            data-status-toggle data-status-entity="withdrawal-reason"
                            data-status-id="{{ $reason->id }}" data-status="{{ $reason->status ? 1 : 0 }}"
                            aria-pressed="{{ $reason->status ? 'true' : 'false' }}">
                            <span class="status-toggle-label">{{ $reason->status ? 'ON' : 'OFF' }}</span><span class="status-toggle-knob"></span>
                        </button>
                    </div>
                    <div class="withdrawal-reason-card-grid">
                        <div>
                            <div class="withdrawal-reason-card-label school-profile-khmer">&#x1798;&#x17bc;&#x179b;&#x17a0;&#x17c1;&#x178f;&#x17bb;&#x1794;&#x17c4;&#x17c7;&#x1794;&#x1784;&#x17cb;&#x1780;&#x17b6;&#x179a;&#x179f;&#x17b7;&#x1780;&#x17d2;&#x179f;&#x17b6;</div>
                            <div class="withdrawal-reason-card-name school-profile-khmer">{{ $reason->name_kh ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="withdrawal-reason-card-label">Reason (English)</div>
                            <div class="withdrawal-reason-card-name">{{ $reason->name_en ?: '-' }}</div>
                            <div class="withdrawal-reason-card-subtitle">{{ $reason->reason_key ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="withdrawal-reason-card-label">Order</div>
                            <div class="withdrawal-reason-card-value">{{ $reason->sort_order }}</div>
                        </div>
                    </div>
                    <div class="withdrawal-reason-card-actions">
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                            data-bs-target="#reasonModal" data-reason='@json($reason)'><i class="ti ti-edit"></i></button>
                        @if ($reason->status)
                            <form method="POST" action="{{ route('withdrawal-reasons.delete', $reason) }}"
                                data-withdrawal-reason-delete-form>@csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger"><i class="ti ti-trash"></i></button>
                            </form>
                        @else
                            <button type="button" class="btn btn-outline-danger" disabled><i class="ti ti-trash"></i></button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center text-secondary py-3">No withdrawal reasons found.</div>
            @endforelse
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
                                    value="{{ $reasons->currentPage() }}" id="withdrawalReasonPageInput"><span>Page</span></label>
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
                            <div class="col-6 withdrawal-reason-status-field"><input type="hidden" name="status" id="reason_status" value="1"><button type="button" id="reason-status-toggle" class="status-toggle is-active" aria-pressed="true"><span class="status-toggle-label">ON</span><span class="status-toggle-knob"></span></button></div>
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
@vite('resources/js/withdrawalReasons.js')


