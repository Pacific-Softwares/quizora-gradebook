<?php

namespace Modules\QuizoraGradebook\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class CreatorPlugin implements Plugin
{
    public static function make(): static
    {
        return new static();
    }

    public function getId(): string
    {
        return 'quizora-gradebook-creator';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Creator\GradebookPage::class]);
    }

    public function boot(Panel $panel): void
    {
    }
}
