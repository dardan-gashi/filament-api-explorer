<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\ApiExplorerPlugin;
use DardanGashi\FilamentApiExplorer\Pages\ApiExplorerPage;
use Filament\Facades\Filament;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;

use function Pest\Livewire\livewire;

/**
 * The plugin registers its page as a configuration so it can set the slug, so
 * the page classes of a panel come from its page configurations.
 *
 * @return list<string>
 */
function registeredPageClasses(Panel $panel): array
{
	return array_values(array_map(
		fn (PageConfiguration $configuration): string => $configuration->getPage(),
		$panel->getPageConfigurations(),
	));
}

// ----------------------------------------------------------------------------------
// ApiExplorerPlugin Test Suite
// Sections: Registration, Navigation, Page Options, Authorization
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// ApiExplorerPlugin - Registration
// ------------------------------------------------------------

describe('ApiExplorerPlugin - Registration', function () {

	test('registers itself on the panel under a stable id', function () {
		expect(Filament::getPlugin(ApiExplorerPlugin::ID))->toBeInstanceOf(ApiExplorerPlugin::class)
			->and(ApiExplorerPlugin::current())->toBeInstanceOf(ApiExplorerPlugin::class);
	});

	test('registers the explorer page with the panel', function () {
		expect(registeredPageClasses(Filament::getPanel('admin')))->toContain(ApiExplorerPage::class);
	});

	test('serves the page under the configured slug', function () {
		expect(ApiExplorerPage::getUrl())->toContain('/admin/api-explorer');
	});

	test('registers its stylesheet with the panel', function () {
		Filament::getPanel('admin')->registerAssets();

		expect(FilamentAsset::getStyleHref('api-explorer', package: 'dardangashi/filament-api-explorer'))
			->toContain('api-explorer');
	});

	test('stays out of a production panel unless it is asked for', function () {
		$plugin = ApiExplorerPlugin::make();
		app()->detectEnvironment(fn (): string => 'production');

		$panel = Panel::make()->id('production');
		$plugin->register($panel);

		expect(registeredPageClasses($panel))->toBe([]);

		$plugin->enabledInProduction()->register($panel);

		expect(registeredPageClasses($panel))->toContain(ApiExplorerPage::class);
	});
});

// ------------------------------------------------------------
// ApiExplorerPlugin - Navigation
// ------------------------------------------------------------

describe('ApiExplorerPlugin - Navigation', function () {

	test('falls back to the packaged navigation label', function () {
		expect(ApiExplorerPage::getNavigationLabel())->toBe('API Explorer');
	});

	test('takes the label, group, icon and sort a panel sets', function () {
		ApiExplorerPlugin::current()
			?->navigationLabel('OpenAPI')
			->navigationGroup('Developer')
			->navigationIcon('heroicon-o-document-text')
			->navigationSort(90);

		expect(ApiExplorerPage::getNavigationLabel())->toBe('OpenAPI')
			->and(ApiExplorerPage::getNavigationGroup())->toBe('Developer')
			->and(ApiExplorerPage::getNavigationIcon())->toBe('heroicon-o-document-text')
			->and(ApiExplorerPage::getNavigationSort())->toBe(90);
	});

	test('badges the endpoint count', function () {
		ApiExplorerPlugin::current()?->navigationBadge('count');

		expect(ApiExplorerPage::getNavigationBadge())->toBe('7');
	});

	test('badges the documented share', function () {
		ApiExplorerPlugin::current()?->navigationBadge('coverage');

		expect(ApiExplorerPage::getNavigationBadge())->toBe('57%');
	});

	test('badges the api version', function () {
		ApiExplorerPlugin::current()?->navigationBadge('version');

		expect(ApiExplorerPage::getNavigationBadge())->toBe('v2.4.1');
	});

	test('shows no badge when none is asked for', function () {
		ApiExplorerPlugin::current()?->navigationBadge(null);

		expect(ApiExplorerPage::getNavigationBadge())->toBeNull();
	});

	test('shows no badge when the specification cannot be loaded', function () {
		config()->set('filament-api-explorer.sources', [
			'v2' => ['driver' => 'file', 'path' => '/does/not/exist.json'],
		]);

		ApiExplorerPlugin::current()?->navigationBadge('count');

		expect(ApiExplorerPage::getNavigationBadge())->toBeNull();
	});
});

// ------------------------------------------------------------
// ApiExplorerPlugin - Page Options
// ------------------------------------------------------------

describe('ApiExplorerPlugin - Page Options', function () {

	test('falls back to the title of the document', function () {
		livewire(ApiExplorerPage::class)->assertSee('Bookshop API');
	});

	test('takes the title a panel sets', function () {
		ApiExplorerPlugin::current()?->title('API Documentation');

		livewire(ApiExplorerPage::class)->assertSee('API Documentation');
	});

	test('explains nothing above the fold', function () {
		// The document's own description used to be rendered as a subheading. On a
		// page opened daily, two lines of prose are only ever in the way.
		livewire(ApiExplorerPage::class)
			->assertDontSee('A catalogue and order API.');
	});

	test('uses the full page width by default and gives it up on request', function () {
		expect(app(ApiExplorerPage::class)->getMaxContentWidth())->toBe(Width::Full);

		ApiExplorerPlugin::current()?->fullWidth(false);

		expect(app(ApiExplorerPage::class)->getMaxContentWidth())->not->toBe(Width::Full);
	});

	test('opens on the source a panel chose', function () {
		config()->set('filament-api-explorer.sources', [
			'v2' => ['driver' => 'file', 'path' => __DIR__.'/../Fixtures/openapi.json'],
			'v1' => ['driver' => 'array', 'document' => ['info' => ['title' => 'legacy api']]],
		]);

		ApiExplorerPlugin::current()?->source('v1');

		livewire(ApiExplorerPage::class)
			->assertSet('source', 'v1')
			->assertSee('legacy api');
	});

	test('turns the request sender off on request', function () {
		ApiExplorerPlugin::current()?->requestSending(false);

		livewire(ApiExplorerPage::class)->assertSee('Sending requests is disabled');
	});
});

// ------------------------------------------------------------
// ApiExplorerPlugin - Authorization
// ------------------------------------------------------------

describe('ApiExplorerPlugin - Authorization', function () {

	test('allows access when no rule was given', function () {
		expect(ApiExplorerPage::canAccess())->toBeTrue();
	});

	test('defers to the rule a panel gave', function () {
		ApiExplorerPlugin::current()?->authorizeUsing(fn (): bool => false);

		expect(ApiExplorerPage::canAccess())->toBeFalse();

		ApiExplorerPlugin::current()?->authorizeUsing(fn (): bool => true);

		expect(ApiExplorerPage::canAccess())->toBeTrue();
	});
});
