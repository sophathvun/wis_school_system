<p class="small text-secondary mt-2">Scholarship Result shows only published awarded students within the user's campus scope. Confidential Result and Import / Export are Central only. Each tab has separate report permissions.</p>
@foreach($module['tabs'] as $tabKey=>$tabGroup)
    @if($tabPermission=$tabGroup['permission'])
        @php
            $tabOn=$assignedPermissions->contains('id',$tabPermission->id);
            $tabId=$permissionPrefix.'-scholarship-tab-'.$tabKey;
        @endphp
        <div class="permission-tab border rounded my-2" data-permission-tab="{{ $tabKey }}">
            <div class="d-flex align-items-center justify-content-between p-2 bg-light permission-action" data-action-parent="{{ $submenuId }}">
                <strong class="small"><i class="ti ti-layout-list me-1" aria-hidden="true"></i>{{ $tabGroup['label'] }}</strong>
                <button type="button" class="status-toggle {{ $tabOn?'is-active':'' }}" data-permission-toggle data-permission-level="action" data-permission-code="{{ $tabPermission->code }}" data-permission-id="{{ $tabId }}" data-parent-permission="{{ $submenuId }}" data-status="{{ $tabOn?1:0 }}" aria-label="View {{ $tabGroup['label'] }} tab" aria-pressed="{{ $tabOn?'true':'false' }}">
                    <input type="checkbox" class="d-none" name="permissions[]" value="{{ $tabPermission->id }}" @checked($tabOn)>
                    <span class="status-toggle-label">{{ $tabOn?'ON':'OFF' }}</span><span class="status-toggle-knob"></span>
                </button>
            </div>
            @foreach($tabGroup['actions'] as $action)
                @php($actionOn=$assignedPermissions->contains('id',$action->id))
                <div class="d-flex align-items-center justify-content-between gap-2 border-top p-2 ps-3 permission-action" data-action-parent="{{ $submenuId }}">
                    <span class="small text-secondary">{{ $action->name }} <small>({{ $action->code }})</small></span>
                    <button type="button" class="status-toggle {{ $actionOn?'is-active':'' }}" data-permission-toggle data-permission-level="action" data-permission-code="{{ $action->code }}" data-requires-permission="{{ $tabPermission->code }}" data-parent-permission="{{ $submenuId }}" data-status="{{ $actionOn?1:0 }}" aria-pressed="{{ $actionOn?'true':'false' }}">
                        <input type="checkbox" class="d-none" name="permissions[]" value="{{ $action->id }}" @checked($actionOn)>
                        <span class="status-toggle-label">{{ $actionOn?'ON':'OFF' }}</span><span class="status-toggle-knob"></span>
                    </button>
                </div>
            @endforeach
        </div>
    @endif
@endforeach
