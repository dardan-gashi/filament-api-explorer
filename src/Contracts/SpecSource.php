<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Contracts;

use Carbon\CarbonImmutable;
use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;

/**
 * Supplies one raw OpenAPI document. Implement this to read a specification
 * from somewhere other than the local filesystem — a generator, an object
 * store, an HTTP endpoint — and register it with the source manager.
 */
interface SpecSource
{
	/**
	 * The key this source is registered under, e.g. `v2`.
	 */
	public function name(): string;

	/**
	 * The decoded OpenAPI document.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws SpecUnavailable
	 */
	public function document(): array;

	/**
	 * When the document was last written, shown as the snapshot time and used
	 * to key the parsed-specification cache.
	 */
	public function generatedAt(): ?CarbonImmutable;
}
