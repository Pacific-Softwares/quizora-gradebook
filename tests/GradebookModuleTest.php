<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Quiz;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Modules\QuizoraGradebook\Filament\Admin\GradebookOverview;
use Modules\QuizoraGradebook\Filament\Creator\GradebookPage;
use Modules\QuizoraGradebook\Models\ReportCard;
use Modules\QuizoraGradebook\Support\Gradebook;
use Pacific\Licentra\Modules\Laravel\ModuleLoader;
use Tests\TestCase;

/**
 * Runs inside Quizora:  php artisan test ../licentra/modules/quizora-gradebook/tests/GradebookModuleTest.php
 * The module is loaded in developer mode from a scratch folder (never your real modules-dev/).
 */
class GradebookModuleTest extends TestCase
{
    use RefreshDatabase;

    private static string $root;

    private static array $env = [];

    public function createApplication()
    {
        self::$root = sys_get_temp_dir().'/quizora-gradebook-test-'.getmypid();
        @mkdir(self::$root.'/modules-dev', 0777, true);
        @symlink(dirname(__DIR__), self::$root.'/modules-dev/quizora-gradebook');
        self::$env = [
            'LICENTRA_MODULES_DEV' => 'true',
            'LICENTRA_MODULES_PATH' => self::$root.'/modules',
            'LICENTRA_MODULES_DEV_PATH' => self::$root.'/modules-dev',
            'LICENTRA_MODULES_PUBLIC_PATH' => self::$root.'/public-modules',
            'LICENTRA_MODULES_REGISTRY' => self::$root.'/licentra-modules.php',
        ];
        foreach (self::$env as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('module:migrate', ['slug' => 'quizora-gradebook']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (array_keys(self::$env) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        putenv('LICENTRA_MODULES_DEV=false');
        $_ENV['LICENTRA_MODULES_DEV'] = $_SERVER['LICENTRA_MODULES_DEV'] = 'false';
        @unlink(self::$root.'/modules-dev/quizora-gradebook');
        (new Filesystem)->deleteDirectory(self::$root);
    }

    private function creator(): User
    {
        return User::factory()->create(['role' => 'creator']);
    }

    private function quiz(User $creator, string $title, float $pass = 60): Quiz
    {
        return Quiz::factory()->create(['creator_id' => $creator->id, 'title' => $title, 'pass_percentage' => $pass]);
    }

    private function attempt(Quiz $quiz, User $student, float $percentage, array $extra = []): Attempt
    {
        return Attempt::factory()->create(array_merge([
            'quiz_id' => $quiz->id, 'user_id' => $student->id, 'status' => 'completed',
            'submitted_at' => now()->subHour(), 'time_taken_seconds' => 300,
            'score' => $percentage / 10, 'total_marks' => 10, 'percentage' => $percentage,
            'is_passed' => $percentage >= (float) $quiz->pass_percentage,
        ], $extra));
    }

    private function asCreator(User $creator): void
    {
        Filament::setCurrentPanel(Filament::getPanel('creator'));
        $this->actingAs($creator);
    }

    public function test_the_module_loads_and_its_pages_are_registered(): void
    {
        $this->assertTrue(app(ModuleLoader::class)->isLoaded('quizora-gradebook'));
        $this->assertContains(GradebookPage::class, Filament::getPanel('creator')->getPages());
        $this->assertContains(GradebookOverview::class, Filament::getPanel('admin')->getPages());
        $this->assertStringStartsWith('quizora-gradebook/', GradebookPage::getSlug());
    }

    public function test_it_is_free_for_every_creator(): void
    {
        $this->asCreator($this->creator());

        $this->assertTrue(GradebookPage::canAccess());
    }

    public function test_the_grid_shows_each_students_best_score_and_class_averages(): void
    {
        $me = $this->creator();
        $algebra = $this->quiz($me, 'Algebra');
        $geometry = $this->quiz($me, 'Geometry');
        $ana = User::factory()->create(['name' => 'Ana Lopez']);
        $ben = User::factory()->create(['name' => 'Ben Ito']);
        $this->attempt($algebra, $ana, 40);
        $this->attempt($algebra, $ana, 90, ['attempt_number' => 2]);
        $this->attempt($geometry, $ana, 70);
        $this->attempt($algebra, $ben, 50);
        $this->attempt($algebra, $ben, 100, ['status' => 'in_progress', 'submitted_at' => null]); // not graded

        $book = new Gradebook($me->id);
        $grid = $book->grid([$algebra->id, $geometry->id]);

        $this->assertSame(['Ana Lopez', 'Ben Ito'], $grid['students']->pluck('name')->all());
        $this->assertSame(90.0, $grid['cells'][$ana->id][$algebra->id]['best']);
        $this->assertSame(2, $grid['cells'][$ana->id][$algebra->id]['attempts']);
        $this->assertSame(50.0, $grid['cells'][$ben->id][$algebra->id]['best']);
        $this->assertSame(1, $grid['cells'][$ben->id][$algebra->id]['attempts']);
        $this->assertSame(70.0, $book->quizAverages([$algebra->id])[$algebra->id]);
        $this->assertSame(['students' => 2, 'attempts' => 4, 'average' => 62.5, 'pass_rate' => 50.0], $book->stats([$algebra->id, $geometry->id]));

        $this->asCreator($me);
        Livewire::test(GradebookPage::class)
            ->assertOk()
            ->assertSee('Ana Lopez')
            ->assertSee('90%')
            ->assertSee('Class average')
            ->set('search', 'ben')
            ->assertSee('Ben Ito')
            ->assertDontSee('Ana Lopez');
    }

    public function test_another_creators_students_and_quizzes_never_show(): void
    {
        $me = $this->creator();
        $other = $this->creator();
        $mine = $this->quiz($me, 'My quiz');
        $theirs = $this->quiz($other, 'Their quiz');
        $this->attempt($mine, User::factory()->create(['name' => 'My Student']), 80);
        $stranger = User::factory()->create(['name' => 'Their Student']);
        $this->attempt($theirs, $stranger, 95);

        $book = new Gradebook($me->id);
        $this->assertSame([$mine->id], $book->ownQuizIds([$theirs->id, $mine->id]));
        $this->assertNull($book->student($stranger->id));
        $this->assertSame([], $book->best([$theirs->id]));

        $this->asCreator($me);
        Livewire::test(GradebookPage::class)
            ->assertDontSee('Their Student')
            ->call('toggleQuiz', $theirs->id)
            ->assertDontSee('Their quiz')
            ->call('openStudent', $stranger->id)
            ->assertSet('studentId', null);
    }

    public function test_the_period_filter_drops_old_attempts(): void
    {
        $me = $this->creator();
        $quiz = $this->quiz($me, 'Biology');
        $this->attempt($quiz, User::factory()->create(['name' => 'Old Timer']), 70, ['submitted_at' => now()->subDays(60)]);
        $this->attempt($quiz, User::factory()->create(['name' => 'New Comer']), 80);

        $this->asCreator($me);
        Livewire::test(GradebookPage::class)
            ->assertSee('Old Timer')
            ->set('period', '30d')
            ->assertSee('New Comer')
            ->assertDontSee('Old Timer');
    }

    public function test_a_shared_report_card_can_be_opened_renewed_and_turned_off(): void
    {
        $me = $this->creator();
        $quiz = $this->quiz($me, 'Chemistry');
        $student = User::factory()->create(['name' => 'Chloe Park']);
        $this->attempt($quiz, $student, 85);
        $this->asCreator($me);

        Livewire::test(GradebookPage::class)
            ->call('openStudent', $student->id)
            ->assertSet('studentId', $student->id)
            ->call('shareReportCard')
            ->set('note', 'Great term, Chloe!')
            ->call('saveNote');

        $card = ReportCard::firstOrFail();
        $this->assertSame(40, strlen($card->token));

        $this->get("/quizora-gradebook/report/{$card->token}")
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Chloe Park')
            ->assertSee('Chemistry')
            ->assertSee('85%')
            ->assertSee('Great term, Chloe!');
        $this->assertSame(1, $card->fresh()->views);

        $old = $card->token;
        Livewire::test(GradebookPage::class)->call('openStudent', $student->id)->call('regenerateReportCard');
        $this->get("/quizora-gradebook/report/{$old}")->assertNotFound();
        $new = $card->fresh()->token;
        $this->get("/quizora-gradebook/report/{$new}")->assertOk();

        Livewire::test(GradebookPage::class)->call('openStudent', $student->id)->call('revokeReportCard');
        $this->get("/quizora-gradebook/report/{$new}")->assertNotFound();
        $this->get('/quizora-gradebook/report/not-a-token')->assertNotFound();
    }

    public function test_the_report_card_only_lists_the_issuing_creators_quizzes(): void
    {
        $me = $this->creator();
        $other = $this->creator();
        $student = User::factory()->create();
        $this->attempt($this->quiz($me, 'Physics'), $student, 75);
        $this->attempt($this->quiz($other, 'Secret Exam'), $student, 99);
        $card = ReportCard::create(['creator_id' => $me->id, 'student_id' => $student->id]);

        $this->get("/quizora-gradebook/report/{$card->token}")->assertOk()->assertSee('Physics')->assertDontSee('Secret Exam');
    }

    public function test_csv_export_has_one_row_per_result_and_neutralises_formulas(): void
    {
        $me = $this->creator();
        $quiz = $this->quiz($me, '=HYPERLINK("x")');
        $this->attempt($quiz, User::factory()->create(['name' => 'Dana White', 'email' => 'dana@example.com']), 66);
        $this->asCreator($me);

        $response = Livewire::test(GradebookPage::class)->call('export');
        $response->assertFileDownloaded('gradebook-'.now()->format('Y-m-d').'.csv');

        $lines = iterator_to_array((new Gradebook($me->id))->csv([$quiz->id]));
        $this->assertCount(2, $lines);
        $this->assertSame(['Dana White', 'dana@example.com', '=HYPERLINK("x")', 66.0, 6.6, 10.0, 1, 'yes', $lines[1][8]], $lines[1]);
    }

    public function test_empty_gradebook_explains_what_will_appear(): void
    {
        $this->asCreator($this->creator());

        Livewire::test(GradebookPage::class)->assertOk()->assertSee('No graded attempts yet');
    }

    public function test_the_admin_overview_lists_active_creators(): void
    {
        $me = User::factory()->create(['role' => 'creator', 'name' => 'Prof. Ada']);
        $this->attempt($this->quiz($me, 'Logic'), User::factory()->create(), 80);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        Livewire::test(GradebookOverview::class)->assertOk()->assertSee('Prof. Ada')->assertSee('80%');
    }
}
