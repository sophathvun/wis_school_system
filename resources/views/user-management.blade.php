@extends('layouts.app')
@section('title', 'Users')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Administrator</div>
                <h2 class="page-title">Users</h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
                <form class="user-header-toolbar" method="GET">
                    <input type="hidden" name="sortBy" value="{{ request('sortBy', 'name') }}">
                    <input type="hidden" name="sortDir" value="{{ request('sortDir', 'asc') }}">
                    <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                    <div class="input-icon user-header-search"><span class="input-icon-addon"><i
                                class="ti ti-search icon"></i></span><input type="text" name="search"
                            value="{{ request('search') }}" class="form-control" placeholder="Search users"></div>
                    <a class="btn btn-outline-primary" href="{{ route('users.print') }}" target="_blank"
                        rel="noopener"><i class="ti ti-printer icon"></i> Print</a>
                    <a class="btn btn-outline-success" href="{{ route('users.excel') }}"><i
                            class="ti ti-file-spreadsheet icon"></i> Excel</a>
                    <a id="btnNewUser" class="btn btn-primary" href="{{ route('users.index', ['create' => 1]) }}"><i
                            class="ti ti-plus icon"></i> New User</a>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('content')
    @php
        $formatCambodiaPhone = function ($value) {
            $digits = preg_replace('/\D+/', '', (string) $value);
            if ($digits === '') {
                return '';
            }
            if (str_starts_with($digits, '855')) {
                $digits = substr($digits, 3);
            }
            if (str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }
            $digits = substr($digits, 0, 9);
            if ($digits === '') {
                return '';
            }
            if (strlen($digits) <= 2) {
                return '+855 ' . $digits;
            }
            if (strlen($digits) <= 5) {
                return '+855 ' . substr($digits, 0, 2) . ' ' . substr($digits, 2);
            }
            return '+855 ' . substr($digits, 0, 2) . ' ' . substr($digits, 2, 3) . ' ' . substr($digits, 5);
        };
    @endphp

    <div class="modal modal-blur fade" id="userModal" tabindex="-1" aria-hidden="true"
        data-open-on-load="{{ $editUser || ($createUser ?? false) ? '1' : '0' }}">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $editUser ? 'Edit User' : 'Create User' }}</h5>
                    <a class="btn-close" href="{{ route('users.index') }}" aria-label="Close"></a>
                </div>
                <form method="POST" action="{{ route('users.save') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $editUser?->id }}">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="student-photo-upload-row">
                                    <div class="logo-dropzone" id="staffPhotoDropzone" tabindex="0">
                                        <div class="user-photo-dropzone-content">
                                            <i class="ti ti-cloud-upload logo-dropzone-icon"></i>
                                            <div><strong>Drag and drop staff photo here</strong></div>
                                            <div class="text-secondary">or click, paste, or upload a file</div>
                                        </div>
                                        <div class="form-hint user-photo-dropzone-hint">JPG, PNG, or WEBP. Maximum
                                            size: 2 MB.</div>
                                        <input type="file" class="d-none" name="photo" id="staff_photo"
                                            accept="image/jpeg,image/png,image/webp">
                                    </div>
                                    <div class="d-none staff-photo-preview-wrap" id="staffPhotoPreviewContainer"><img
                                            id="staffPhotoPreview" src="#" alt="Staff photo preview"
                                            data-initial-photo="{{ $editUser?->photo_path ? asset('storage/' . $editUser->photo_path) : '' }}"
                                            class="student-photo-preview"></div>
                                </div>
                            </div>
                            <div class="col-md-3"><label class="form-label">Staff ID</label><input class="form-control"
                                    name="staff_id" value="{{ old('staff_id', $editUser?->staff_id) }}" required></div>
                            <div class="col-md-3"><label class="form-label">Staff Name</label><input class="form-control"
                                    name="name" value="{{ old('name', $editUser?->name) }}" required></div>
                            <div class="col-md-3"><label class="form-label">Username</label><input class="form-control"
                                    name="username" value="{{ old('username', $editUser?->username) }}" required></div>
                            <div class="col-md-3"><label class="form-label">Email</label><input class="form-control"
                                    type="email" name="email" value="{{ old('email', $editUser?->email) }}" required>
                            </div>
                            <div class="col-md-3"><label class="form-label">Password</label><input class="form-control"
                                    type="password" name="password" minlength="8"><small class="text-body-secondary fw-semibold d-block mt-1">Leave blank to use the default password.</small></div>
                            <div class="col-md-3"><label class="form-label">Confirm Password</label><input
                                    class="form-control" type="password" name="password_confirmation" minlength="8">
                            </div>
                            <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select"
                                    name="gender">
                                    <option value=""></option>
                                    <option value="Male" @selected(strtolower((string) old('gender', $editUser?->gender)) === 'male')>Male</option>
                                    <option value="Female" @selected(strtolower((string) old('gender', $editUser?->gender)) === 'female')>Female</option>
                                    <option value="Other" @selected(strtolower((string) old('gender', $editUser?->gender)) === 'other')>Other</option>
                                </select></div>
                            <div class="col-md-3"><label class="form-label">Date of Birth</label><input
                                    class="form-control" type="date" name="date_of_birth"
                                    value="{{ old('date_of_birth', $editUser?->date_of_birth?->format('Y-m-d')) }}"></div>
                            <div class="col-md-3 user-phone-field" style="position:relative;"><label class="form-label" style="position:absolute;z-index:5;top:.42rem;left:1rem;margin:0;padding:0 .45rem;background:var(--tblr-bg-surface,#fff);color:#5b4bd1;font-size:.72rem;font-weight:700;line-height:1.1;pointer-events:none;">Phone Number</label>
                                <div class="phone-input-group"><input class="form-control" type="tel"
                                        id="user_phone_number" placeholder=" "
                                        value="{{ old('phone', $editUser?->phone) }}"><input type="hidden"
                                        name="phone" id="user_phone" value="{{ old('phone', $editUser?->phone) }}">
                                </div>
                            </div>
                            <div class="col-md-3"><label class="form-label">Position</label><select class="form-select"
                                    name="position_id">
                                    <option value=""></option>
                                    @foreach ($positions as $position)
                                        <option value="{{ $position->id }}"
                                            data-department-id="{{ $position->department_id }}"
                                            @selected(old('position_id', $editUser?->position_id) == $position->id)>{{ $position->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Department</label><select class="form-select"
                                    name="department_id">
                                    <option value=""></option>
                                    @foreach ($departments as $d)
                                        <option value="{{ $d->id }}" @selected(old('department_id', $editUser?->department_id) == $d->id)>
                                            {{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Role</label><select class="form-select"
                                    name="role_id" required>
                                    <option value=""></option>
                                    @foreach ($roles as $r)
                                        <option value="{{ $r->id }}" @selected(old('role_id', $editUser?->roles->first()?->id) == $r->id)>
                                            {{ $r->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label">Campus Assignments</label><select
                                    class="form-select" name="campuses[]" multiple size="4">
                                    @foreach ($campuses as $c)
                                        <option value="{{ $c->id }}" @selected($editUser?->campuses->contains($c->id))>
                                            {{ $c->campus_name_en }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label">Allowed Login Method</label><select
                                    class="form-select" name="login_identifier">
                                    <option value="username" @selected(old('login_identifier', $editUser?->login_identifier ?? 'username') === 'username')>Username only</option>
                                    <option value="email" @selected(old('login_identifier', $editUser?->login_identifier) === 'email')>Email only</option>
                                    <option value="both" @selected(old('login_identifier', $editUser?->login_identifier) === 'both')>Username or Email</option>
                                </select></div>
                            <div class="col-md-4"><label class="form-label">Account Status</label><select
                                    class="form-select" name="status">
                                    <option value="1" @selected(old('status', $editUser?->status ?? 1) == 1)>Active</option>
                                    <option value="0" @selected(old('status', $editUser?->status) == 0)>Inactive</option>
                                </select><label class="form-check mt-2"><input class="form-check-input" type="checkbox"
                                        name="is_global" value="1" @checked(old('is_global', $editUser?->is_global))><span
                                        class="form-check-label">Central Office / global access</span></label></div>
                        </div>
                        <details class="mt-4">
                            <summary>Staff-specific permissions</summary>
                            <div class="row g-2 mt-2">
                                @foreach ($permissions as $module => $items)
                                    <div class="col-md-3">
                                        <div class="text-uppercase small text-secondary">{{ $module }}</div>
                                        @foreach ($items as $permission)
                                            <label class="form-check"><input class="form-check-input" type="checkbox"
                                                    name="permission_ids[]" value="{{ $permission->id }}"
                                                    @checked($editUser?->permissionOverrides->contains($permission->id))><span
                                                    class="form-check-label">{{ $permission->name }}</span></label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </div>
                    <div class="modal-footer"><a class="btn me-auto" href="{{ route('users.index') }}">Cancel</a><button
                            class="btn btn-primary">{{ $editUser ? 'Update User' : 'Create User' }}</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="staffPhotoCropModal" tabindex="-1" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Crop Staff Photo</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">Adjust the photo inside the square frame. The final image will be
                        exactly 400 x 400 px.</p>
                    <div class="staff-photo-crop-stage">
                        <canvas id="staffPhotoCropCanvas" width="400" height="400"></canvas>
                    </div>
                    <div class="row g-2 align-items-center mt-3">
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary" id="staffPhotoZoomOut"><i
                                    class="ti ti-zoom-out"></i></button>
                        </div>
                        <div class="col">
                            <input type="range" class="form-range" id="staffPhotoZoom" min="1" max="3"
                                step="0.01" value="1" aria-label="Zoom photo">
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary" id="staffPhotoZoomIn"><i
                                    class="ti ti-zoom-in"></i></button>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary" id="staffPhotoRotateLeft"><i
                                    class="ti ti-rotate-2"></i> Left</button>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary" id="staffPhotoRotateRight"><i
                                    class="ti ti-rotate-clockwise-2"></i> Right</button>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-secondary" id="staffPhotoReset">Reset</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="staffPhotoCropUpload"><i
                            class="ti ti-crop me-1"></i>Crop and Upload</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal modal-blur fade" id="staffPhotoViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered staff-photo-view-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="staffPhotoViewTitle">Staff Photo</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center overflow-hidden">
                    <img id="staffPhotoViewImage" src="#" alt="Staff photo">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" id="staffPhotoViewZoomOut"><i
                            class="ti ti-zoom-out"></i></button>
                    <input type="range" id="staffPhotoViewZoom" min="1" max="3" step=".05"
                        value="1" style="width:180px" aria-label="Zoom staff photo">
                    <button type="button" class="btn btn-outline-secondary" id="staffPhotoViewZoomIn"><i
                            class="ti ti-zoom-in"></i></button>
                    <button type="button" class="btn btn-outline-secondary" id="staffPhotoViewZoomReset">Reset</button>
                    <a class="btn btn-primary" id="staffPhotoViewDownload" href="#" download>
                        <i class="ti ti-download"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @php
        $sortUrl = function (string $field) {
            $currentSort = request('sortBy', 'name');
            $currentDir = request('sortDir', 'asc');
            $nextDir = $currentSort === $field && $currentDir === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery([
                'sortBy' => $field,
                'sortDir' => $nextDir,
                'page' => 1,
            ]);
        };
        $sortIcon = function (string $field) {
            if (request('sortBy', 'name') !== $field) {
                return '<span class="table-sort-icon" aria-hidden="true">↕</span>';
            }

            return request('sortDir', 'asc') === 'asc'
                ? '<span class="table-sort-icon" aria-hidden="true">↑</span>'
                : '<span class="table-sort-icon" aria-hidden="true">↓</span>';
        };
        $genderIcon = function ($gender) {
            $gender = strtolower((string) $gender);

            return match ($gender) {
                'male' => ['ti-gender-male', 'Male'],
                'female' => ['ti-gender-female', 'Female'],
                default => ['ti-gender-bigender', $gender ? ucfirst($gender) : 'Gender'],
            };
        };
    @endphp
    <div class="card user-management-list-card">
        <div class="card-header">
            <h3 class="card-title">User Lists</h3>
        </div>

        <div class="user-management-mobile-list-wrap d-md-none">
            <div class="user-management-mobile-scroll-hint user-management-mobile-scroll-hint-left"
                aria-hidden="true">
                <i class="ti ti-chevron-left"></i>
            </div>
            <div class="user-management-mobile-scroll-hint user-management-mobile-scroll-hint-right"
                aria-hidden="true">
                <i class="ti ti-chevron-right"></i>
            </div>
            <div class="user-management-mobile-scroll-position" aria-live="polite">
                {{ $users->count() ? 1 : 0 }} of {{ $users->count() }}
            </div>
            <div class="user-management-mobile-list">
            @forelse($users as $user)
                <article class="user-management-mobile-card">
                    <div class="user-management-mobile-head">
                        <div class="user-management-mobile-photo">
                            @if ($user->photo_path)
                                <button type="button" class="btn p-0 border-0 staff-photo-view-trigger"
                                    data-photo-url="{{ asset('storage/' . $user->photo_path) }}"
                                    data-photo-title="{{ $user->name ?: 'Staff Photo' }}">
                                    <img src="{{ asset('storage/' . $user->photo_path) }}"
                                        alt="{{ $user->name ?: 'Staff Photo' }}">
                                </button>
                            @else
                                <span class="avatar avatar-md bg-secondary-lt"><i class="ti ti-user"></i></span>
                            @endif
                        </div>
                        <div class="user-management-mobile-title">
                            <div class="user-management-mobile-name">{{ $user->name }}</div>
                            <div class="user-management-mobile-subtitle">Staff ID: {{ $user->staff_id ?: '' }}</div>
                            <div class="user-management-mobile-subtitle">{{ $user->username }}</div>
                        </div>
                        <button type="button"
                            class="status-toggle {{ $user->status ? 'is-active' : '' }}"
                            data-status-toggle data-status-entity="user"
                            data-status-id="{{ $user->id }}"
                            data-status="{{ $user->status ? 1 : 0 }}"
                            aria-pressed="{{ $user->status ? 'true' : 'false' }}"><span
                                class="status-toggle-label">{{ $user->status ? 'ON' : 'OFF' }}</span><span
                                class="status-toggle-knob"></span></button>
                    </div>
                    <div class="user-management-mobile-grid">
                        <div>
                            <span>Phone</span>
                            <strong>{{ $formatCambodiaPhone($user->phone) }}</strong>
                        </div>
                        <div>
                            <span>Role</span>
                            <strong>{{ $user->roles->pluck('name')->unique()->join(', ') ?: '' }}</strong>
                        </div>
                        <div>
                            <span>Position</span>
                            <strong>{{ $user->position?->name ?: '' }}</strong>
                        </div>
                        <div>
                            <span>Campus</span>
                            <strong>{{ $user->is_global ? 'All Campuses' : ($user->campuses->pluck('campus_name_en')->filter()->join(', ') ?: '') }}</strong>
                        </div>
                    </div>
                    <div class="user-management-mobile-extra">
                        <div><span>Department</span><strong>{{ $user->department?->name ?: '' }}</strong></div>
                        <div><span>Login</span><strong>{{ $user->login_identifier === 'both' ? 'Username / Email' : ucfirst($user->login_identifier) }}</strong></div>
                    </div>
                    <div class="user-management-mobile-actions">
                        <a class="btn btn-outline-primary btn-sm" href="{{ route('users.index', ['edit' => $user->id]) }}"
                            aria-label="Edit user">
                            <i class="ti ti-edit"></i><span class="visually-hidden">Edit</span>
                        </a>
                        <form method="POST" action="{{ route('users.delete', $user) }}" onsubmit="return confirm('Delete this user?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Delete user">
                                <i class="ti ti-trash"></i><span class="visually-hidden">Delete</span>
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="text-center text-secondary py-4">No users found.</div>
            @endforelse
            </div>
        </div>

        <div class="table-responsive table-vcenter text-nowrap d-none d-md-block">
            <table class="table card-table" data-staff-photo-column="1" data-staff-details-columns="1">
                <thead>
                    <tr>
                        <th>NO.</th>
                        <th>PHOTO</th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('staff_id') }}">STAFF ID {!! $sortIcon('staff_id') !!}</a></th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('name') }}">STAFF FULL NAME {!! $sortIcon('name') !!}</a></th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('username') }}">USER LOGIN {!! $sortIcon('username') !!}</a></th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('position') }}">POSITION {!! $sortIcon('position') !!}</a></th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('campus') }}">CAMPUS ASSIGNMENT {!! $sortIcon('campus') !!}</a></th>
                        <th><a class="table-sort-button text-uppercase" href="{{ $sortUrl('status') }}">STATUS {!! $sortIcon('status') !!}</a></th>
                        <th class="text-center">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php
                            [$userGenderIcon, $userGenderLabel] = $genderIcon($user->gender);
                        @endphp
                        <tr>
                            <td>{{ $users->firstItem() + $loop->index }}</td>
                            <td>
                                @if ($user->photo_path)
                                    <button type="button" class="btn p-0 border-0 staff-photo-view-trigger"
                                        data-photo-url="{{ asset('storage/' . $user->photo_path) }}"
                                        data-photo-title="{{ $user->name ?: 'Staff Photo' }}">
                                        <img src="{{ asset('storage/' . $user->photo_path) }}"
                                            alt="{{ $user->name ?: 'Staff Photo' }}"
                                            class="user-management-staff-photo-thumb" width="58" height="58" style="width:58px;height:58px;object-fit:cover;">
                                    </button>
                                @else
                                    <span class="avatar bg-secondary-lt user-management-staff-photo-placeholder"><i class="ti ti-user"></i></span>
                                @endif
                            </td>
                            <td>{{ $user->staff_id ?: '' }}</td>
                            <td>{{ $user->name }}
                                <div class="text-secondary small user-list-meta-row" title="Gender"><i class="ti {{ $userGenderIcon }}"></i><span>{{ $userGenderLabel }}</span></div>
                                <div class="text-secondary small user-list-meta-row" title="Date of Birth"><i class="ti ti-cake"></i><span>{{ $user->date_of_birth?->format('d-M-Y') ?: '' }}</span></div>
                                <div class="text-secondary small user-list-meta-row" title="Phone"><i class="ti ti-phone"></i><span>{{ $formatCambodiaPhone($user->phone) }}</span></div>
                            </td>
                            <td>{{ $user->username }}
                                <div class="text-secondary small user-list-meta-row" title="Email"><i class="ti ti-mail"></i><span>{{ $user->email }}</span></div>
                                <div class="text-secondary small user-list-meta-row" title="Login"><i class="ti ti-login"></i><span>{{ $user->login_identifier === 'both' ? 'Username / Email' : ucfirst($user->login_identifier) }}</span></div>
                                <div class="text-secondary small user-list-meta-row" title="Role"><i class="ti ti-shield-check"></i><span>{{ $user->roles->pluck('name')->unique()->join(', ') ?: '' }}</span></div>
                            </td>
                            <td>{{ $user->position?->name ?: '' }}
                                <div class="text-secondary small">Dept: {{ $user->department?->name ?: '' }}</div>
                            </td>
                            <td>{{ $user->is_global ? 'All Campuses' : ($user->campuses->pluck('campus_name_en')->filter()->join(', ') ?: '') }}</td>
                            <td><button type="button"
                                    class="status-toggle {{ $user->status ? 'is-active' : '' }}"
                                    data-status-toggle data-status-entity="user"
                                    data-status-id="{{ $user->id }}"
                                    data-status="{{ $user->status ? 1 : 0 }}"
                                    aria-pressed="{{ $user->status ? 'true' : 'false' }}"><span
                                        class="status-toggle-label">{{ $user->status ? 'ON' : 'OFF' }}</span><span
                                        class="status-toggle-knob"></span></button></td>
                            <td class="text-center"><a class="btn btn-sm btn-outline-primary" data-edit="{{ $user->id }}"
                                    href="{{ route('users.index', ['edit' => $user->id]) }}"><i
                                        class="ti ti-edit"></i></a>
                                <form class="d-inline" method="POST" action="{{ route('users.delete', $user) }}"
                                    onsubmit="return confirm('Delete this user?')">@csrf @method('DELETE')<button
                                        class="btn btn-sm btn-outline-danger" data-delete="{{ $user->id }}"><i
                                            class="ti ti-trash"></i></button></form>
                            </td>
                    </tr>@empty<tr>
                            <td colspan="9" class="text-center text-secondary py-4">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-center">@include('partials.user-pagination')</div>
        </div>
    </div>
    @vite('resources/js/userManagement.js')
    @vite('resources/css/pages/user-management.css')
@endsection
