@php
    $programOptions = \App\Support\ProgramWorkspace::available(auth()->user());
    $selectedProgram = \App\Support\ProgramWorkspace::selected(auth()->user());
@endphp
@if(count($programOptions))
    @if(count($programOptions)>1)
        <div class="dropdown program-workspace-switcher">
            <button class="btn program-switch-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Switch program, current program {{ $programOptions[$selectedProgram] }}">
                <span class="program-switch-symbol"><i class="ti ti-{{ $selectedProgram==='gep'?'language':'school' }}" aria-hidden="true"></i></span><span class="program-switch-caption"><small>Workspace</small><strong>{{ $programOptions[$selectedProgram] }}</strong></span><i class="ti ti-selector program-switch-chevron" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end program-switch-menu">
                <div class="program-switch-menu-heading"><span class="program-switch-menu-icon"><i class="ti ti-apps" aria-hidden="true"></i></span><div><strong>Choose your workspace</strong><small>Switch between your school programs</small></div></div>
                @foreach($programOptions as $programCode=>$programLabel)
                    <form action="{{ route('program-workspace.switch') }}" method="post" data-program-switch-form>
                        @csrf
                        <input type="hidden" name="program" value="{{ $programCode }}">
                        <button type="submit" class="program-switch-option {{ $selectedProgram===$programCode?'is-selected':'' }}" @disabled($selectedProgram===$programCode) aria-label="{{ $programLabel }}{{ $selectedProgram===$programCode?' (current workspace)':'' }}">
                            <span class="program-option-icon"><i class="ti ti-{{ $programCode==='gep'?'language':'school' }}" aria-hidden="true"></i></span><span class="program-option-copy"><strong>{{ $programLabel }}</strong><small>{{ $programCode==='gep'?'General English Program':'Western International School' }}</small></span><span class="program-option-check"><i class="ti ti-{{ $selectedProgram===$programCode?'check':'arrow-right' }}" aria-hidden="true"></i></span>
                        </button>
                    </form>
                @endforeach
                <div class="program-switch-menu-note"><i class="ti ti-lock me-1" aria-hidden="true"></i>Your permissions apply in each workspace.</div>
            </div>
        </div>
    @else
        <span class="program-single-workspace"><i class="ti ti-books me-1" aria-hidden="true"></i>{{ $programOptions[$selectedProgram] }}</span>
    @endif
@endif
