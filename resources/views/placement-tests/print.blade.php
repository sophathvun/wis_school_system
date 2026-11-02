<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Placement Test &middot; {{ $attempt->applicant->application_code }}</title>
    @vite(['resources/css/pages/placement-test-print.css', 'resources/js/placementTestPrint.js'])
</head>

<body>
    @php
        $snapshot = $attempt->snapshot;
        $questions = $snapshot['questions'] ?? [];
        $questionDocument = in_array($document, ['package', 'questions'], true);
        $answerSheetDocument = in_array($document, ['package', 'answer-sheet'], true);
    @endphp

    {{-- Document controls are hidden automatically when printing. --}}
    <nav class="toolbar" aria-label="Print document options">
        <button type="button" data-placement-test-print>Print A4</button>
        <a href="?document=package">Full Package</a>
        <a href="?document=questions">Questions</a>
        <a href="?document=answer-sheet">Answer Sheet</a>
        <a href="?document=answer-key">Answer Key</a>
    </nav>

    {{-- Question paper --}}
    @if ($questionDocument)
        <section class="page">
            <div class="watermark">PLACEMENT TEST</div>

            <header class="head">
                <div>
                    @if ($logo)
                        <img class="logo" src="{{ $logo }}" alt="School logo">
                    @endif
                </div>

                <div>
                    <h1>STUDENT PLACEMENT TEST</h1>
                    <p>
                        {{ $snapshot['name'] ?? 'Placement Test' }}
                        &middot; Grade {{ $snapshot['grade'] ?? '' }}
                    </p>
                </div>

                <div class="form-badge">FORM {{ $snapshot['form_code'] ?? '' }}</div>
            </header>

            <div class="candidate">
                <div>
                    <span>Applicant</span>
                    <strong>{{ $attempt->applicant->full_name_en }}</strong>
                </div>
                <div>
                    <span>Application Code</span>
                    <strong>{{ $attempt->applicant->application_code }}</strong>
                </div>
                <div>
                    <span>Academic Year</span>
                    <strong>{{ $attempt->applicant->academicYear?->academic_year }}</strong>
                </div>
                <div>
                    <span>Campus</span>
                    <strong>{{ $attempt->applicant->campus?->campus_name_en }}</strong>
                </div>
                <div>
                    <span>Duration</span>
                    <strong>{{ $snapshot['duration_minutes'] }} minutes</strong>
                </div>
                <div>
                    <span>Maximum Score</span>
                    <strong>{{ $snapshot['max_score'] }}</strong>
                </div>
            </div>

            <div class="instructions">
                <strong>Instructions</strong><br>
                {{ $snapshot['instructions'] ?: 'Choose the best answer for every question. Mark one answer only.' }}
            </div>

            @php($lastSubject = null)

            @foreach ($questions as $question)
                @php
                    $subject = $question['subject'] ?? 'General';
                    $questionType = $question['type'] ?? 'multiple_choice';
                    $isChoiceQuestion = in_array($questionType, ['multiple_choice', 'true_false'], true);
                @endphp

                @if ($subject !== $lastSubject)
                    <h2 class="subject">{{ $subject }}</h2>
                    @php($lastSubject = $subject)
                @endif

                <article class="question">
                    <div class="question-title">
                        <span>{{ $question['order_no'] }}.</span>
                        <span>{{ $question['prompt'] }}</span>
                        <span class="question-points">{{ $question['points'] }} pt</span>
                    </div>

                    @if ($isChoiceQuestion)
                        <div class="answers">
                            @foreach ($question['options'] ?? [] as $index => $answer)
                                @php
                                    $answerLabel =
                                        ($question['option_label_style'] ?? 'letters') === 'numbers'
                                            ? $index + 1
                                            : chr(65 + $index);
                                @endphp

                                <div class="answer">
                                    <b>{{ $answerLabel }}</b>
                                    <span>{{ $answer }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        @php
                            $writtenLineCount = match ($questionType) {
                                'essay' => 10,
                                'short_answer' => 5,
                                default => 2,
                            };
                        @endphp

                        <div class="written-lines">
                            @for ($line = 0; $line < $writtenLineCount; $line++)
                                <i></i>
                            @endfor
                        </div>
                    @endif
                </article>
            @endforeach

            <div class="signature">
                <span>Candidate signature: ____________________</span>
                <span>Invigilator: ____________________</span>
            </div>
        </section>
    @endif

    {{-- Optical answer sheet SVG generated by the placement-test service. --}}
    @if ($answerSheetDocument)
        <section class="page answer-sheet">
            {!! $svg !!}
        </section>
    @endif

    {{-- Confidential answer key --}}
    @if ($document === 'answer-key')
        <section class="page key">
            <header class="head">
                <div>
                    @if ($logo)
                        <img class="logo" src="{{ $logo }}" alt="School logo">
                    @endif
                </div>

                <div>
                    <h1>CONFIDENTIAL ANSWER KEY</h1>
                    <p>
                        {{ $snapshot['name'] ?? '' }}
                        &middot; Form {{ $snapshot['form_code'] ?? '' }}
                    </p>
                </div>

                <div class="form-badge">STAFF ONLY</div>
            </header>

            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Subject</th>
                        <th>Answer / Marking Reference</th>
                        <th>Points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($questions as $question)
                        @php
                            $isWrittenQuestion = in_array($question['type'] ?? '', ['short_answer', 'essay'], true);
                            $answerReference =
                                $question['correct_answer'] ?:
                                ($isWrittenQuestion
                                    ? 'Use marking guidance and examiner judgment'
                                    : 'Not provided');
                        @endphp

                        <tr>
                            <td>{{ $question['order_no'] }}</td>
                            <td>{{ $question['subject'] }}</td>
                            <td>
                                <strong>{{ $answerReference }}</strong>
                            </td>
                            <td>{{ $question['points'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</body>

</html>
