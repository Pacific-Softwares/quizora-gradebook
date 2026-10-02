<?php

namespace Modules\QuizoraGradebook\Support;

use App\Models\Attempt;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every gradebook number for one creator, read live from Quizora's attempts.
 *
 * Only *graded* attempts count (completed or timed out, with a percentage), and only on the
 * creator's own quizzes: every query starts from quizIds(), so a student or quiz id sent from
 * the browser can never reach another creator's data.
 */
final class Gradebook
{
    public const PERIODS = ['all', '30d', '90d', '12m'];

    public function __construct(public readonly string $creatorId)
    {
    }

    /** The creator's quizzes that have at least one graded attempt, most recently attempted first. */
    public function quizzes(): Collection
    {
        return Quiz::query()
            ->where('creator_id', $this->creatorId)
            ->whereHas('attempts', fn (Builder $q) => $this->graded($q))
            ->withMax(['attempts as last_attempt_at' => fn (Builder $q) => $this->graded($q)], 'submitted_at')
            ->orderByDesc('last_attempt_at')
            ->get(['id', 'title', 'slug', 'pass_percentage']);
    }

    /** Keep only ids of this creator's quizzes, in the order given. */
    public function ownQuizIds(array $ids): array
    {
        $own = Quiz::where('creator_id', $this->creatorId)->whereIn('id', $ids)->pluck('id')->all();

        return array_values(array_filter($ids, fn ($id) => in_array($id, $own, true)));
    }

    public static function since(string $period): ?Carbon
    {
        return match ($period) {
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            '12m' => now()->subMonths(12),
            default => null,
        };
    }

    /**
     * One page of the grid: students (by name) and their best result per quiz.
     *
     * @return array{students: Collection, cells: array<string, array<string, array>>, total: int, pages: int}
     */
    public function grid(array $quizIds, ?Carbon $since = null, string $search = '', int $page = 1, int $perPage = 25): array
    {
        $students = User::query()
            ->whereIn('id', $this->attempts($quizIds, $since)->select('user_id'))
            ->when(trim($search) !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('name', 'like', '%'.trim($search).'%')
                ->orWhere('email', 'like', '%'.trim($search).'%')))
            ->orderBy('name')
            ->select(['id', 'name', 'email']);

        $total = (clone $students)->count();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $rows = $students->forPage($page, $perPage)->get();

        return [
            'students' => $rows,
            'cells' => $this->best($quizIds, $since, $rows->pluck('id')->all()),
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
        ];
    }

    /**
     * Best result per student and quiz.
     *
     * @return array<string, array<string, array{best: float, score: float, total: float, attempts: int, passed: bool, last: ?string}>>
     */
    public function best(array $quizIds, ?Carbon $since = null, ?array $studentIds = null): array
    {
        $rows = $this->attempts($quizIds, $since)
            ->when($studentIds !== null, fn (Builder $q) => $q->whereIn('user_id', $studentIds))
            ->selectRaw('user_id, quiz_id, MAX(percentage) as best, MAX(score) as score, MAX(total_marks) as total, COUNT(*) as attempts, MAX(CASE WHEN is_passed THEN 1 ELSE 0 END) as passed, MAX(submitted_at) as last')
            ->groupBy('user_id', 'quiz_id')
            ->toBase()
            ->get();

        $cells = [];
        foreach ($rows as $r) {
            $cells[$r->user_id][$r->quiz_id] = [
                'best' => round((float) $r->best, 1),
                'score' => (float) $r->score,
                'total' => (float) $r->total,
                'attempts' => (int) $r->attempts,
                'passed' => (bool) $r->passed,
                'last' => $r->last,
            ];
        }

        return $cells;
    }

    /** Average of the students' best scores, per quiz (the footer row). */
    public function quizAverages(array $quizIds, ?Carbon $since = null): array
    {
        $averages = [];
        foreach ($this->best($quizIds, $since) as $perQuiz) {
            foreach ($perQuiz as $quizId => $cell) {
                $averages[$quizId][] = $cell['best'];
            }
        }

        return array_map(fn (array $v) => round(array_sum($v) / count($v), 1), $averages);
    }

    /** @return array{students: int, attempts: int, average: ?float, pass_rate: ?float} */
    public function stats(array $quizIds, ?Carbon $since = null): array
    {
        $row = $this->attempts($quizIds, $since)
            ->selectRaw('COUNT(DISTINCT user_id) as students, COUNT(*) as attempts, AVG(percentage) as average, AVG(CASE WHEN is_passed THEN 1.0 ELSE 0.0 END) as pass_rate')
            ->toBase()
            ->first();

        return [
            'students' => (int) ($row->students ?? 0),
            'attempts' => (int) ($row->attempts ?? 0),
            'average' => $row && $row->attempts ? round((float) $row->average, 1) : null,
            'pass_rate' => $row && $row->attempts ? round((float) $row->pass_rate * 100, 1) : null,
        ];
    }

    /** A student who has taken at least one of this creator's quizzes, or null. */
    public function student(string $studentId): ?User
    {
        return User::query()
            ->whereKey($studentId)
            ->whereIn('id', $this->attempts($this->allQuizIds())->select('user_id'))
            ->first(['id', 'name', 'email', 'avatar']);
    }

    /** Every graded attempt of one student on this creator's quizzes, newest first. */
    public function attemptsOf(string $studentId): Collection
    {
        return $this->attempts($this->allQuizIds())
            ->where('user_id', $studentId)
            ->with(['quiz:id,title,slug,pass_percentage', 'certificate:id,attempt_id,uuid'])
            ->orderByDesc('submitted_at')
            ->get(['id', 'quiz_id', 'user_id', 'attempt_number', 'status', 'submitted_at', 'time_taken_seconds', 'score', 'total_marks', 'percentage', 'is_passed']);
    }

    /**
     * One student's report card: best result per quiz, plus totals.
     *
     * @return array{rows: Collection, average: ?float, passed: int, quizzes: int}
     */
    public function reportCard(string $studentId): array
    {
        $attempts = $this->attemptsOf($studentId);
        $rows = $attempts->groupBy('quiz_id')->map(function (Collection $group) {
            $best = $group->sortByDesc(fn (Attempt $a) => (float) $a->percentage)->first();

            return [
                'quiz' => $best->quiz,
                'best' => $best,
                'attempts' => $group->count(),
                'passed' => $group->contains(fn (Attempt $a) => (bool) $a->is_passed),
                'certificate' => $group->map->certificate->filter()->first(),
                'last' => $group->max('submitted_at'),
            ];
        })->sortBy(fn (array $r) => $r['quiz']?->title)->values();

        return [
            'rows' => $rows,
            'average' => $rows->isEmpty() ? null : round($rows->avg(fn (array $r) => (float) $r['best']->percentage), 1),
            'passed' => $rows->where('passed', true)->count(),
            'quizzes' => $rows->count(),
        ];
    }

    /** CSV lines for the whole grid (no paging): one row per student and quiz they took. */
    public function csv(array $quizIds, ?Carbon $since = null): \Generator
    {
        $titles = Quiz::whereIn('id', $quizIds)->pluck('title', 'id');
        $cells = $this->best($quizIds, $since);
        $students = User::whereIn('id', array_keys($cells))->orderBy('name')->get(['id', 'name', 'email']);

        yield ['Student', 'Email', 'Quiz', 'Best %', 'Best score', 'Total marks', 'Attempts', 'Passed', 'Last attempt'];
        foreach ($students as $student) {
            foreach ($quizIds as $quizId) {
                if ($cell = $cells[$student->id][$quizId] ?? null) {
                    yield [$student->name, $student->email, $titles[$quizId] ?? '', $cell['best'], $cell['score'], $cell['total'], $cell['attempts'], $cell['passed'] ? 'yes' : 'no', $cell['last']];
                }
            }
        }
    }

    private function allQuizIds(): array
    {
        return Quiz::where('creator_id', $this->creatorId)->pluck('id')->all();
    }

    /** Graded attempts on the given quizzes, limited to this creator's quizzes. */
    private function attempts(array $quizIds, ?Carbon $since = null): Builder
    {
        return $this->graded(Attempt::query())
            ->whereIn('quiz_id', $this->ownQuizIds($quizIds))
            ->when($since, fn (Builder $q) => $q->where('submitted_at', '>=', $since));
    }

    private function graded(Builder $query): Builder
    {
        return $query->whereIn('status', ['completed', 'timed_out'])->whereNotNull('percentage');
    }
}
