@extends('layouts.app')

@section('title', 'Feedback')

@section('content')
    <div class="page-header feedback-page-header">
        <div class="row align-items-center">
            <div class="col">
                <h2>Feedback</h2>
                <div class="text-secondary">Send feedback to the system administrator.</div>
            </div>
        </div>
    </div>

    <div class="card feedback-card">
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('feedback.save') }}" enctype="multipart/form-data" data-feedback-form>
                @csrf
                <label class="form-label">Subject</label>
                <input class="form-control mb-3" name="subject" value="{{ old('subject') }}" required>

                <label class="form-label">Message</label>
                <div class="feedback-editor" data-feedback-editor-shell>
                    <div class="feedback-editor-toolbar" aria-label="Feedback editor toolbar">
                        <button class="btn btn-outline-secondary" type="button" data-feedback-command="bold" title="Bold">
                            <i class="ti ti-bold"></i><span class="feedback-tool-label">Bold</span>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-feedback-command="italic" title="Italic">
                            <i class="ti ti-italic"></i><span class="feedback-tool-label">Italic</span>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-feedback-command="underline" title="Underline">
                            <i class="ti ti-underline"></i><span class="feedback-tool-label">Line</span>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-feedback-command="insertUnorderedList" title="Bullet list">
                            <i class="ti ti-list"></i><span class="feedback-tool-label">List</span>
                        </button>
                        <select class="form-select feedback-font-family" data-feedback-font-family aria-label="Font family">
                            <option value="">System Font</option>
                            <option value="Khmer OS Siemreap">Khmer OS Siemreap</option>
                            <option value="Khmer OS Battambang">Khmer OS Battambang</option>
                            <option value="Khmer OS Muol Light">Khmer OS Muol Light</option>
                            <option value="Noto Sans Khmer">Noto Sans Khmer</option>
                            <option value="Tacteing">Tacteing</option>
                            <option value="Arial">Arial</option>
                            <option value="Times New Roman">Times New Roman</option>
                        </select>
                        <select class="form-select feedback-font-size" data-feedback-font-size aria-label="Font size">
                            <option value="14px">Normal</option>
                            <option value="12px">Small</option>
                            <option value="16px">Medium</option>
                            <option value="18px">Large</option>
                            <option value="20px">Extra Large</option>
                        </select>
                        <div class="feedback-color-picker" aria-label="Text color" data-feedback-color-picker>
                            <button class="feedback-color-custom" type="button" data-feedback-color-open title="Choose custom text color">
                                <span class="feedback-color-custom-preview" data-feedback-color-preview
                                    style="--feedback-selected-color: #2563eb"></span>
                                Color
                            </button>
                            <input class="feedback-color-native" type="color" value="#2563eb"
                                data-feedback-color title="Choose text color">
                            <div class="feedback-color-palette" data-feedback-color-palette>
                                <button class="feedback-color-picker-button" type="button" data-feedback-native-open>
                                    <i class="ti ti-palette me-1"></i>More colors
                                </button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#e5e7eb"
                                    style="--feedback-swatch-color: #e5e7eb" title="White"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#111827"
                                    style="--feedback-swatch-color: #111827" title="Black"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#dc2626"
                                    style="--feedback-swatch-color: #dc2626" title="Red"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#16a34a"
                                    style="--feedback-swatch-color: #16a34a" title="Green"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#2563eb"
                                    style="--feedback-swatch-color: #2563eb" title="Blue"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#7c3aed"
                                    style="--feedback-swatch-color: #7c3aed" title="Purple"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#0891b2"
                                    style="--feedback-swatch-color: #0891b2" title="Cyan"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#0d9488"
                                    style="--feedback-swatch-color: #0d9488" title="Teal"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#65a30d"
                                    style="--feedback-swatch-color: #65a30d" title="Lime"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#ca8a04"
                                    style="--feedback-swatch-color: #ca8a04" title="Yellow"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#f97316"
                                    style="--feedback-swatch-color: #f97316" title="Orange"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#db2777"
                                    style="--feedback-swatch-color: #db2777" title="Pink"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#be123c"
                                    style="--feedback-swatch-color: #be123c" title="Rose"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#9333ea"
                                    style="--feedback-swatch-color: #9333ea" title="Violet"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#64748b"
                                    style="--feedback-swatch-color: #64748b" title="Slate"></button>
                                <button class="feedback-color-swatch" type="button" data-feedback-color-swatch="#78716c"
                                    style="--feedback-swatch-color: #78716c" title="Stone"></button>
                            </div>
                        </div>
                        <button class="btn btn-primary feedback-image-button" type="button" data-feedback-image-button>
                            <i class="ti ti-photo-up me-1"></i> Add image
                        </button>
                    </div>

                    <div class="feedback-editor-body" contenteditable="true" data-feedback-editor
                        aria-label="Feedback message" data-placeholder="Type your feedback. You can paste a screenshot here too."></div>

                    <div class="feedback-attachment-preview d-none" data-feedback-attachment-preview>
                        <img alt="Feedback attachment preview" data-feedback-attachment-image>
                        <div class="feedback-attachment-meta">
                            <div class="fw-semibold" data-feedback-attachment-name></div>
                            <button class="btn btn-outline-danger btn-sm" type="button" data-feedback-attachment-remove>
                                <i class="ti ti-trash me-1"></i> Remove image
                            </button>
                        </div>
                    </div>

                    <div class="feedback-editor-help">
                        <span>Use the toolbar to format text.</span>
                        <span>Add or paste a screenshot of the error form.</span>
                    </div>
                </div>

                <textarea class="d-none" name="message" data-feedback-message>{{ old('message') }}</textarea>
                <input class="d-none" type="file" name="attachment" accept="image/*" data-feedback-attachment>

                <button class="btn btn-primary mt-3" type="submit">
                    <i class="ti ti-send me-1"></i> Submit Feedback
                </button>
            </form>
        </div>
    </div>
@endsection

@vite('resources/css/pages/feedback.css')
@push('scripts')
    @vite('resources/js/feedback.js')
@endpush
