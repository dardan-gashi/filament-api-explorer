<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use DardanGashi\FilamentApiExplorer\ApiExplorerServiceProvider;
use DardanGashi\FilamentApiExplorer\Tests\Fixtures\TestPanelProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

abstract class TestCase extends Orchestra
{
	/**
	 * The order matters. Filament's support provider rebinds Livewire's
	 * `DataStore` to its own subclass with a transient binding, and Livewire's
	 * provider is what promotes that binding to a shared instance while
	 * registering its mechanisms. Registering Livewire first leaves the store
	 * transient, and every write to it is silently lost — so Livewire has to
	 * come after Filament, exactly as package discovery orders them in an app.
	 *
	 * @return list<class-string>
	 */
	protected function getPackageProviders($app): array
	{
		return [
			BladeIconsServiceProvider::class,
			BladeHeroiconsServiceProvider::class,
			BladeCaptureDirectiveServiceProvider::class,
			SupportServiceProvider::class,
			ActionsServiceProvider::class,
			FormsServiceProvider::class,
			InfolistsServiceProvider::class,
			NotificationsServiceProvider::class,
			SchemasServiceProvider::class,
			TablesServiceProvider::class,
			WidgetsServiceProvider::class,
			FilamentServiceProvider::class,
			LivewireServiceProvider::class,
			ApiExplorerServiceProvider::class,
			TestPanelProvider::class,
		];
	}

	protected function defineEnvironment($app): void
	{
		/** @var Application $app */
		$app['config']->set('app.url', 'https://api.bookshop.test');
		$app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');
		$app['config']->set('view.paths', [__DIR__.'/../resources/views']);

		$app['config']->set('filament-api-explorer.sources', [
			'v2' => [
				'driver' => 'file',
				'path' => __DIR__.'/Fixtures/openapi.json',
			],
		]);
	}
}
