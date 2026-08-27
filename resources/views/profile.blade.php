@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
    @php
        $profileUser = $profileUser ?? auth()->user();
        $profileLogoPath = $branding->report_logo_1_path ?? $branding->login_logo_path ?? $branding->sidebar_logo_path ?? null;
        $cardBackground = preg_match('/^#[0-9A-Fa-f]{6}$/', $profileUser->public_card_background ?? '')
            ? $profileUser->public_card_background
            : '#206bc4';
        $cardRgb = sscanf(ltrim($cardBackground, '#'), '%02x%02x%02x') ?: [32, 107, 196];
        $isLightCard = (($cardRgb[0] * 299) + ($cardRgb[1] * 587) + ($cardRgb[2] * 114)) > 180000;
        $shareTitle = trim(($profileUser->name ?: 'Staff Name Card') . ' - Western International School');
        $shareMessage = $shareTitle . ' ' . $publicCardUrl;
    @endphp
    <style>
        .profile-workspace{display:grid;grid-template-columns:250px minmax(0,1fr);gap:1rem;align-items:start}
        .profile-workspace-nav{position:sticky;top:1rem;display:flex;flex-direction:column;gap:.5rem;padding:.9rem;border:1px solid rgba(132,158,190,.25);border-radius:24px;background:radial-gradient(circle at 0 0,rgba(32,107,196,.1),transparent 45%),linear-gradient(180deg,#fff,#f7faff);box-shadow:0 18px 46px rgba(30,41,59,.1)}
        .profile-workspace-nav-heading{display:flex;align-items:center;gap:.7rem;padding:.65rem .55rem .9rem;margin-bottom:.35rem;border-bottom:1px solid #e5edf6}.profile-workspace-nav-heading>.avatar{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;padding:0;line-height:1}.profile-workspace-nav-heading>.avatar .ti{display:block;line-height:1}
        .profile-workspace-nav-heading strong,.profile-workspace-nav-heading span{display:block}
        .profile-workspace-nav-heading strong{max-width:165px;overflow:hidden;color:#203a5f;text-overflow:ellipsis;white-space:nowrap}
        .profile-workspace-nav-heading span:last-child{margin-top:.15rem;color:#7b8da5;font-size:.78rem}
        .profile-workspace-tabs{display:flex;flex-direction:column;gap:.5rem}
        .profile-workspace-link{display:flex;align-items:center;gap:.75rem;width:100%;min-height:68px;padding:.7rem;border:1px solid transparent;border-radius:14px;background:transparent;color:#60758f;font:inherit;font-weight:700;text-align:left;cursor:pointer;transition:.18s ease}
        .profile-workspace-link:hover{background:#edf5ff;color:#206bc4}.profile-workspace-link>.ti-chevron-right{color:#8da0b8;font-size:1rem;transition:transform .18s ease,color .18s ease}.profile-workspace-link:hover>.ti-chevron-right,.profile-workspace-link.is-active>.ti-chevron-right{color:#206bc4;transform:translateX(2px)}
        .profile-workspace-link.is-active{border-color:rgba(32,107,196,.16);background:linear-gradient(135deg,#e8f2ff,#f4f9ff);color:#206bc4;box-shadow:0 10px 24px rgba(32,107,196,.12)}
        .profile-workspace-link-icon{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;border-radius:14px;background:#e6f2ff;color:#206bc4;font-size:1.25rem}
        .profile-workspace-link-icon.is-purple{background:#f0eaff;color:#8b5cf6}
        .profile-workspace-link-copy{display:grid;min-width:0;gap:.1rem;text-align:left}
        .profile-workspace-link-copy strong{color:currentColor;font-size:.92rem}
        .profile-workspace-link-copy small{overflow:hidden;color:#8494aa;font-size:.72rem;font-weight:500;text-overflow:ellipsis;white-space:nowrap}
        .profile-workspace-content{min-width:0}.profile-workspace-panel[hidden]{display:none!important}.profile-workspace-panel:not([hidden]){animation:profile-panel-in .22s ease both}
        .profile-panel-card-header{display:flex;align-items:center;justify-content:space-between;gap:1rem}.profile-panel-header-icon{color:#206bc4;font-size:1.6rem}
        .profile-card-orientation-form{display:grid;gap:.35rem}.profile-card-orientation-form label{color:#60758f;font-size:.75rem;font-weight:700}.profile-qr-box{width:100%;min-height:300px;padding:1.5rem}.profile-qr-box img{width:min(300px,100%);height:auto}.profile-public-card-preview{height:auto!important;width:min(100%,520px);margin-inline:auto;min-height:0!important;overflow:hidden;padding:1rem 2rem 2rem!important;background:linear-gradient(145deg,color-mix(in srgb,var(--profile-card-background,#206bc4) 82%,#102e56),var(--profile-card-background,#206bc4)),radial-gradient(circle at 85% 10%,rgba(255,255,255,.2),transparent 28%)}.profile-public-card-brand{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.15rem;margin-bottom:.25rem;color:#fff;font-size:.85rem;font-weight:800;text-align:center}.profile-public-card-brand img{width:190px;height:137px;object-fit:contain}.profile-public-card-mini-qr{position:static;width:max-content;margin-top:.75rem}.profile-public-card-portrait{aspect-ratio:54/90!important;width:min(100%,320px)}.profile-public-card-portrait .profile-public-card-top{flex-direction:column;align-items:flex-start}.profile-public-card-portrait .profile-public-card-photo{width:90px;height:112px;flex-basis:112px}.profile-public-card-light{color:#203a5f!important}.profile-public-card-light .profile-public-card-brand,.profile-public-card-light .profile-public-card-info p{color:rgba(32,58,95,.82)}.profile-public-card-light .profile-public-card-tags span,.profile-public-card-light .profile-public-card-details div{background:rgba(32,58,95,.1);color:#203a5f}
        .profile-public-card-preview{padding-top:4px!important}.profile-public-card-brand img{transform:translateY(-32px);margin-bottom:-32px}.profile-public-card-top{display:grid;grid-template-columns:30% minmax(0,1fr);gap:1.25rem}.profile-public-card-photo{width:100%;height:auto;aspect-ratio:3/4;grid-column:1;grid-row:1}.profile-public-card-info{grid-column:2;grid-row:1}.profile-public-card-info h3{font-size:1.3rem!important}.profile-public-card-portrait .profile-public-card-top{grid-template-columns:30% minmax(0,1fr)}
        .profile-public-card-info p{font-size:.85rem!important}.profile-public-card-info h3{text-transform:uppercase}.profile-public-card-tags{gap:3px!important;row-gap:3px!important}.profile-public-card-tags span{justify-content:flex-start;padding:0!important;margin:0!important;line-height:1!important;background:transparent!important;color:#206bc4}.profile-public-card-mini-qr{position:static!important;grid-column:1 / -1;grid-row:2;display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;margin-top:2rem}.profile-public-card-mini-qr img{width:150px!important;height:150px!important;object-fit:contain}
        @keyframes profile-panel-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:767.98px){.profile-workspace{grid-template-columns:1fr}.profile-workspace-nav{position:static}.profile-workspace-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.profile-workspace-link{min-height:62px;padding-inline:.55rem}.profile-workspace-link .ti-chevron-right{display:none}}
    </style>
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h2>My Profile</h2>
                <div class="text-secondary">Manage your account and sign-in credentials.</div>
            </div>
        </div>
    </div>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('warning') || auth()->user()->must_change_password)
        <div class="alert alert-warning">{{ session('warning', 'Please change your temporary password before continuing.') }}
        </div>
    @endif
    <div class="profile-workspace">
        <aside class="profile-workspace-nav" aria-label="Profile sections">
            <div class="profile-workspace-nav-heading">
                <span class="avatar avatar-md bg-blue-lt"><i class="ti ti-user"></i></span>
                <div>
                    <strong>{{ $profileUser->name }}</strong>
                    <span>Personal workspace</span>
                </div>
            </div>
            <div class="profile-workspace-tabs" role="tablist" aria-label="Profile sections">
            <button class="profile-workspace-link is-active" type="button" role="tab" aria-selected="true"
                aria-controls="profile-panel-profile-view" data-profile-panel="profile-view">
                <span class="profile-workspace-link-icon"><i class="ti ti-user-circle"></i></span>
                <span class="profile-workspace-link-copy">
                    <strong>My Profile</strong>
                    <small>View personal information</small>
                </span>
                <i class="ti ti-chevron-right ms-auto"></i>
            </button>
            <button class="profile-workspace-link" type="button" role="tab" aria-selected="false"
                aria-controls="profile-panel-profile-edit" data-profile-panel="profile-edit">
                <span class="profile-workspace-link-icon"><i class="ti ti-edit"></i></span>
                <span class="profile-workspace-link-copy">
                    <strong>Edit Profile</strong>
                    <small>Update account details</small>
                </span>
                <i class="ti ti-chevron-right ms-auto"></i>
            </button>
            <button class="profile-workspace-link" type="button" role="tab" aria-selected="false"
                aria-controls="profile-panel-name-card" data-profile-panel="name-card">
                <span class="profile-workspace-link-icon"><i class="ti ti-id-badge-2"></i></span>
                <span class="profile-workspace-link-copy">
                    <strong>My Name Card</strong>
                    <small>QR code and sharing</small>
                </span>
                <i class="ti ti-chevron-right ms-auto"></i>
            </button>
            <button class="profile-workspace-link" type="button" role="tab" aria-selected="false"
                aria-controls="profile-panel-preferences" data-profile-panel="preferences">
                <span class="profile-workspace-link-icon is-green"><i class="ti ti-adjustments-horizontal"></i></span>
                <span class="profile-workspace-link-copy">
                    <strong>Preferences</strong>
                    <small>Theme and appearance</small>
                </span>
                <i class="ti ti-chevron-right ms-auto"></i>
            </button>
            </div>
        </aside>

        <main class="profile-workspace-content">
            <section id="profile-panel-name-card" class="profile-workspace-panel" role="tabpanel"
                data-profile-panel-content="name-card" hidden>
    <div class="card profile-name-card mb-3">
        <div class="card-header profile-name-card-header">
            <div>
                <div class="text-uppercase text-secondary fw-bold small">Public Name Card</div>
                <h3 class="card-title mb-0">My Name Card with QR</h3>
                <div class="text-secondary">Share your professional contact card. No username or password is shown.</div>
            </div>
            <span class="badge {{ $profileUser->public_card_enabled ? 'bg-success-lt' : 'bg-danger-lt' }}">
                {{ $profileUser->public_card_enabled ? 'Public Active' : 'Public Disabled' }}
            </span>
        </div>
        <div class="card-body">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-5">
                    <div class="profile-public-card-preview profile-public-card-{{ $profileUser->public_card_orientation ?: 'landscape' }} {{ $isLightCard ? 'profile-public-card-light' : '' }}"
                        style="--profile-card-background: {{ $cardBackground }}; background: linear-gradient(145deg, {{ $cardBackground }}, {{ $cardBackground }}) !important">
                        <div class="profile-public-card-brand">
                            @if ($profileLogoPath)
                                <img src="{{ asset('storage/' . $profileLogoPath) }}" alt="School Logo 1">
                            @endif
                        </div>
                        <div class="profile-public-card-top" style="display: grid; grid-template-columns: 30% minmax(0, 1fr);">
                            <div class="profile-public-card-photo" style="grid-column: 1; grid-row: 1; width: 100%; height: auto; aspect-ratio: 3 / 4;">
                                @if ($profileUser->photo_path)
                                    <img src="{{ asset('storage/' . $profileUser->photo_path) }}" alt="{{ $profileUser->name }}">
                                @else
                                    <span>{{ mb_substr($profileUser->name ?: 'S', 0, 1) }}</span>
                                @endif
                            </div>
                            <div class="profile-public-card-info" style="grid-column: 2; grid-row: 1;">
                                <h3>{{ $profileUser->name }}</h3>
                                <p>{{ $profileUser->position?->name ?: 'Staff / Teacher' }}</p>
                                <div class="profile-public-card-tags"><span><i class="ti ti-phone"></i>{{ $profileUser->phone ?: '—' }}</span><span><i class="ti ti-mail"></i>{{ $profileUser->email ?: '—' }}</span></div>
                            </div>
                        </div>
                        <div class="profile-public-card-mini-qr">
                            <img src="{{ $publicCardQrUrl }}" alt="Name card QR">
                            <span>Scan</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="profile-qr-panel">
                        <div class="profile-qr-box">
                            <img src="{{ $publicCardQrUrl }}" alt="Public name card QR code">
                        </div>
                        <div class="profile-qr-meta">
                            <div>
                                <strong>{{ number_format((int) $profileUser->public_card_scan_count) }}</strong>
                                <span>Scans</span>
                            </div>
                            <div>
                                <strong>{{ $profileUser->public_card_last_viewed_at?->format('d-M-Y') ?: '—' }}</strong>
                                <span>Last viewed</span>
                            </div>
                        </div>
                        <div class="profile-card-link-box">
                            <input class="form-control" value="{{ $publicCardUrl }}" readonly id="publicCardUrl">
                            <button class="btn btn-outline-primary" type="button" id="copyPublicCardUrl">
                                <i class="ti ti-copy"></i>
                            </button>
                        </div>
                        <div class="profile-share-actions">
                            <a class="btn btn-outline-primary" href="{{ $publicCardUrl }}" target="_blank" rel="noopener">
                                <i class="ti ti-eye"></i> View
                            </a>
                            <a class="btn btn-outline-success" href="{{ $publicCardVcardUrl }}">
                                <i class="ti ti-address-book"></i> Save Contact
                            </a>
                            <a class="btn btn-outline-secondary profile-name-card-print-button" href="{{ $publicCardUrl }}?print=1"
                                title="Print name card" aria-label="Print name card"
                                onclick="const printWindow = window.open(this.href, 'staff-card-print', 'popup,width=900,height=900'); if (printWindow) { printWindow.focus(); return false; }">
                                <i class="ti ti-printer"></i>
                            </a>
                            <a class="btn btn-outline-info" target="_blank" rel="noopener"
                                href="https://t.me/share/url?url={{ urlencode($publicCardUrl) }}&text={{ urlencode($shareTitle) }}">
                                <i class="ti ti-brand-telegram"></i>
                            </a>
                            <a class="btn btn-outline-success" target="_blank" rel="noopener"
                                href="https://wa.me/?text={{ urlencode($shareMessage) }}">
                                <i class="ti ti-brand-whatsapp"></i>
                            </a>
                            <a class="btn btn-outline-primary" target="_blank" rel="noopener"
                                href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($publicCardUrl) }}">
                                <i class="ti ti-brand-facebook"></i>
                            </a>
                        </div>
                        <div class="profile-card-settings">
                            <form method="POST" action="{{ route('profile.name-card.update') }}" class="profile-card-orientation-form">
                                @csrf
                                @method('PATCH')
                                <label for="publicCardOrientation">Card orientation</label>
                                <select class="form-select" id="publicCardOrientation" name="public_card_orientation" onchange="this.form.submit()">
                                    <option value="landscape" @selected(($profileUser->public_card_orientation ?: 'landscape') === 'landscape')>Landscape</option>
                                    <option value="portrait" @selected(($profileUser->public_card_orientation ?: 'landscape') === 'portrait')>Portrait</option>
                                </select>
                                <label for="publicCardBackground">Background color</label>
                                <input class="form-control form-control-color" type="color" id="publicCardBackground"
                                    name="public_card_background" value="{{ $profileUser->public_card_background ?: '#206bc4' }}"
                                    title="Choose name-card background color" onchange="this.form.submit()">
                            </form>
                            <form method="POST" action="{{ route('profile.name-card.update') }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="public_card_enabled" value="0">
                                <label class="profile-card-toggle">
                                    <input type="checkbox" name="public_card_enabled" value="1"
                                        @checked($profileUser->public_card_enabled) onchange="this.form.submit()">
                                    <span></span>
                                    <strong>Allow public QR card</strong>
                                </label>
                            </form>
                            <form method="POST" action="{{ route('profile.name-card.regenerate') }}"
                                id="regenerateNameCardForm">
                                @csrf
                                <button class="btn btn-outline-danger w-100" type="submit">
                                    <i class="ti ti-refresh"></i> Regenerate QR
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
            </section>

            <section id="profile-panel-preferences" class="profile-workspace-panel" role="tabpanel"
                data-profile-panel-content="preferences" hidden>
                <div class="card profile-preferences-card">
                    <div class="card-header profile-panel-card-header">
                        <div>
                            <div class="text-uppercase text-secondary fw-bold small">Personal Preferences</div>
                            <h3 class="card-title mb-0">Theme Settings</h3>
                            <div class="text-secondary">Customize the appearance for your account.</div>
                        </div>
                        <i class="ti ti-palette profile-panel-header-icon"></i>
                    </div>
                    <div class="card-body profile-theme-settings">
                        @include('layouts.partials.setting', ['embedded' => true])
                    </div>
                </div>
            </section>

            <section id="profile-panel-profile-view" class="profile-workspace-panel" role="tabpanel"
                data-profile-panel-content="profile-view" hidden>
                <div class="card profile-readonly-card">
                    <div class="card-header profile-panel-card-header">
                        <div>
                            <div class="text-uppercase text-secondary fw-bold small">Staff Profile</div>
                            <h3 class="card-title mb-0">My Profile</h3>
                            <div class="text-secondary">Personal information</div>
                        </div>
                        <i class="ti ti-eye profile-panel-header-icon"></i>
                    </div>
                    <div class="card-body">
                        <div class="profile-readonly-hero">
                            <div class="profile-readonly-avatar">
                                @if ($profileUser->photo_path)
                                    <img src="{{ asset('storage/' . $profileUser->photo_path) }}" alt="{{ $profileUser->name }}">
                                @else
                                    <span>{{ mb_substr($profileUser->name ?: 'S', 0, 1) }}</span>
                                @endif
                            </div>
                            <div>
                                <h4>{{ $profileUser->name ?: '—' }}</h4>
                                <p>{{ $profileUser->position?->name ?: 'Staff / Teacher' }}</p>
                            </div>
                        </div>
                        <div class="profile-readonly-grid">
                            <div><span>Staff Name</span><strong>{{ $profileUser->name ?: '—' }}</strong></div>
                            <div><span>Username</span><strong>{{ $profileUser->username ?: '—' }}</strong></div>
                            <div><span>Email</span><strong>{{ $profileUser->email ?: '—' }}</strong></div>
                            <div><span>Phone</span><strong>{{ $profileUser->phone ?: '—' }}</strong></div>
                            <div><span>Gender</span><strong>{{ $profileUser->gender ?: '—' }}</strong></div>
                            <div><span>Date of Birth</span><strong>{{ $profileUser->date_of_birth?->format('d-M-Y') ?: '—' }}</strong></div>
                            <div><span>Position</span><strong>{{ $profileUser->position?->name ?: '—' }}</strong></div>
                            <div><span>Department</span><strong>{{ $profileUser->department?->name ?: '—' }}</strong></div>
                            <div class="profile-readonly-wide"><span>Assigned Campuses</span><strong>{{ $profileUser->campuses->pluck('campus_name_en')->filter()->join(', ') ?: '—' }}</strong></div>
                            <div class="profile-readonly-wide"><span>Roles</span><strong>{{ $profileUser->roles->pluck('name')->filter()->join(', ') ?: '—' }}</strong></div>
                            <div><span>Account Status</span><strong>{{ $profileUser->status ? 'Active' : 'Inactive' }}</strong></div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="profile-panel-profile-edit" class="profile-workspace-panel" role="tabpanel"
                data-profile-panel-content="profile-edit" hidden>
    <div class="card">
        <div class="card-header profile-panel-card-header">
            <div>
                <div class="text-uppercase text-secondary fw-bold small">Account Settings</div>
                <h3 class="card-title mb-0">Edit Profile</h3>
                <div class="text-secondary">Update your account details</div>
            </div>
            <i class="ti ti-user-circle profile-panel-header-icon"></i>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">@csrf
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Profile Photo</label>
                        <div class="logo-dropzone" id="profilePhotoDropzone" tabindex="0"><i
                                class="ti ti-cloud-upload logo-dropzone-icon"></i>
                            <div><strong>Drag and drop profile photo here</strong></div>
                            <div class="text-secondary">or click, paste, or upload a file</div>
                            <div id="profilePhotoPreview" class="mt-3 @if (!auth()->user()->photo_path) d-none @endif"><img
                                    src="{{ auth()->user()->photo_path ? asset('storage/' . auth()->user()->photo_path) : '#' }}"
                                    alt="Profile photo preview"
                                    style="width:140px;height:140px;object-fit:cover;border-radius:.5rem;border:1px solid var(--tblr-border-color);">
                            </div><input class="d-none" type="file" name="photo" id="profile_photo"
                                accept="image/jpeg,image/png,image/webp">
                        </div><small class="form-hint">JPG, PNG, or WEBP. Maximum size: 2 MB. Crop output: 400 × 400
                            px.</small>
                    </div>
                    <div class="col-md-6"><label class="form-label">Staff Name</label><input class="form-control"
                            name="name" value="{{ old('name', auth()->user()->name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Username</label><input class="form-control"
                            name="username" value="{{ old('username', auth()->user()->username) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email"
                            name="email" value="{{ old('email', auth()->user()->email) }}" required></div>
                    <div class="col-md-6"><label class="form-label">New Password</label><input class="form-control"
                            type="password" name="password" minlength="8"><small class="text-secondary">Leave blank to keep
                            the current password.</small></div>
                    <div class="col-md-6"><label class="form-label">Confirm New Password</label><input class="form-control"
                            type="password" name="password_confirmation" minlength="8"></div>
                </div><button class="btn btn-primary mt-4">Save changes</button>
            </form>
        </div>
    </div>
            </section>

        </main>
    </div>
    <div class="modal modal-blur fade" id="profilePhotoCropModal" tabindex="-1" aria-hidden="true"
        data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Crop Profile Photo</h3><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">Adjust the photo. The final image will be exactly 400 × 400 px.</p>
                    <div class="profile-photo-crop-stage"><canvas id="profilePhotoCropCanvas" width="400"
                            height="400"></canvas></div>
                    <div class="row g-2 align-items-center mt-3">
                        <div class="col-auto"><button type="button" class="btn btn-outline-secondary"
                                id="profilePhotoZoomOut"><i class="ti ti-zoom-out"></i></button></div>
                        <div class="col"><input type="range" class="form-range" id="profilePhotoZoom" min="1"
                                max="3" step=".01" value="1" aria-label="Zoom photo"></div>
                        <div class="col-auto"><button type="button" class="btn btn-outline-secondary"
                                id="profilePhotoZoomIn"><i class="ti ti-zoom-in"></i></button></div>
                        <div class="col-auto"><button type="button" class="btn btn-outline-secondary"
                                id="profilePhotoRotateLeft">Left</button></div>
                        <div class="col-auto"><button type="button" class="btn btn-outline-secondary"
                                id="profilePhotoRotateRight">Right</button></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-link"
                        data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary"
                        id="profilePhotoCropUpload">Crop and Upload</button></div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const profileLinks = document.querySelectorAll('[data-profile-panel]');
            const profilePanels = document.querySelectorAll('[data-profile-panel-content]');
            const activateProfilePanel = panelName => {
                const selectedPanel = [...profilePanels].some(panel => panel.dataset.profilePanelContent === panelName)
                    ? panelName
                    : 'profile-view';

                profileLinks.forEach(link => {
                    const isActive = link.dataset.profilePanel === selectedPanel;
                    link.classList.toggle('is-active', isActive);
                    link.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    link.tabIndex = isActive ? 0 : -1;
                });
                profilePanels.forEach(panel => {
                    panel.hidden = panel.dataset.profilePanelContent !== selectedPanel;
                });
            };

            profileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    const panelName = link.dataset.profilePanel;
                    activateProfilePanel(panelName);
                    window.history.replaceState(null, '', `#${panelName}`);
                });
            });

            activateProfilePanel(window.location.hash.replace('#', ''));

            const regenerateForm = document.getElementById('regenerateNameCardForm');
            regenerateForm?.addEventListener('submit', async event => {
                event.preventDefault();

                const confirmDialog = window.schoolShowConfirm
                    ? await window.schoolShowConfirm(
                        'Generate a new QR code?',
                        'The current QR code and public link will stop working.',
                        'Generate QR',
                        'Cancel',
                    )
                    : { isConfirmed: window.confirm('Generate a new QR code? The current QR code and public link will stop working.') };

                if (confirmDialog.isConfirmed) {
                    regenerateForm.submit();
                }
            });

            document.getElementById('copyPublicCardUrl')?.addEventListener('click', async () => {
                const input = document.getElementById('publicCardUrl');
                if (!input) return;
                input.select();
                input.setSelectionRange(0, input.value.length);
                try {
                    await navigator.clipboard.writeText(input.value);
                } catch (error) {
                    document.execCommand('copy');
                }
            });

            const z = document.getElementById('profilePhotoDropzone'),
                i = document.getElementById('profile_photo'),
                p = document.getElementById('profilePhotoPreview');
            if (!z || !i) return;
            const show = f => {
                if (!f || !f.type.startsWith('image/') || f.size > 2097152) return;
                const d = new DataTransfer();
                d.items.add(f);
                i.files = d.files;
                p.querySelector('img').src = URL.createObjectURL(f);
                p.classList.remove('d-none')
            };
            z.addEventListener('click', () => i.click());
            z.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') i.click()
            });
            i.addEventListener('change', () => show(i.files?.[0]));
            z.addEventListener('dragover', e => {
                e.preventDefault();
                z.classList.add('is-dragging')
            });
            z.addEventListener('dragleave', () => z.classList.remove('is-dragging'));
            z.addEventListener('drop', e => {
                e.preventDefault();
                z.classList.remove('is-dragging');
                show(e.dataTransfer.files?.[0])
            })
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelector('form[action$="/profile"]')?.classList.add('profile-account-form');
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form[action$="/profile"]');
            if (!form || form.dataset.passwordUiReady) return;
            form.dataset.passwordUiReady = '1';
            const fieldsRow = form.querySelector('.row.g-3');
            if (fieldsRow && !fieldsRow.dataset.profileColumnsReady) {
                fieldsRow.dataset.profileColumnsReady = '1';
                const left = document.createElement('div');
                const right = document.createElement('div');
                left.className = 'col-md-6 profile-fields-column profile-fields-left';
                right.className = 'col-md-6 profile-fields-column';
                const moveField = (selector, target) => {
                    const field = fieldsRow.querySelector(selector)?.closest('.col-md-6');
                    if (!field) return;
                    target.appendChild(field);
                };
                moveField('input[name="name"]', left);
                moveField('input[name="username"]', left);
                moveField('input[name="email"]', left);
                moveField('input[name="password"]', right);
                moveField('input[name="password_confirmation"]', right);
                fieldsRow.append(left, right);
            }
            const password = form.querySelector('input[name="password"]');
            const confirmation = form.querySelector('input[name="password_confirmation"]');
            const addToggle = input => {
                if (!input || input.parentElement.classList.contains('premium-password-field')) return;
                const wrapper = document.createElement('div');
                wrapper.className = 'premium-password-field';
                input.parentElement.insertBefore(wrapper, input);
                wrapper.appendChild(input);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'premium-password-toggle';
                button.setAttribute('aria-label', 'Show password');
                button.innerHTML = '<i class="ti ti-eye"></i>';
                button.addEventListener('click', () => {
                    const visible = input.type === 'text';
                    input.type = visible ? 'password' : 'text';
                    button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
                    button.innerHTML = `<i class="ti ${visible ? 'ti-eye' : 'ti-eye-off'}"></i>`;
                });
                wrapper.appendChild(button);
            };
            addToggle(password);
            addToggle(confirmation);
            if (password) {
                const strength = document.createElement('div');
                strength.className = 'profile-password-strength';
                strength.innerHTML =
                    '<div class="profile-password-strength-header"><span>Password Strength</span><span class="profile-password-strength-value">Weak</span></div><div class="profile-password-strength-bar"><span class="profile-password-strength-fill"></span></div><div class="profile-password-rules"><span class="profile-password-rule" data-rule="length">8 Chars</span><span class="profile-password-rule" data-rule="upper">A-Z</span><span class="profile-password-rule" data-rule="lower">a-z</span><span class="profile-password-rule" data-rule="number">123</span><span class="profile-password-rule" data-rule="special">@#$</span></div>';
                password.closest('.premium-password-field')?.after(strength);
                const note = password.closest('.col-md-6')?.querySelector('small.text-secondary');
                const rules = strength.querySelector('.profile-password-rules');
                if (note && rules) {
                    const meta = document.createElement('div');
                    meta.className = 'profile-password-meta';
                    note.classList.add('profile-password-note');
                    meta.append(note, rules);
                    strength.appendChild(meta);
                }
                const updateStrength = () => {
                    const value = password.value;
                    const checks = {
                        length: value.length >= 8,
                        upper: /[A-Z]/.test(value),
                        lower: /[a-z]/.test(value),
                        number: /\d/.test(value),
                        special: /[^A-Za-z0-9]/.test(value)
                    };
                    Object.entries(checks).forEach(([rule, valid]) => strength.querySelector(
                        `[data-rule="${rule}"]`)?.classList.toggle('is-valid', valid));
                    const score = Object.values(checks).filter(Boolean).length;
                    const label = strength.querySelector('.profile-password-strength-value');
                    const fill = strength.querySelector('.profile-password-strength-fill');
                    const level = score >= 4 ? 'strong' : score >= 2 ? 'medium' : '';
                    label.textContent = score >= 4 ? 'Strong' : score >= 2 ? 'Medium' : 'Weak';
                    label.className = `profile-password-strength-value ${level}`;
                    fill.style.width = `${score * 20}%`;
                    fill.className = `profile-password-strength-fill ${level}`;
                };
                password.addEventListener('input', updateStrength);
                updateStrength();
            }
        });
    </script>

    @vite('resources/js/profilePhoto.js')
    @vite('resources/css/pages/profile.css')
@endsection
