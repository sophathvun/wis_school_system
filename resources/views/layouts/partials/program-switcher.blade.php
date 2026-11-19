@php
    $programOptions = \App\Support\ProgramWorkspace::available(auth()->user());
    $selectedProgram = \App\Support\ProgramWorkspace::selected(auth()->user());
@endphp
@if(count($programOptions))
    @if(count($programOptions)>1)
        <div class="dropdown program-workspace-switcher">
            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Select program workspace">
                <i class="ti ti-books me-2" aria-hidden="true"></i><span>Program: {{ $programOptions[$selectedProgram] }}</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                @foreach($programOptions as $programCode=>$programLabel)
                    <form action="{{ route('program-workspace.switch') }}" method="post" data-program-switch-form>
                        @csrf
                        <input type="hidden" name="program" value="{{ $programCode }}">
                        <button type="submit" class="dropdown-item {{ $selectedProgram===$programCode?'active':'' }}" @disabled($selectedProgram===$programCode)>
                            {{ $programLabel }}@if($selectedProgram===$programCode)<i class="ti ti-check ms-auto" aria-hidden="true"></i>@endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @else
        <span class="badge bg-primary-lt px-3 py-2"><i class="ti ti-books me-1" aria-hidden="true"></i>Program: {{ $programOptions[$selectedProgram] }}</span>
    @endif
@endif
