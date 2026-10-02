<?php

namespace Modules\QuizoraGradebook;

use Modules\QuizoraGradebook\Filament\AdminPlugin;
use Modules\QuizoraGradebook\Filament\CreatorPlugin;
use Pacific\Licentra\Modules\Laravel\ModuleServiceProvider as BaseProvider;

/**
 * Gradebook (free add-on). Reads Quizora's attempts; its only table holds report-card links.
 * Creator panel: Gradebook page. Admin panel: usage overview. Public: /quizora-gradebook/report/{token}.
 */
class ModuleServiceProvider extends BaseProvider
{
    public function bootModule(): void
    {
        $this->loadModuleResources();
        $this->loadModuleRoutes('routes/web.php', ['web']);
    }

    public function filamentPlugins(string $panel): array
    {
        return match ($panel) {
            'creator' => [CreatorPlugin::make()],
            'admin' => [AdminPlugin::make()],
            default => [],
        };
    }
}
