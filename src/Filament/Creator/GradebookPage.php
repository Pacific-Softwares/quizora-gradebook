<?php

namespace Modules\QuizoraGradebook\Filament\Creator;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Modules\QuizoraGradebook\Models\ReportCard;
use Modules\QuizoraGradebook\Support\Gradebook;
use Pacific\Licentra\Modules\Laravel\GatedByModule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Creator panel → Gradebook. Every number comes from Gradebook (scoped to the signed-in
 * creator), so ids posted from the browser can't reach other creators' students or quizzes.
 */
class GradebookPage extends Page
{
    use GatedByModule;

    protected static string $module = 'quizora-gradebook';

    protected static ?string $slug = 'quizora-gradebook/grades';

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'quizora-gradebook::filament.gradebook';

    /** Columns shown at once; more quizzes are one click away in the picker. */
    public const DEFAULT_COLUMNS = 6;

    #[Url]
    public string $period = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    /** @var list<string> */
    public array $quizIds = [];

    public int $gridPage = 1;

    public ?string $studentId = null;

    public string $note = '';

    public static function getNavigationLabel(): string
    {
        return __('quizora-gradebook::messages.nav');
    }

    public function getTitle(): string
    {
        return __('quizora-gradebook::messages.title');
    }

    public function getSubheading(): ?string
    {
        return __('quizora-gradebook::messages.subheading');
    }

    public function mount(): void
    {
        $this->quizIds = $this->gradebook()->quizzes()->take(self::DEFAULT_COLUMNS)->pluck('id')->all();
        if (! in_array($this->period, Gradebook::PERIODS, true)) {
            $this->period = 'all';
        }
    }

    public function gradebook(): Gradebook
    {
        return new Gradebook((string) auth()->id());
    }

    // ── Filters ──────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->gridPage = 1;
    }

    public function updatedPeriod(): void
    {
        $this->period = in_array($this->period, Gradebook::PERIODS, true) ? $this->period : 'all';
        $this->gridPage = 1;
    }

    public function toggleQuiz(string $quizId): void
    {
        $this->quizIds = in_array($quizId, $this->quizIds, true)
            ? array_values(array_diff($this->quizIds, [$quizId]))
            : $this->gradebook()->ownQuizIds([...$this->quizIds, $quizId]);
        $this->gridPage = 1;
    }

    public function showAllQuizzes(bool $all = true): void
    {
        $quizzes = $this->gradebook()->quizzes();
        $this->quizIds = ($all ? $quizzes : $quizzes->take(self::DEFAULT_COLUMNS))->pluck('id')->all();
        $this->gridPage = 1;
    }

    public function goToPage(int $page): void
    {
        $this->gridPage = max(1, $page);
    }

    /** Data for the view, computed once per render. */
    public function gradebookData(): array
    {
        $book = $this->gradebook();
        $since = Gradebook::since($this->period);
        $quizIds = $book->ownQuizIds($this->quizIds);
        $all = $book->quizzes();

        return [
            'allQuizzes' => $all,
            'columns' => $all->whereIn('id', $quizIds)->sortBy(fn ($q) => array_search($q->id, $quizIds, true))->values(),
            'grid' => $book->grid($quizIds, $since, $this->search, $this->gridPage),
            'stats' => $book->stats($quizIds, $since),
            'averages' => $book->quizAverages($quizIds, $since),
        ];
    }

    // ── Student drill-down and report card ────────────────────────────────

    public function openStudent(string $studentId): void
    {
        if (! $this->gradebook()->student($studentId)) {
            return;
        }
        $this->studentId = $studentId;
        $this->note = (string) $this->card()?->note;
        $this->dispatch('open-modal', id: 'gradebook-student');
    }

    public function student(): ?User
    {
        return $this->studentId ? $this->gradebook()->student($this->studentId) : null;
    }

    public function card(): ?ReportCard
    {
        return $this->studentId
            ? ReportCard::where('creator_id', auth()->id())->where('student_id', $this->studentId)->first()
            : null;
    }

    public function shareReportCard(): void
    {
        if (! $this->student()) {
            return;
        }
        ReportCard::firstOrCreate(['creator_id' => auth()->id(), 'student_id' => $this->studentId]);
        Notification::make()->success()->title(__('quizora-gradebook::messages.link_created'))->send();
    }

    public function saveNote(): void
    {
        $this->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $this->card()?->update(['note' => trim($this->note) ?: null]);
        Notification::make()->success()->title(__('quizora-gradebook::messages.note_saved'))->send();
    }

    public function regenerateReportCard(): void
    {
        $this->card()?->regenerate();
        Notification::make()->success()->title(__('quizora-gradebook::messages.link_renewed'))->send();
    }

    public function revokeReportCard(): void
    {
        $this->card()?->delete();
        $this->note = '';
        Notification::make()->success()->title(__('quizora-gradebook::messages.link_revoked'))->send();
    }

    // ── Export ───────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('quizora-gradebook::messages.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->export()),
        ];
    }

    public function export(): StreamedResponse
    {
        $book = $this->gradebook();
        $quizIds = $book->ownQuizIds($this->quizIds);
        $since = Gradebook::since($this->period);

        return response()->streamDownload(function () use ($book, $quizIds, $since) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows names correctly
            foreach ($book->csv($quizIds, $since) as $line) {
                // Neutralise spreadsheet formulas in names and titles (CSV injection).
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $line));
            }
            fclose($out);
        }, 'gradebook-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
