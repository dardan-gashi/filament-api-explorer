<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;
use DardanGashi\FilamentApiExplorer\Sources\ScrambleSpecSource;
use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\GeneratorConfig;

// ----------------------------------------------------------------------------------
// ScrambleSpecSource Test Suite
// Sections: name, document, generatedAt
// ----------------------------------------------------------------------------------

/**
 * A generator that fails the way analysis fails: somewhere deep, on one route.
 */
final class BrokenGenerator extends CacheableGenerator
{
	/**
	 * @return array<mixed, mixed>
	 */
	public function __invoke(?GeneratorConfig $config = null): array
	{
		throw new RuntimeException('analysis exploded');
	}
}

function watched(string ...$files): string
{
	$directory = sys_get_temp_dir().'/fae-watch-'.uniqid();
	mkdir($directory);

	foreach ($files === [] ? ['a.php'] : $files as $name) {
		file_put_contents($directory.'/'.$name, '<?php');
	}

	return $directory;
}

// ------------------------------------------------------------
// ScrambleSpecSource - name
// ------------------------------------------------------------

describe('ScrambleSpecSource - name', function () {

	test('reports the name it was configured under', function () {
		expect((new ScrambleSpecSource('api', app(CacheableGenerator::class)))->name())->toBe('api');
	});
});

// ------------------------------------------------------------
// ScrambleSpecSource - document
// ------------------------------------------------------------

describe('ScrambleSpecSource - document', function () {

	test('describes the routes that are registered', function () {
		// The point of generating rather than reading a file: an export nobody
		// re-ran describes the API of the day it was written.
		$document = (new ScrambleSpecSource('api', app(CacheableGenerator::class)))->document();

		expect($document)->toHaveKey('openapi')
			->and(array_keys($document['paths']))->toBe(['/things', '/things/{thing}']);
	});

	test('reports a generator that failed as an unavailable specification', function () {
		// A page that cannot show a document has one job left: say why. An
		// exception out of static analysis is not an answer anybody can read.
		$source = new ScrambleSpecSource('api', new BrokenGenerator(app(Generator::class)));

		expect(fn () => $source->document())
			->toThrow(SpecUnavailable::class, 'Scramble could not generate the [default] document: analysis exploded');
	});
});

// ------------------------------------------------------------
// ScrambleSpecSource - generatedAt
// ------------------------------------------------------------

describe('ScrambleSpecSource - generatedAt', function () {

	test('dates the document by the newest file it is derived from', function () {
		// Generating costs about a second, so the parsed document is cached — and a
		// cache needs to know when what it describes last changed. Nothing dates a
		// generated document except the code it was generated from.
		$directory = watched('old.php', 'new.php');
		touch($directory.'/old.php', 1_600_000_000);
		touch($directory.'/new.php', 1_700_000_000);

		$source = new ScrambleSpecSource('api', app(CacheableGenerator::class), watchPaths: [$directory]);

		expect($source->generatedAt()?->getTimestamp())->toBe(1_700_000_000);
	});

	test('scans once and answers from what it found', function () {
		// The page asks on every render, and a scan per render is a scan too many.
		$directory = watched('a.php');
		touch($directory.'/a.php', 1_600_000_000);

		$source = new ScrambleSpecSource('api', app(CacheableGenerator::class), watchPaths: [$directory]);
		$first = $source->generatedAt();

		touch($directory.'/a.php', 1_700_000_000);

		expect($source->generatedAt())->toEqual($first);
	});

	test('dates nothing when it watches nothing', function () {
		// Then the specification is not cached across requests, which is the safe
		// way round: a stale reference is worse than a slow one.
		$source = new ScrambleSpecSource('api', app(CacheableGenerator::class));

		expect($source->generatedAt())->toBeNull();
	});

	test('ignores a path that is not there', function () {
		$source = new ScrambleSpecSource('api', app(CacheableGenerator::class), watchPaths: ['/does/not/exist']);

		expect($source->generatedAt())->toBeNull();
	});
});
