<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Tests\Fixtures;

use DardanGashi\FilamentApiExplorer\Tests\TestCase;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Routing\Router;

/**
 * An application with Scramble installed and a couple of routes to describe.
 *
 * The Scramble integration is the one part of this package that reads another
 * package's API, so it is tested against the real generator rather than against
 * a stand-in for it.
 */
abstract class ScrambleTestCase extends TestCase
{
	/**
	 * @return list<class-string>
	 */
	protected function getPackageProviders($app): array
	{
		return [...parent::getPackageProviders($app), ScrambleServiceProvider::class];
	}

	protected function defineRoutes($router): void
	{
		/** @var Router $router */
		$router->middleware(['throttle:600,1', 'abilities:things:read'])
			->get('api/things', [ThingController::class, 'index']);

		$router->get('api/things/{thing}', [ThingController::class, 'show']);
		$router->delete('api/things/{thing}', [ThingController::class, 'destroy']);
	}
}
