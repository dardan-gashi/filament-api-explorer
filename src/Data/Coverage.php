<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

/**
 * How much of the API is fully documented — the figure in the page header.
 */
final readonly class Coverage
{
	public function __construct(
		public int $documented,
		public int $total,
	) {}

	/**
	 * @param  list<Endpoint>  $endpoints
	 */
	public static function forEndpoints(array $endpoints): self
	{
		$documented = array_filter($endpoints, fn (Endpoint $endpoint): bool => $endpoint->isDocumented());

		return new self(
			documented: count($documented),
			total: count($endpoints),
		);
	}

	/**
	 * The rounded share of documented endpoints; an empty API counts as 100 %
	 * so the badge never reads as a failure before any endpoint exists.
	 */
	public function percentage(): int
	{
		if ($this->total === 0) {
			return 100;
		}

		return (int) round($this->documented / $this->total * 100);
	}

	public function isComplete(): bool
	{
		return $this->documented === $this->total;
	}

	public function gapCount(): int
	{
		return $this->total - $this->documented;
	}

	/**
	 * The Filament colour name for the coverage badge.
	 */
	public function color(): string
	{
		return match (true) {
			$this->percentage() >= 90 => 'success',
			$this->percentage() >= 60 => 'warning',
			default => 'danger',
		};
	}
}
