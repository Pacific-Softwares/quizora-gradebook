@php
    $creator = $card->creator;
    $student = $card->student;
    $logo = $creator->certificate_logo ?: $creator->avatar;
    $logoUrl = $logo ? (str_starts_with($logo, 'http') ? $logo : \Illuminate\Support\Facades\Storage::url($logo)) : null;
    $fmtTime = fn (?int $s) => $s === null ? '—' : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('quizora-gradebook::messages.report_title', ['name' => $student->name]) }}</title>
    <link rel="stylesheet" href="{{ asset('modules/quizora-gradebook/report.css') }}">
</head>
<body>
<div class="bar">
    <span>{{ __('quizora-gradebook::messages.report_card') }} · {{ $student->name }}</span>
    <button type="button" onclick="window.print()">{{ __('quizora-gradebook::messages.print') }}</button>
</div>

<main class="sheet">
    <header class="head">
        <div class="from">
            @if ($logoUrl)<img src="{{ $logoUrl }}" alt="" class="logo">@endif
            <div>
                <p class="label">{{ __('quizora-gradebook::messages.issued_by') }}</p>
                <h2>{{ $creator->name }}</h2>
            </div>
        </div>
        <div class="date">
            <p class="label">{{ __('quizora-gradebook::messages.as_of') }}</p>
            <p>{{ now()->translatedFormat('F j, Y') }}</p>
        </div>
    </header>

    <section class="student">
        <p class="label">{{ __('quizora-gradebook::messages.report_card') }}</p>
        <h1>{{ $student->name }}</h1>
    </section>

    <section class="summary">
        <div><small>{{ __('quizora-gradebook::messages.chip_quizzes') }}</small><b>{{ $report['quizzes'] }}</b></div>
        <div><small>{{ __('quizora-gradebook::messages.chip_average') }}</small><b>{{ $report['average'] !== null ? ($report['average'] + 0).'%' : '—' }}</b></div>
        <div><small>{{ __('quizora-gradebook::messages.chip_passed') }}</small><b>{{ $report['passed'] }} / {{ $report['quizzes'] }}</b></div>
    </section>

    @if ($report['rows']->isEmpty())
        <p class="empty">{{ __('quizora-gradebook::messages.report_empty') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('quizora-gradebook::messages.quiz') }}</th>
                    <th class="n">{{ __('quizora-gradebook::messages.best_score') }}</th>
                    <th class="n">{{ __('quizora-gradebook::messages.result') }}</th>
                    <th class="n hide-sm">{{ __('quizora-gradebook::messages.attempts') }}</th>
                    <th class="n hide-sm">{{ __('quizora-gradebook::messages.last_attempt') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td>
                            <b>{{ $row['quiz']?->title }}</b>
                            <span class="sub">{{ __('quizora-gradebook::messages.score_of', ['score' => (float) $row['best']->score, 'total' => (float) $row['best']->total_marks]) }} · {{ __('quizora-gradebook::messages.time_taken', ['t' => $fmtTime($row['best']->time_taken_seconds)]) }}</span>
                            @if ($row['certificate'])
                                <a class="cert" href="{{ route('certificate.verify', $row['certificate']->uuid) }}">{{ __('quizora-gradebook::messages.verify_certificate') }}</a>
                            @endif
                        </td>
                        <td class="n pct">{{ (float) $row['best']->percentage }}%</td>
                        <td class="n"><span @class(['pill', 'pass' => $row['passed'], 'fail' => ! $row['passed']])>{{ $row['passed'] ? __('quizora-gradebook::messages.passed') : __('quizora-gradebook::messages.not_passed') }}</span></td>
                        <td class="n hide-sm">{{ $row['attempts'] }}</td>
                        <td class="n hide-sm">{{ \Illuminate\Support\Carbon::parse($row['last'])->translatedFormat('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($card->note)
        <section class="note">
            <p class="label">{{ __('quizora-gradebook::messages.note_from', ['name' => $creator->name]) }}</p>
            <p>{{ $card->note }}</p>
        </section>
    @endif

    <footer>{{ __('quizora-gradebook::messages.report_footer') }}</footer>
</main>
</body>
</html>
