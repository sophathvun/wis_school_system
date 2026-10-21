@if (!empty($permissionSources[$permissionId]))
    @php
        $sourceLabels = $permissionSources[$permissionId];
        $hasRoleSource = collect($sourceLabels)->contains(fn ($source) => str_starts_with($source, 'Role:'));
        $hasDepartmentSource = collect($sourceLabels)->contains(fn ($source) => str_starts_with($source, 'Department:'));
        $sourceBadge = $hasRoleSource && $hasDepartmentSource ? 'Role + Dept.' : ($hasRoleSource ? 'Role' : 'Dept.');
    @endphp
    <span class="badge bg-blue-lt permission-inheritance-badge" data-bs-toggle="tooltip" title="{{ implode('; ', $sourceLabels) }}"><i class="ti ti-link me-1" aria-hidden="true"></i>{{ $sourceBadge }}</span>
@endif
