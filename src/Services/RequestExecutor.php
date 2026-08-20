<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use DardanGashi\FilamentApiExplorer\Data\ExecutedRequest;
use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Exceptions\RequestNotAllowed;
use DardanGashi\FilamentApiExplorer\Support\ExecutionPolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Sends a request the explorer built and reports what came back.
 *
 * Redirects are not followed: a redirect could point at a host the policy would
 * have refused, and the reader of an API reference is better served by seeing
 * the `30x` that the API actually returned.
 */
final class RequestExecutor
{
	public function __construct(
		private readonly HttpFactory $http,
		private readonly ExecutionPolicy $policy,
		private readonly int $timeout = 10,
	) {}

	/**
	 * @throws RequestNotAllowed when the policy refuses the request
	 */
	public function send(RequestBlueprint $blueprint): ExecutedRequest
	{
		$blueprint = $blueprint->withoutBlanks();

		$this->policy->authorize($blueprint);

		$startedAt = microtime(true);

		try {
			$response = $this->http
				->withHeaders($blueprint->headers)
				->withoutRedirecting()
				->timeout($this->timeout)
				->send(strtoupper($blueprint->method->value), $blueprint->fullUrl());
		} catch (ConnectionException $exception) {
			return ExecutedRequest::failed($exception->getMessage(), $this->elapsedMs($startedAt));
		}

		return new ExecutedRequest(
			status: $response->status(),
			body: $response->body(),
			headers: $this->headers($response->headers()),
			durationMs: $this->elapsedMs($startedAt),
		);
	}

	/**
	 * @param  array<string, array<int, string>>  $headers
	 * @return array<string, string>
	 */
	private function headers(array $headers): array
	{
		return array_map(
			fn (array $values): string => implode(', ', $values),
			$headers,
		);
	}

	private function elapsedMs(float $startedAt): int
	{
		return (int) round((microtime(true) - $startedAt) * 1000);
	}
}
