<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Scramble;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the facts about an endpoint that live in the routing table, not in the
 * OpenAPI schema: which action answers it, how often it may be called, and which
 * token abilities it insists on.
 *
 * These are the things a reader of the reference asks straight away and would
 * otherwise have to look up in `routes/api.php`. OpenAPI has no field for any of
 * them, so they travel as vendor extensions — `x-handler`, `x-rate-limit`,
 * `x-abilities` — which is what this package renders as the captions under an
 * endpoint title. The side that writes them and the side that reads them belong
 * together, which is why this ships here rather than in every host application.
 */
final class EndpointFacts implements OperationTransformer
{
	public function handle(Operation $operation, RouteInfo $routeInfo): void
	{
		if ($routeInfo->isClassBased()) {
			$operation->setExtensionProperty(
				'handler',
				class_basename((string) $routeInfo->className()).'@'.$routeInfo->methodName(),
			);
		}

		foreach ([
			'rate-limit' => $this->rateLimit($routeInfo->route),
			'abilities' => $this->abilities($routeInfo->route),
		] as $key => $fact) {
			if ($fact !== null) {
				$operation->setExtensionProperty($key, $fact);
			}
		}

		$this->keepDescription($operation, $routeInfo);
	}

	/**
	 * Scramble replaces the description of an operation with the text of its
	 * `@deprecated` tag, so an endpoint loses its documentation the moment somebody
	 * marks it as going away. Both belong in the reference: what the endpoint does,
	 * then the notice about it.
	 */
	protected function keepDescription(Operation $operation, RouteInfo $routeInfo): void
	{
		if (!$operation->deprecated) {
			return;
		}

		PhpDoc::addSummaryAttributes($phpDoc = $routeInfo->phpDoc());

		$documented = trim((string) $phpDoc->getAttribute('description'));
		$notice = trim((string) $operation->description);

		if ($documented === '' || Str::contains($notice, $documented)) {
			return;
		}

		$operation->description(trim($documented."\n\n".$notice));
	}

	/**
	 * The token abilities the route insists on.
	 *
	 * OpenAPI keeps its scopes slot for OAuth flows and requires it to be empty
	 * for a bearer scheme, so the one fact that explains most 403s — a valid token
	 * without the right ability — has nowhere else to go.
	 */
	protected function abilities(Route $route): ?string
	{
		foreach ($route->gatherMiddleware() as $middleware) {
			if (!is_string($middleware) || !Str::startsWith($middleware, ['ability:', 'abilities:'])) {
				continue;
			}

			$abilities = array_filter(array_map('trim', explode(',', Str::after($middleware, ':'))));

			if ($abilities !== []) {
				return implode(' · ', $abilities);
			}
		}

		return null;
	}

	/**
	 * The throttle of a route as a person would say it: `600/min`.
	 */
	protected function rateLimit(Route $route): ?string
	{
		foreach ($route->gatherMiddleware() as $middleware) {
			if (!is_string($middleware) || !Str::startsWith($middleware, 'throttle:')) {
				continue;
			}

			$limit = $this->limit(Str::after($middleware, 'throttle:'));

			if ($limit !== null) {
				return $this->caption($limit);
			}
		}

		return null;
	}

	/**
	 * A throttle is either written into the route as `throttle:60,1` or defined
	 * elsewhere as a named limiter, and a named limiter only yields its numbers
	 * when it is asked with a request.
	 */
	protected function limit(string $parameters): ?Limit
	{
		if (preg_match('/^(\d+)(?:,(\d+))?$/', $parameters, $matches) === 1) {
			return Limit::perMinutes((int) ($matches[2] ?? 1), (int) $matches[1]);
		}

		$limiter = RateLimiter::limiter($parameters);

		if ($limiter === null) {
			return null;
		}

		try {
			$resolved = $limiter(request());
		} catch (Throwable) {
			// A limiter is free to depend on more of the request than a document
			// generator can offer. Then the reference simply says nothing about it.
			return null;
		}

		$resolved = is_array($resolved) ? ($resolved[0] ?? null) : $resolved;

		return $resolved instanceof Limit ? $resolved : null;
	}

	protected function caption(Limit $limit): string
	{
		$per = match ($limit->decaySeconds) {
			60 => 'min',
			3600 => 'h',
			86400 => 'd',
			default => $limit->decaySeconds.'s',
		};

		return $limit->maxAttempts.'/'.$per;
	}
}
