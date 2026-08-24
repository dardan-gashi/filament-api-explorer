<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Data;

use Carbon\CarbonImmutable;

/**
 * A parsed OpenAPI document: the API's identity plus every operation in it.
 */
final readonly class ApiSpec
{
	/**
	 * @param  string  $name  The configured source key, e.g. `v2`.
	 * @param  list<string>  $servers
	 * @param  list<Endpoint>  $endpoints
	 * @param  array<string, string>  $securityLabels  Scheme name to the caption worth showing for it.
	 * @param  CarbonImmutable|null  $generatedAt  When the document was written, shown as the snapshot time.
	 */
	public function __construct(
		public string $name,
		public string $title,
		public ?string $version = null,
		public ?string $description = null,
		public array $servers = [],
		public array $endpoints = [],
		public array $securityLabels = [],
		public ?CarbonImmutable $generatedAt = null,
	) {}

	public static function empty(string $name = 'default'): self
	{
		return new self(name: $name, title: $name);
	}

	public function coverage(): Coverage
	{
		return Coverage::forEndpoints($this->endpoints);
	}

	public function find(?string $key): ?Endpoint
	{
		if (blank($key)) {
			return null;
		}

		foreach ($this->endpoints as $endpoint) {
			if ($endpoint->key === $key) {
				return $endpoint;
			}
		}

		// A link written before the document named its operations, or before it
		// named this one: the address was the method and the path, which is still
		// what an operation without an id is keyed by.
		foreach ($this->endpoints as $endpoint) {
			if (Endpoint::keyFor($endpoint->method, $endpoint->path) === $key) {
				return $endpoint;
			}
		}

		return null;
	}

	public function firstEndpoint(): ?Endpoint
	{
		return $this->endpoints[0] ?? null;
	}

	public function endpointCount(): int
	{
		return count($this->endpoints);
	}

	public function defaultServer(): ?string
	{
		return $this->servers[0] ?? null;
	}

	/**
	 * What to call a security scheme on screen. Generators like to name a scheme
	 * after its own type — a scheme keyed `http` tells a reader nothing — so the
	 * parser works out a caption and this falls back to the key.
	 */
	public function securityLabel(string $name): string
	{
		return $this->securityLabels[$name] ?? $name;
	}

	/**
	 * The leading path segments every endpoint shares, e.g. `/api/v2`. The
	 * sidebar trims it so the list reads as the API's own shape rather than
	 * repeating a prefix on every row. The last segment of a path is never
	 * consumed, so each entry keeps a name.
	 */
	public function commonPathPrefix(): string
	{
		$shared = null;

		foreach ($this->endpoints as $endpoint) {
			$segments = array_values(array_filter(explode('/', $endpoint->path), fn (string $segment): bool => $segment !== ''));
			array_pop($segments);

			if ($shared === null) {
				$shared = $segments;

				continue;
			}

			$common = [];

			foreach ($shared as $index => $segment) {
				if (($segments[$index] ?? null) !== $segment) {
					break;
				}

				$common[] = $segment;
			}

			$shared = $common;
		}

		return $shared === null || $shared === [] ? '' : '/'.implode('/', $shared);
	}

	/**
	 * The version prefixed with `v`, ready for the badge and the picker.
	 */
	public function versionLabel(): ?string
	{
		if (blank($this->version)) {
			return null;
		}

		return str_starts_with($this->version, 'v') ? $this->version : 'v'.$this->version;
	}
}
