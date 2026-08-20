<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Contracts\SpecSource;
use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;
use DardanGashi\FilamentApiExplorer\Sources\ArraySpecSource;
use DardanGashi\FilamentApiExplorer\Sources\FileSpecSource;
use DardanGashi\FilamentApiExplorer\Sources\SpecSourceManager;

// ----------------------------------------------------------------------------------
// SpecSourceManager Test Suite
// Sections: names, defaultName, source, extend
// ----------------------------------------------------------------------------------

function manager(): SpecSourceManager
{
	return new SpecSourceManager([
		'v2' => ['driver' => 'file', 'path' => '/tmp/v2.json'],
		'v1' => ['driver' => 'array', 'document' => ['info' => ['title' => 'v1 api']]],
	]);
}

// ------------------------------------------------------------
// SpecSourceManager - names
// ------------------------------------------------------------

describe('SpecSourceManager - names', function () {

	test('lists the configured sources in configuration order', function () {
		expect(manager()->names())->toBe(['v2', 'v1']);
	});

	test('reports whether a source is configured', function () {
		expect(manager()->has('v1'))->toBeTrue()
			->and(manager()->has('v3'))->toBeFalse();
	});
});

// ------------------------------------------------------------
// SpecSourceManager - defaultName
// ------------------------------------------------------------

describe('SpecSourceManager - defaultName', function () {

	test('takes the first configured source', function () {
		expect(manager()->defaultName())->toBe('v2');
	});

	test('reports nothing when nothing is configured', function () {
		expect((new SpecSourceManager([]))->defaultName())->toBeNull();
	});
});

// ------------------------------------------------------------
// SpecSourceManager - source
// ------------------------------------------------------------

describe('SpecSourceManager - source', function () {

	test('builds a file source', function () {
		$source = manager()->source('v2');

		expect($source)->toBeInstanceOf(FileSpecSource::class)
			->and($source->name())->toBe('v2');
	});

	test('builds an in-memory source', function () {
		$source = manager()->source('v1');

		expect($source)->toBeInstanceOf(ArraySpecSource::class)
			->and($source->document())->toBe(['info' => ['title' => 'v1 api']]);
	});

	test('falls back to the default source', function () {
		expect(manager()->source()->name())->toBe('v2');
	});

	test('builds each source only once', function () {
		$subject = manager();

		expect($subject->source('v2'))->toBe($subject->source('v2'));
	});

	test('reports a source that is not configured', function () {
		manager()->source('v3');
	})->throws(SpecUnavailable::class, 'No OpenAPI source named [v3]');

	test('reports that nothing is configured at all', function () {
		(new SpecSourceManager([]))->source();
	})->throws(SpecUnavailable::class);

	test('reports a driver it does not know', function () {
		(new SpecSourceManager(['v2' => ['driver' => 'telepathy']]))->source('v2');
	})->throws(SpecUnavailable::class, 'No OpenAPI source driver named [telepathy]');

	test('treats a source without a driver as a file', function () {
		expect((new SpecSourceManager(['v2' => ['path' => '/tmp/v2.json']]))->source('v2'))
			->toBeInstanceOf(FileSpecSource::class);
	});
});

// ------------------------------------------------------------
// SpecSourceManager - extend
// ------------------------------------------------------------

describe('SpecSourceManager - extend', function () {

	test('accepts a driver of its own', function () {
		$subject = (new SpecSourceManager(['generated' => ['driver' => 'generator', 'title' => 'Generated']]))
			->extend('generator', fn (string $name, array $config): SpecSource => new ArraySpecSource(
				$name,
				['info' => ['title' => $config['title'] ?? '']],
			));

		expect($subject->source('generated')->document())->toBe(['info' => ['title' => 'Generated']]);
	});

	test('lets a new driver replace a built-in one', function () {
		$subject = (new SpecSourceManager(['v2' => ['driver' => 'file', 'path' => '/tmp/v2.json']]))
			->extend('file', fn (string $name): SpecSource => new ArraySpecSource($name, []));

		expect($subject->source('v2'))->toBeInstanceOf(ArraySpecSource::class);
	});
});
