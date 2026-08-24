<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Data\Parameter;
use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;

/**
 * Assembles the blueprint that both the code samples and the live sender use,
 * so what a user copies is what the explorer would send.
 */
final class RequestBlueprintFactory
{
	/**
	 * @param  array<string, string>  $pathParameters
	 * @param  array<string, string>  $queryParameters
	 * @param  array<string, string>  $headers
	 */
	public function make(
		Endpoint $endpoint,
		string $server,
		array $pathParameters = [],
		array $queryParameters = [],
		array $headers = [],
		?string $accept = null,
	): RequestBlueprint {
		return new RequestBlueprint(
			method: $endpoint->method,
			url: $this->url($endpoint, $server, $pathParameters),
			query: $this->only($queryParameters, $endpoint, ParameterLocation::Query),
			headers: $this->withAccept($this->headers($headers, $endpoint), $endpoint, $accept),
		);
	}

	/**
	 * Ask for the media type the endpoint documents.
	 *
	 * A client that sends no `Accept` gets whatever the server thinks a browser
	 * wants, and for a Laravel API that is an HTML page: an expired token comes
	 * back as a redirect to a login page instead of as a 401, and where no such
	 * page exists the framework fails while building the redirect and answers
	 * 500. Asking for JSON is what any real client does, so the sample and the
	 * live request both do it — unless the document declares its own `Accept`.
	 *
	 * @param  array<string, string>  $headers
	 * @param  string|null  $accept  The format the reader chose, where the endpoint documents more than one.
	 * @return array<string, string>
	 */
	private function withAccept(array $headers, Endpoint $endpoint, ?string $accept = null): array
	{
		foreach (array_keys($headers) as $name) {
			if (strcasecmp($name, 'Accept') === 0) {
				return $headers;
			}
		}

		return ['Accept' => $this->acceptable($endpoint, $accept) ?? 'application/json', ...$headers];
	}

	/**
	 * A chosen format is asked for only where a response is documented in it.
	 * The media types of an endpoint include the ones its request body is sent
	 * as — `multipart/form-data` is a way to send and no way to be answered, and
	 * asking for it would earn a 406 from a correct server.
	 */
	private function acceptable(Endpoint $endpoint, ?string $accept): ?string
	{
		if ($accept !== null) {
			foreach ($endpoint->responses as $response) {
				if (in_array($accept, $response->mediaTypes(), true)) {
					return $accept;
				}
			}
		}

		return $endpoint->primaryResponse()->mediaType ?? null;
	}

	/**
	 * The values a request panel starts with: each documented parameter's
	 * example, default or first allowed value.
	 *
	 * @return array<string, string>
	 */
	public function suggestions(Endpoint $endpoint, ParameterLocation $in): array
	{
		$values = [];

		foreach ($endpoint->parametersIn($in) as $parameter) {
			$values[$parameter->name] = $parameter->suggestedValue() ?? '';
		}

		return $values;
	}

	/**
	 * Path placeholders are replaced where a value is known and left in place
	 * where it is not, so an untouched sample still reads as a template.
	 *
	 * @param  array<string, string>  $pathParameters
	 */
	private function url(Endpoint $endpoint, string $server, array $pathParameters): string
	{
		$path = $endpoint->path;

		foreach ($endpoint->parametersIn(ParameterLocation::Path) as $parameter) {
			$value = trim($pathParameters[$parameter->name] ?? '');

			if ($value !== '') {
				$path = str_replace('{'.$parameter->name.'}', rawurlencode($value), $path);
			}
		}

		return rtrim($server, '/').'/'.ltrim($path, '/');
	}

	/**
	 * Header values, each with the scheme its documentation prescribes.
	 *
	 * @param  array<string, string>  $values
	 * @return array<string, string>
	 */
	private function headers(array $values, Endpoint $endpoint): array
	{
		$headers = [];

		foreach ($endpoint->parametersIn(ParameterLocation::Header) as $parameter) {
			$value = $parameter->withScheme(trim($values[$parameter->name] ?? ''));

			if ($value !== '') {
				$headers[$parameter->name] = $value;
			}
		}

		return $headers;
	}

	/**
	 * Keep only the values that belong to a documented parameter of the given
	 * location, so nothing a stale form left behind reaches the request.
	 *
	 * @param  array<string, string>  $values
	 * @return array<string, string>
	 */
	private function only(array $values, Endpoint $endpoint, ParameterLocation $in): array
	{
		$documented = array_map(
			fn (Parameter $parameter): string => $parameter->name,
			$endpoint->parametersIn($in),
		);

		$filtered = [];

		foreach ($documented as $name) {
			$value = trim($values[$name] ?? '');

			if ($value !== '') {
				$filtered[$name] = $value;
			}
		}

		return $filtered;
	}
}
