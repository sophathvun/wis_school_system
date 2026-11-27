@if(!empty($preview['preview_token']) && ($canImport??false))
<form method="post" action="{{ route('gep.transfer.upload',$preview['result']['type']) }}" class="mt-3">
    @csrf
    <input type="hidden" name="use_preview" value="1">
    <input type="hidden" name="preview_token" value="{{ $preview['preview_token'] }}">
    <button class="btn btn-success" type="submit">Apply previewed file</button>
    <span class="text-secondary ms-2">{{ $preview['filename'] }} · {{ $preview['result']['rows'] }} rows</span>
</form>
@endif
