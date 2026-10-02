<?php

namespace Modules\QuizoraGradebook\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

class AdminPlugin implements Plugin
{
    public static function make(): static
    {
        return new static();
    }

    public function getId(): string
    {
        return 'quizora-gradebook-admin';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([Admin\GradebookOverview::class]);
    }

    public function boot(Panel $panel): void
    {
    }
}
