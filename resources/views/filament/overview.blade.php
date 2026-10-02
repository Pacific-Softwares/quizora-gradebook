@php($rows = $this->rows())
@php($totals = $this->totals())
<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('modules/quizora-gradebook/gradebook.css') }}">
<div class="gb">
    <div class="gb-stats">
        <div class="gb-card gb-stat"><small>{{ __('quizora-gradebook::messages.ov_creators') }}</small><b>{{ $rows->count() }}</b><i>{{ __('quizora-gradebook::messages.ov_last30') }}</i></div>
        <div class="gb-card gb-stat"><small>{{ __('quizora-gradebook::messages.stat_students') }}</small><b>{{ number_format($rows->sum('students')) }}</b><i>{{ __('quizora-gradebook::messages.ov_last30') }}</i></div>
        <div class="gb-card gb-stat"><small>{{ __('quizora-gradebook::messages.ov_cards') }}</small><b>{{ number_format($totals['cards']) }}</b><i>{{ __('quizora-gradebook::messages.ov_all_time') }}</i></div>
        <div class="gb-card gb-stat"><small>{{ __('quizora-gradebook::messages.ov_views') }}</small><b>{{ number_format($totals['views']) }}</b><i>{{ __('quizora-gradebook::messages.ov_all_time') }}</i></div>
    </div>
    <div class="gb-card">
        @if ($rows->isEmpty())
            <div class="gb-empty"><h3>{{ __('quizora-gradebook::messages.ov_empty') }}</h3></div>
        @else
            <div class="gb-scroll">
                <table class="gb-table">
                    <thead><tr>
                        <th class="who">{{ __('quizora-gradebook::messages.ov_creator') }}</th>
                        <th>{{ __('quizora-gradebook::messages.stat_students') }}</th>
                        <th>{{ __('quizora-gradebook::messages.attempts') }}</th>
                        <th>{{ __('quizora-gradebook::messages.stat_average') }}</th>
                        <th>{{ __('quizora-gradebook::messages.ov_cards') }}</th>
                        <th>{{ __('quizora-gradebook::messages.ov_views') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td class="who"><div class="gb-who"><span class="nm"><b>{{ $r->name }}</b><span>{{ $r->email }}</span></span></div></td>
                                <td>{{ number_format($r->students) }}</td>
                                <td>{{ number_format($r->attempts) }}</td>
                                <td class="gb-avg">{{ round((float) $r->average, 1) + 0 }}%</td>
                                <td>{{ $r->cards }}</td>
                                <td>{{ $r->views }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
</x-filament-panels::page>
