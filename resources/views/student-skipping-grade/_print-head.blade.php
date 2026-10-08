<!DOCTYPE html>
<html lang="km"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $printTitle }}</title>
@vite(['resources/css/pages/student-skipping-grade-print.css','resources/js/studentSkippingGradePrint.js'])
</head><body @if($templatePreview??false) class="skipping-template-preview" @endif @if($imageExport??false) data-skipping-image-export data-approval-filename="{{ $approvalImageFilename }}" @endif>
@unless($imageExport??false)<div class="skipping-print-toolbar"><button type="button" data-skipping-print>Print</button><button type="button" data-skipping-close>Close</button></div>@endunless
