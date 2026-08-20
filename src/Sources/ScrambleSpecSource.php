<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Sources;

use Carbon\CarbonImmutable;
use DardanGashi\FilamentApiExplorer\Contracts\SpecSource;
use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;
use DardanGashi\FilamentApiExplorer\Support\Documents;
use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Scramble;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Feeds the API explorer straight out of Scramble, so the reference describes
 * the routes that are actually registered instead of an exported snapshot that
 * can silently go stale.
 */
final class ScrambleSpecSource implements SpecSource
{
	private ?CarbonImmutable $newestChange = null;

	private bool $scanned = false;

	/**
	 * @param  list<string>  $watchPaths  Directories whose PHP files shape the document.
	 */
	public function __construct(
		private readonly string $name,
		private readonly CacheableGenerator $generator,
		private readonly string $api = Scramble::DEFAULT_API,
		private readonly array $watchPaths = [],
	) {}

	public function name(): string
	{
		return $this->name;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function document(): array
	{
		try {
			$document = ($this->generator)(Scramble::getGeneratorConfig($this->api));
		} catch (Throwable $exception) {
			throw new SpecUnavailable(
				"Scramble could not generate the [{$this->api}] document: {$exception->getMessage()}",
				previous: $exception,
			);
		}

		return Documents::toMap($document);
	}

	/**
	 * When the described code last changed.
	 *
	 * Scramble rebuilds the document by analysing every route, which costs about
	 * a second — far too much for a page that re-renders on every click. The
	 * explorer caches the parsed specification, but only for a source that can
	 * date its document, so this dates it by what the document is derived from:
	 * the newest modification time among the watched paths. Editing a controller
	 * invalidates the cache on its own, a deployment that changes nothing keeps
	 * serving from it, and scanning a few hundred files costs about 3 ms.
	 *
	 * It doubles as an honest snapshot time in the page header, because that is
	 * exactly what it is.
	 */
	public function generatedAt(): ?CarbonImmutable
	{
		if ($this->scanned) {
			return $this->newestChange;
		}

		$this->scanned = true;
		$paths = array_values(array_filter($this->watchPaths, 'is_dir'));

		if ($paths === []) {
			return $this->newestChange = null;
		}

		$newest = 0;

		foreach (Finder::create()->files()->in($paths)->name('*.php') as $file) {
			$newest = max($newest, (int) $file->getMTime());
		}

		return $this->newestChange = $newest === 0
			? null
			: CarbonImmutable::createFromTimestamp($newest);
	}
}
