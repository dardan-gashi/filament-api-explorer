<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Sources\ScrambleSpecSource;
use Dedoc\Scramble\CacheableGenerator;

// ----------------------------------------------------------------------------------
// EndpointFacts Test Suite
// Sections: handle
// ----------------------------------------------------------------------------------

/**
 * One operation of the document Scramble generates for the test application.
 *
 * Generating it costs about a second, so it is generated once and read many
 * times. It depends on the routes and on nothing a test changes.
 *
 * @return array<string, mixed>
 */
function operation(string $path, string $method = 'get'): array
{
	static $document = null;

	$document ??= (new ScrambleSpecSource('api', app(CacheableGenerator::class)))->document();

	expect($document['paths'][$path][$method] ?? null)->toBeArray();

	/** @var array<string, mixed> $operation */
	$operation = $document['paths'][$path][$method];

	return $operation;
}

// ------------------------------------------------------------
// EndpointFacts - handle
// ------------------------------------------------------------

describe('EndpointFacts - handle', function () {

	test('names the action that answers the endpoint', function () {
		expect(operation('/things')['x-handler'])->toBe('ThingController@index');
	});

	test('says the throttle the way a person would', function () {
		expect(operation('/things')['x-rate-limit'])->toBe('600/min');
	});

	test('names the abilities the route insists on', function () {
		// A valid token without the right ability is the most common 403 there is,
		// and OpenAPI keeps its scopes slot empty for a bearer scheme.
		expect(operation('/things')['x-abilities'])->toBe('things:read');
	});

	test('says nothing about a route that carries neither', function () {
		expect(operation('/things/{thing}'))->not->toHaveKey('x-rate-limit')
			->and(operation('/things/{thing}'))->not->toHaveKey('x-abilities');
	});

	test('keeps the documentation of an endpoint that is going away', function () {
		// Scramble replaces the description with the text of the `@deprecated` tag,
		// so an endpoint loses its documentation the moment somebody marks it. Both
		// belong in a reference: what it does, then the notice about it.
		$operation = operation('/things/{thing}', 'delete');

		expect($operation['deprecated'])->toBeTrue()
			->and($operation['description'])->toBe("Deleting is permanent and cannot be undone.\n\nArchive it instead.");
	});
});
