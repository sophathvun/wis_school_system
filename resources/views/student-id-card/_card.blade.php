@php
    $cardValues = \App\Support\StudentIdCardLayout::values($cardStudent, $cardStudent?->enrollments?->first());
    $cardAssets = [
        'logo' => $logoPath ? asset('storage/' . $logoPath) : '',
        'photo' => $cardStudent?->photo_path ? asset('storage/' . $cardStudent->photo_path) : '',
        'qr' => $cardStudent && (!($hasStoredQrCode ?? false) || filled($cardStudent->id_card_qr_code)) ? route('student-id-card-qr.qr', $cardStudent) . '?v=public-' . urlencode($cardStudent->id_card_qr_code ?? 'compact-qr') : '',
    ];
    $cardShowQr = collect($cardLayout['fields'])->contains(fn($field) => $field['type'] === 'qr' && $field['visible']);
@endphp
<div class="student-id-card student-id-card-preview student-id-template-card" data-id-card data-id-values="{{ json_encode($cardValues) }}" data-id-assets="{{ json_encode($cardAssets) }}">
    <div class="student-id-card-bg"></div>
    @foreach($cardLayout['fields'] as $key => $field)
        <div class="student-id-template-field student-id-template-{{ $field['type'] }}" data-id-field="{{ $key }}" style="{{ \App\Support\StudentIdCardLayout::style($field) }}" @if(!$field['visible'] || ($key === 'scan' && !$cardShowQr)) hidden @endif>
            @if($field['type'] === 'text')
                <span class="student-id-field-text">{{ \App\Support\StudentIdCardLayout::text($field['text'] ?? '', $cardValues) }}</span>
            @elseif($cardAssets[$field['type']])
                <img src="{{ $cardAssets[$field['type']] }}" alt="{{ $field['label'] }}" draggable="false">
            @elseif($field['type'] === 'logo')
                <span>Western International School</span>
            @else
                <span class="student-id-media-placeholder"><i class="ti ti-{{ $field['type'] === 'qr' ? 'qrcode' : 'user' }}" aria-hidden="true"></i></span>
            @endif
        </div>
    @endforeach
</div>
