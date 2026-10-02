<?php

namespace Modules\QuizoraGradebook\Filament\Admin;

use App\Models\Attempt;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Modules\QuizoraGradebook\Models\ReportCard;
use Pacific\Licentra\Modules\Laravel\GatedByModule;

/** Admin panel → Gradebook: which creators grade the most, and report cards shared. */
class GradebookOverview extends Page
{
    use GatedByModule;

    protected static string $module = 'quizora-gradebook';

    protected static ?string $slug = 'quizora-gradebook/overview';

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'quizora-gradebook::filament.overview';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group_configuration');
    }

    public static function getNavigationLabel(): string
    {
        return __('quizora-gradebook::messages.nav');
    }

    public function getTitle(): string
    {
        return __('quizora-gradebook::messages.overview_title');
    }

    /** Creators with graded attempts in the last 30 days, busiest first. */
    public function rows()
    {
        $cards = ReportCard::query()->selectRaw('creator_id, COUNT(*) as cards, SUM(views) as views')->groupBy('creator_id')->get()->keyBy('creator_id');

        return Attempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'attempts.quiz_id')
            ->join('users', 'users.id', '=', 'quizzes.creator_id')
            ->whereIn('attempts.status', ['completed', 'timed_out'])
            ->whereNotNull('attempts.percentage')
            ->where('attempts.submitted_at', '>=', now()->subDays(30))
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(50)
            ->get([
                'users.id', 'users.name', 'users.email',
                DB::raw('COUNT(DISTINCT attempts.user_id) as students'),
                DB::raw('COUNT(*) as attempts'),
                DB::raw('AVG(attempts.percentage) as average'),
            ])
            ->map(fn ($r) => $r->setAttribute('cards', (int) ($cards[$r->id]->cards ?? 0))->setAttribute('views', (int) ($cards[$r->id]->views ?? 0)));
    }

    public function totals(): array
    {
        return [
            'cards' => ReportCard::count(),
            'views' => (int) ReportCard::sum('views'),
        ];
    }
}
