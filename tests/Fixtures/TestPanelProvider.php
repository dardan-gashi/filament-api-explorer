<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Tests\Fixtures;

use DardanGashi\FilamentApiExplorer\ApiExplorerPlugin;
use Filament\Panel;
use Filament\PanelProvider;

final class TestPanelProvider extends PanelProvider
{
	public function panel(Panel $panel): Panel
	{
		return $panel
			->default()
			->id('admin')
			->path('admin')
			->plugin(ApiExplorerPlugin::make());
	}
}
