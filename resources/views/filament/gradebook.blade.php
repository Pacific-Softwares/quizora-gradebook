@php
    $d = $this->gradebookData();
    $grid = $d['grid'];
    $columns = $d['columns'];
    $stats = $d['stats'];
    $initials = fn (?string $name) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join('')) ?: '?';
    $band = fn (array $cell, $quiz) => $cell['passed'] || $cell['best'] >= (float) ($quiz->pass_percentage ?? 0) ? 'pass' : ($cell['best'] >= ((float) ($quiz->pass_percentage ?: 50)) * 0.6 ? 'fail' : 'low');
    $periods = ['all' => __('quizora-gradebook::messages.period_all'), '30d' => __('quizora-gradebook::messages.period_30d'), '90d' => __('quizora-gradebook::messages.period_90d'), '12m' => __('quizora-gradebook::messages.period_12m')];
    $fmtTime = fn (?int $s) => $s === null ? '—' : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
@endphp

<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('modules/quizora-gradebook/gradebook.css') }}">

<div class="gb">
@if ($d['allQuizzes']->isEmpty())
    <div class="gb-card gb-empty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
        <h3>{{ __('quizora-gradebook::messages.empty_title') }}</h3>
        <p>{{ __('quizora-gradebook::messages.empty_body') }}</p>
    </div>
@else
    {{-- Toolbar --}}
    <div class="gb-bar">
        <div class="gb-seg" role="group" aria-label="{{ __('quizora-gradebook::messages.period') }}">
            @foreach ($periods as $key => $label)
                <button type="button" wire:click="$set('period', '{{ $key }}')" @class(['on' => $period === $key])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="gb-pick" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
            <button type="button" class="gb-btn" @click="open = !open">
                <x-filament::icon icon="heroicon-m-view-columns" class="h-4 w-4" />
                {{ __('quizora-gradebook::messages.quizzes') }}
                <span class="n">{{ $columns->count() }} / {{ $d['allQuizzes']->count() }}</span>
            </button>
            <div class="gb-menu" x-show="open" x-cloak x-transition.opacity>
                @foreach ($d['allQuizzes'] as $quiz)
                    <label wire:key="pick-{{ $quiz->id }}">
                        <input type="checkbox" wire:click="toggleQuiz('{{ $quiz->id }}')" @checked(in_array($quiz->id, $quizIds, true))>
                        <span>{{ $quiz->title }}</span>
                    </label>
                @endforeach
                <div class="acts">
                    <button type="button" wire:click="showAllQuizzes(true)">{{ __('quizora-gradebook::messages.show_all') }}</button>
                    <button type="button" wire:click="showAllQuizzes(false)">{{ __('quizora-gradebook::messages.show_recent') }}</button>
                </div>
            </div>
        </div>

        <label class="gb-search">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.45 4.39l3.08 3.08a.75.75 0 1 1-1.06 1.06l-3.08-3.08A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
            <input type="search" wire:model.live.debounce.350ms="search" placeholder="{{ __('quizora-gradebook::messages.search') }}" aria-label="{{ __('quizora-gradebook::messages.search') }}">
        </label>
    </div>

    {{-- At a glance --}}
    <div class="gb-stats">
        <div class="gb-card gb-stat"><small>{{ __('quizora-gradebook::messages.stat_students') }}</small><b>{{ number_format($stats['students']) }}</b><i>{{ trans_choice('quizora-gradebook::messages.stat_attempts', $stats['attempts'], ['n' => number_format($stats['attempts'])]) }}</i></div>
        <div class="gb-card gb-stat"><small>{{ __('quizora-gradebook::messages.stat_average') }}</small><b>{{ $stats['average'] !== null ? $stats['average'].'%' : '—' }}</b><i>{{ __('quizora-gradebook::messages.stat_average_hint') }}</i></div>
        <div class="gb-card gb-stat"><small>{{ __('quizora-gradebook::messages.stat_pass_rate') }}</small><b>{{ $stats['pass_rate'] !== null ? $stats['pass_rate'].'%' : '—' }}</b><i>{{ __('quizora-gradebook::messages.stat_pass_hint') }}</i></div>
        <div class="gb-card gb-stat"><small>{{ __('quizora-gradebook::messages.stat_quizzes') }}</small><b>{{ $columns->count() }}</b><i>{{ $periods[$period] }}</i></div>
    </div>

    {{-- The grid --}}
    <div class="gb-card">
        @if ($columns->isEmpty())
            <div class="gb-empty"><h3>{{ __('quizora-gradebook::messages.no_columns') }}</h3><p>{{ __('quizora-gradebook::messages.no_columns_hint') }}</p></div>
        @elseif ($grid['total'] === 0)
            <div class="gb-empty"><h3>{{ __('quizora-gradebook::messages.no_students') }}</h3><p>{{ trim($search) !== '' ? __('quizora-gradebook::messages.no_students_search') : __('quizora-gradebook::messages.no_students_period') }}</p></div>
        @else
            <div class="gb-scroll">
                <table class="gb-table">
                    <thead>
                        <tr>
                            <th class="who">{{ __('quizora-gradebook::messages.student') }}</th>
                            @foreach ($columns as $quiz)
                                <th title="{{ $quiz->title }}"><span class="t">{{ $quiz->title }}</span><span class="p">{{ __('quizora-gradebook::messages.pass_mark', ['n' => (float) $quiz->pass_percentage]) }}</span></th>
                            @endforeach
                            <th>{{ __('quizora-gradebook::messages.average') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grid['students'] as $student)
                            @php($row = $grid['cells'][$student->id] ?? [])
                            <tr wire:key="row-{{ $student->id }}">
                                <td class="who">
                                    <button type="button" class="gb-who" wire:click="openStudent('{{ $student->id }}')" title="{{ __('quizora-gradebook::messages.open_student') }}">
                                        <span class="gb-av">{{ $initials($student->name) }}</span>
                                        <span class="nm"><b>{{ $student->name }}</b><span>{{ $student->email }}</span></span>
                                    </button>
                                </td>
                                @foreach ($columns as $quiz)
                                    <td>
                                        @if ($cell = $row[$quiz->id] ?? null)
                                            <span class="gb-cell {{ $band($cell, $quiz) }}" title="{{ __('quizora-gradebook::messages.cell_title', ['score' => $cell['score'] + 0, 'total' => $cell['total'] + 0, 'n' => $cell['attempts']]) }}">
                                                {{ $cell['best'] + 0 }}%
                                                <small>{{ trans_choice('quizora-gradebook::messages.tries', $cell['attempts'], ['n' => $cell['attempts']]) }}</small>
                                            </span>
                                        @else
                                            <span class="gb-none">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="gb-avg">{{ $row ? round(collect($row)->avg('best'), 1) + 0 .'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="who">{{ __('quizora-gradebook::messages.class_average') }}</td>
                            @foreach ($columns as $quiz)
                                <td>{{ isset($d['averages'][$quiz->id]) ? ($d['averages'][$quiz->id] + 0).'%' : '—' }}</td>
                            @endforeach
                            <td>{{ $stats['average'] !== null ? $stats['average'].'%' : '—' }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="gb-foot">
                <span>{{ __('quizora-gradebook::messages.showing', ['from' => ($grid['page'] - 1) * 25 + 1, 'to' => min($grid['page'] * 25, $grid['total']), 'total' => $grid['total']]) }}</span>
                @if ($grid['pages'] > 1)
                    <span class="pg">
                        <button type="button" wire:click="goToPage({{ $grid['page'] - 1 }})" @disabled($grid['page'] <= 1)>{{ __('quizora-gradebook::messages.prev') }}</button>
                        <button type="button" wire:click="goToPage({{ $grid['page'] + 1 }})" @disabled($grid['page'] >= $grid['pages'])>{{ __('quizora-gradebook::messages.next') }}</button>
                    </span>
                @endif
            </div>
        @endif
    </div>

    <div class="gb-legend">
        <span><i style="background:var(--gb-pass-bg);border:1px solid var(--gb-pass)"></i>{{ __('quizora-gradebook::messages.legend_pass') }}</span>
        <span><i style="background:var(--gb-fail-bg);border:1px solid var(--gb-fail)"></i>{{ __('quizora-gradebook::messages.legend_close') }}</span>
        <span><i style="background:var(--gb-low-bg);border:1px solid var(--gb-low)"></i>{{ __('quizora-gradebook::messages.legend_low') }}</span>
        <span>{{ __('quizora-gradebook::messages.legend_click') }}</span>
    </div>
@endif
</div>

{{-- Student drill-down --}}
<x-filament::modal id="gradebook-student" width="2xl" slide-over>
    @if ($student = $this->student())
        @php($report = $this->gradebook()->reportCard($student->id))
        @php($attempts = $this->gradebook()->attemptsOf($student->id))
        @php($card = $this->card())
        <div class="gb gb-st">
            <div class="gb-st-head">
                <span class="gb-av">{{ $initials($student->name) }}</span>
                <div><b>{{ $student->name }}</b><span>{{ $student->email }}</span></div>
            </div>

            <div class="gb-chips">
                <div class="gb-chip"><small>{{ __('quizora-gradebook::messages.chip_quizzes') }}</small><b>{{ $report['quizzes'] }}</b></div>
                <div class="gb-chip"><small>{{ __('quizora-gradebook::messages.chip_average') }}</small><b>{{ $report['average'] !== null ? ($report['average'] + 0).'%' : '—' }}</b></div>
                <div class="gb-chip"><small>{{ __('quizora-gradebook::messages.chip_passed') }}</small><b>{{ $report['passed'] }} / {{ $report['quizzes'] }}</b></div>
            </div>

            {{-- Report card --}}
            <div>
                <p class="gb-h">{{ __('quizora-gradebook::messages.report_card') }}</p>
                <div class="gb-share">
                    @if (! $card)
                        <p>{{ __('quizora-gradebook::messages.report_card_hint') }}</p>
                        <div class="gb-acts">
                            <x-filament::button wire:click="shareReportCard" icon="heroicon-o-link" size="sm">{{ __('quizora-gradebook::messages.create_link') }}</x-filament::button>
                        </div>
                    @else
                        <div class="gb-link" x-data="{ copied: false }">
                            <input type="text" readonly value="{{ $card->url() }}" @focus="$el.select()" aria-label="{{ __('quizora-gradebook::messages.report_card') }}">
                            <x-filament::button size="sm" color="gray" icon="heroicon-o-clipboard"
                                x-on:click="navigator.clipboard.writeText('{{ $card->url() }}'); copied = true; setTimeout(() => copied = false, 1500)">
                                <span x-text="copied ? @js(__('quizora-gradebook::messages.copied')) : @js(__('quizora-gradebook::messages.copy'))"></span>
                            </x-filament::button>
                            <x-filament::button size="sm" color="gray" tag="a" href="{{ $card->url() }}" target="_blank" icon="heroicon-o-arrow-top-right-on-square">{{ __('quizora-gradebook::messages.open') }}</x-filament::button>
                        </div>
                        <textarea rows="2" wire:model="note" maxlength="1000" placeholder="{{ __('quizora-gradebook::messages.note_placeholder') }}" aria-label="{{ __('quizora-gradebook::messages.note') }}"></textarea>
                        <div class="gb-acts">
                            <x-filament::button size="xs" wire:click="saveNote">{{ __('quizora-gradebook::messages.save_note') }}</x-filament::button>
                            <x-filament::button size="xs" color="gray" wire:click="regenerateReportCard" wire:confirm="{{ __('quizora-gradebook::messages.renew_confirm') }}">{{ __('quizora-gradebook::messages.renew') }}</x-filament::button>
                            <x-filament::button size="xs" color="danger" outlined wire:click="revokeReportCard" wire:confirm="{{ __('quizora-gradebook::messages.revoke_confirm') }}">{{ __('quizora-gradebook::messages.revoke') }}</x-filament::button>
                            <span class="meta">{{ trans_choice('quizora-gradebook::messages.views', $card->views, ['n' => $card->views]) }}@if ($card->last_viewed_at) · {{ $card->last_viewed_at->diffForHumans() }}@endif</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Every attempt --}}
            <div>
                <p class="gb-h">{{ __('quizora-gradebook::messages.all_attempts') }}</p>
                <div class="gb-list">
                    @foreach ($attempts as $a)
                        <div class="gb-row" wire:key="att-{{ $a->id }}">
                            <div style="min-width:0">
                                <div class="q">{{ $a->quiz?->title }}</div>
                                <div class="m">{{ $a->submitted_at?->translatedFormat('M j, Y · H:i') }} · {{ __('quizora-gradebook::messages.time_taken', ['t' => $fmtTime($a->time_taken_seconds)]) }} · {{ __('quizora-gradebook::messages.score_of', ['score' => (float) $a->score, 'total' => (float) $a->total_marks]) }}</div>
                            </div>
                            <div class="r">
                                @if ($a->certificate)
                                    <a href="{{ route('certificate.verify', $a->certificate->uuid) }}" target="_blank">{{ __('quizora-gradebook::messages.certificate') }}</a>
                                @endif
                                <span class="gb-cell {{ $a->is_passed ? 'pass' : 'low' }}">{{ (float) $a->percentage }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-filament::modal>
</x-filament-panels::page>
