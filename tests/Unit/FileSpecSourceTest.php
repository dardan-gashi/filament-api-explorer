<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;
use DardanGashi\FilamentApiExplorer\Sources\ArraySpecSource;
use DardanGashi\FilamentApiExplorer\Sources\FileSpecSource;

// ----------------------------------------------------------------------------------
// FileSpecSource Test Suite
// Sections: document, generatedAt, exists, ArraySpecSource
// ----------------------------------------------------------------------------------

beforeEach(function () {
	$this->directory = sys_get_temp_dir().'/fae-'.bin2hex(random_bytes(4));
	mkdir($this->directory);
});

afterEach(function () {
	foreach (glob($this->directory.'/*') ?: [] as $file) {
		unlink($file);
	}

	rmdir($this->directory);
});

function writeSpec(string $directory, string $name, string $contents): string
{
	$path = $directory.'/'.$name;
	file_put_contents($path, $contents);

	return $path;
}

// ------------------------------------------------------------
// FileSpecSource - document
// ------------------------------------------------------------

describe('FileSpecSource - document', function () {

	test('reads a json document', function () {
		$path = writeSpec($this->directory, 'openapi.json', '{"info":{"title":"Bookshop API"}}');

		expect((new FileSpecSource('v2', $path))->document())->toBe(['info' => ['title' => 'Bookshop API']]);
	});

	test('reads a yaml document', function (string $extension) {
		$path = writeSpec($this->directory, "openapi.{$extension}", "info:\n  title: Bookshop API\n");

		expect((new FileSpecSource('v2', $path))->document())->toBe(['info' => ['title' => 'Bookshop API']]);
	})->with([['yaml'], ['yml']]);

	test('reports a document that is not there', function () {
		(new FileSpecSource('v2', $this->directory.'/missing.json'))->document();
	})->throws(SpecUnavailable::class, 'No OpenAPI document at');

	test('reports a document in a format it cannot read', function () {
		$path = writeSpec($this->directory, 'openapi.xml', '<openapi/>');

		(new FileSpecSource('v2', $path))->document();
	})->throws(SpecUnavailable::class, '.json, .yaml or .yml');

	test('reports json it cannot parse', function () {
		$path = writeSpec($this->directory, 'openapi.json', '{"info":');

		(new FileSpecSource('v2', $path))->document();
	})->throws(SpecUnavailable::class, 'could not be parsed');

	test('reports yaml it cannot parse', function () {
		$path = writeSpec($this->directory, 'openapi.yaml', "info:\n\ttitle: tabs are invalid\n");

		(new FileSpecSource('v2', $path))->document();
	})->throws(SpecUnavailable::class, 'could not be parsed');

	test('reports a document that is not an object', function () {
		$path = writeSpec($this->directory, 'openapi.json', '"just a string"');

		(new FileSpecSource('v2', $path))->document();
	})->throws(SpecUnavailable::class, 'not an object');
});

// ------------------------------------------------------------
// FileSpecSource - generatedAt
// ------------------------------------------------------------

describe('FileSpecSource - generatedAt', function () {

	test('reports when the document was last written', function () {
		$path = writeSpec($this->directory, 'openapi.json', '{}');
		touch($path, CarbonImmutable::parse('2026-08-17 09:14:00')->getTimestamp());

		expect((new FileSpecSource('v2', $path))->generatedAt()?->format('Y-m-d H:i'))
			->toBe('2026-08-17 09:14');
	});

	test('reports nothing for a document that is not there', function () {
		expect((new FileSpecSource('v2', $this->directory.'/missing.json'))->generatedAt())->toBeNull();
	});
});

// ------------------------------------------------------------
// FileSpecSource - exists
// ------------------------------------------------------------

describe('FileSpecSource - exists', function () {

	test('reports whether the document is on disk', function () {
		$path = writeSpec($this->directory, 'openapi.json', '{}');

		expect((new FileSpecSource('v2', $path))->exists())->toBeTrue()
			->and((new FileSpecSource('v2', $this->directory.'/missing.json'))->exists())->toBeFalse()
			->and((new FileSpecSource('v2', $path))->path())->toBe($path);
	});
});

// ------------------------------------------------------------
// ArraySpecSource
// ------------------------------------------------------------

describe('ArraySpecSource - document', function () {

	test('hands back the document it was built with', function () {
		$source = new ArraySpecSource('memory', ['info' => ['title' => 'in memory']]);

		expect($source->name())->toBe('memory')
			->and($source->document())->toBe(['info' => ['title' => 'in memory']])
			->and($source->generatedAt())->toBeNull();
	});
});
