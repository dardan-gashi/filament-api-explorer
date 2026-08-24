<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Services;

use Carbon\CarbonImmutable;
use DardanGashi\FilamentApiExplorer\Contracts\SpecSource;
use DardanGashi\FilamentApiExplorer\Data\ApiSpec;
use DardanGashi\FilamentApiExplorer\Data\BodyRendering;
use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Data\Parameter;
use DardanGashi\FilamentApiExplorer\Data\RequestBodyDefinition;
use DardanGashi\FilamentApiExplorer\Data\ResponseDefinition;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;
use DardanGashi\FilamentApiExplorer\Support\Documents;
use DardanGashi\FilamentApiExplorer\Support\ReferenceResolver;
use Illuminate\Support\Str;

/**
 * Turns a raw OpenAPI document into the value objects the explorer renders.
 *
 * The parser is deliberately forgiving: anything it cannot make sense of is
 * left out rather than raised, because a documentation page that renders most
 * of a specification is more useful than one that refuses a slightly odd
 * document.
 */
final class SpecParser
{
	public const UNGROUPED = 'General';

	public function __construct(
		private readonly SchemaFieldFactory $fields,
		private readonly ExampleFactory $examples,
	) {}

	public function parse(SpecSource $source): ApiSpec
	{
		return $this->parseDocument($source->name(), $source->document(), $source->generatedAt());
	}

	/**
	 * @param  array<string, mixed>  $document
	 */
	public function parseDocument(string $name, array $document, ?CarbonImmutable $generatedAt = null): ApiSpec
	{
		$references = new ReferenceResolver($document);
		$info = Documents::map($document, 'info');

		return new ApiSpec(
			name: $name,
			title: Documents::string($info, 'title') ?? $name,
			version: Documents::string($info, 'version'),
			description: Documents::string($info, 'description'),
			servers: $this->servers($document),
			endpoints: $this->endpoints($document, $references),
			securityLabels: $this->securityLabels($document),
			generatedAt: $generatedAt,
		);
	}

	/**
	 * Server URLs with their template variables replaced by the documented
	 * defaults, so they can be used as-is.
	 *
	 * @param  array<string, mixed>  $document
	 * @return list<string>
	 */
	private function servers(array $document): array
	{
		$servers = [];

		foreach (Documents::list($document, 'servers') as $entry) {
			$server = Documents::toMap($entry);
			$url = Documents::string($server, 'url');

			if ($url === null) {
				continue;
			}

			$servers[] = $this->applyServerVariables($url, Documents::map($server, 'variables'));
		}

		return array_values(array_unique($servers));
	}

	/**
	 * @param  array<string, mixed>  $variables
	 */
	private function applyServerVariables(string $url, array $variables): string
	{
		foreach (Documents::entries($variables) as [$variable, $definition]) {
			$default = Documents::string(Documents::toMap($definition), 'default');

			if ($default !== null) {
				$url = str_replace('{'.$variable.'}', $default, $url);
			}
		}

		return $url;
	}

	/**
	 * @param  array<string, mixed>  $document
	 * @return list<Endpoint>
	 */
	private function endpoints(array $document, ReferenceResolver $references): array
	{
		$paths = Documents::map($document, 'paths');
		$schemes = Documents::map(Documents::map($document, 'components'), 'securitySchemes');
		$documentSecurity = $this->securityNames($document);

		$endpoints = [];
		$taken = [];

		foreach (Documents::entries($paths) as [$path, $pathItem]) {
			$operations = Documents::toMap($pathItem);
			$sharedParameters = Documents::list($operations, 'parameters');

			foreach (Documents::entries($operations) as [$key, $operation]) {
				$method = HttpMethod::tryFromName($key);

				if ($method === null || !is_array($operation)) {
					continue;
				}

				$definition = Documents::toMap($operation);

				$endpoints[] = $this->endpoint(
					key: $this->endpointKey($definition, $method, $path, $taken),
					method: $method,
					path: $path,
					operation: $definition,
					sharedParameters: $sharedParameters,
					documentSecurity: $documentSecurity,
					schemes: $schemes,
					references: $references,
				);

				$taken[] = $endpoints[count($endpoints) - 1]->key;
			}
		}

		return $endpoints;
	}

	/**
	 * @param  array<string, mixed>  $operation
	 * @param  list<mixed>  $sharedParameters
	 * @param  list<string>  $documentSecurity
	 * @param  array<string, mixed>  $schemes
	 */
	private function endpoint(
		string $key,
		HttpMethod $method,
		string $path,
		array $operation,
		array $sharedParameters,
		array $documentSecurity,
		array $schemes,
		ReferenceResolver $references,
	): Endpoint {
		$security = array_key_exists('security', $operation)
			? $this->securityNames($operation)
			: $documentSecurity;

		$parameters = $this->parameters(
			[...$sharedParameters, ...Documents::list($operation, 'parameters')],
			$references,
		);

		return new Endpoint(
			key: $key,
			method: $method,
			path: $path,
			summary: Documents::string($operation, 'summary'),
			description: Documents::string($operation, 'description'),
			group: $this->group($operation),
			security: $security,
			parameters: [...$this->authParameters($security, $schemes, $parameters), ...$parameters],
			requestBody: $this->requestBody(Documents::map($operation, 'requestBody'), $references),
			responses: $this->responses(Documents::map($operation, 'responses'), $references),
			deprecated: Documents::isTrue($operation, 'deprecated'),
			meta: $this->meta($operation),
		);
	}

	/**
	 * The address of an endpoint: its `operationId` where the document gives one,
	 * else its method and path.
	 *
	 * An id is unique by specification and not by enforcement, so a repeated one
	 * falls back — two endpoints answering to one address would leave the second
	 * unreachable from a link.
	 *
	 * @param  array<string, mixed>  $operation
	 * @param  list<string>  $taken
	 */
	private function endpointKey(array $operation, HttpMethod $method, string $path, array $taken): string
	{
		$id = Documents::string($operation, 'operationId');
		$key = $id === null ? null : Endpoint::keyForOperationId($id);

		return $key === null || in_array($key, $taken, true)
			? Endpoint::keyFor($method, $path)
			: $key;
	}

	/**
	 * @param  array<string, mixed>  $operation
	 */
	private function group(array $operation): string
	{
		foreach (Documents::list($operation, 'tags') as $tag) {
			if (is_string($tag) && $tag !== '') {
				return $tag;
			}
		}

		return self::UNGROUPED;
	}

	/**
	 * Vendor extensions become the captions under the endpoint title, which is
	 * how a specification can surface its handler, rate limit or since-version
	 * without this package knowing about them.
	 *
	 * @param  array<string, mixed>  $operation
	 * @return array<string, string>
	 */
	private function meta(array $operation): array
	{
		$meta = [];

		foreach (Documents::entries($operation) as [$key, $value]) {
			if (str_starts_with($key, 'x-') && is_scalar($value)) {
				$meta[Str::after($key, 'x-')] = (string) $value;
			}
		}

		return $meta;
	}

	/**
	 * @param  list<mixed>  $parameters
	 * @return list<Parameter>
	 */
	private function parameters(array $parameters, ReferenceResolver $references): array
	{
		$resolved = [];

		foreach ($parameters as $entry) {
			$parameter = $references->resolve(Documents::toMap($entry));
			$name = Documents::string($parameter, 'name');
			$in = ParameterLocation::tryFromName(Documents::string($parameter, 'in') ?? '');

			if ($name === null || $in === null) {
				continue;
			}

			$schema = Documents::map($parameter, 'schema');
			$field = $this->fields->field(name: $name, schema: $schema, references: $references);

			// Later declarations win, which is how an operation overrides a
			// parameter it shares with its path.
			$resolved[$in->value.':'.$name] = new Parameter(
				name: $name,
				in: $in,
				type: $field->type,
				required: Documents::isTrue($parameter, 'required') || $in === ParameterLocation::Path,
				description: Documents::string($parameter, 'description') ?? $field->description,
				enum: $field->enum,
				default: Documents::scalar($schema, 'default'),
				deprecated: Documents::isTrue($parameter, 'deprecated') || $field->deprecated,
				example: self::exampleValue($parameter) ?? self::exampleValue($schema),
			);
		}

		return array_values($resolved);
	}

	/**
	 * The authentication header implied by the endpoint's security schemes.
	 * Declared parameters take precedence, so a specification that documents
	 * its own `Authorization` header is left untouched.
	 *
	 * @param  list<string>  $security
	 * @param  array<string, mixed>  $schemes
	 * @param  list<Parameter>  $declared
	 * @return list<Parameter>
	 */
	private function authParameters(array $security, array $schemes, array $declared): array
	{
		$declaredHeaders = array_map(
			fn (Parameter $parameter): string => strtolower($parameter->name),
			array_filter($declared, fn (Parameter $parameter): bool => $parameter->in === ParameterLocation::Header),
		);

		$parameters = [];

		foreach ($security as $name) {
			$scheme = Documents::map($schemes, $name);
			$type = strtolower(Documents::string($scheme, 'type') ?? '');
			$header = null;
			$example = null;

			if ($type === 'http') {
				$header = 'Authorization';
				$example = match (strtolower(Documents::string($scheme, 'scheme') ?? '')) {
					'basic' => 'Basic <credentials>',
					default => 'Bearer <token>',
				};
			}

			if ($type === 'apikey' && strtolower(Documents::string($scheme, 'in') ?? '') === 'header') {
				$header = Documents::string($scheme, 'name');
				$example = '<key>';
			}

			if ($header === null || in_array(strtolower($header), $declaredHeaders, true)) {
				continue;
			}

			$declaredHeaders[] = strtolower($header);

			$parameters[] = new Parameter(
				name: $header,
				in: ParameterLocation::Header,
				required: true,
				description: Documents::string($scheme, 'description'),
				example: $example,
				inferred: true,
			);
		}

		return $parameters;
	}

	/**
	 * @param  array<string, mixed>  $responses
	 * @return list<ResponseDefinition>
	 */
	private function responses(array $responses, ReferenceResolver $references): array
	{
		$definitions = [];

		foreach (Documents::entries($responses) as [$status, $entry]) {
			$response = $references->resolve(Documents::toMap($entry));
			$bodies = Documents::map($response, 'content');
			[$mediaType, $content] = $this->preferredContent($bodies);
			$schema = Documents::map($content, 'schema');

			$schemaName = ReferenceResolver::nameOf($schema);
			$description = Documents::string($response, 'description');

			// A description that is nothing but a name is a name. Generators write
			// one where OpenAPI wants a sentence — Scramble describes a response as
			// `OrderDetailResource` — and it belongs in the slot every other
			// response puts its schema name in, not in the one holding `Not found`.
			if ($schemaName === null && $description !== null && preg_match('/^`([^`]+)`$/', $description, $matches) === 1) {
				$schemaName = $matches[1];
				$description = null;
			}

			$definitions[] = new ResponseDefinition(
				status: $status,
				description: $description,
				mediaType: $mediaType,
				schemaName: $schemaName,
				fields: $schema === [] ? [] : $this->fields->rootFields($schema, $references),
				headers: $this->responseHeaders(Documents::map($response, 'headers'), $references),
				example: $content === [] ? null : $this->examples->forMediaType($content, $references, $mediaType),
				exampleSynthesised: $content !== [] && !$this->examples->hasDocumentedExample($content, $references),
				alternates: $this->alternateRenderings($bodies, $mediaType, $references),
			);
		}

		return $definitions;
	}

	/**
	 * The body an operation expects. Absent for the methods that take none, and
	 * for the ones that should take one but do not say so — which is what the
	 * request-body gap reports.
	 *
	 * @param  array<string, mixed>  $requestBody
	 */
	private function requestBody(array $requestBody, ReferenceResolver $references): ?RequestBodyDefinition
	{
		if ($requestBody === []) {
			return null;
		}

		$body = $references->resolve($requestBody);
		$bodies = Documents::map($body, 'content');
		[$mediaType, $content] = $this->preferredContent($bodies);
		$schema = Documents::map($content, 'schema');

		return new RequestBodyDefinition(
			mediaType: $mediaType,
			schemaName: ReferenceResolver::nameOf($schema),
			fields: $schema === [] ? [] : $this->fields->rootFields($schema, $references),
			required: Documents::isTrue($body, 'required'),
			description: Documents::string($body, 'description'),
			example: $content === [] ? null : $this->examples->forMediaType($content, $references, $mediaType),
			exampleSynthesised: $content !== [] && !$this->examples->hasDocumentedExample($content, $references),
			alternates: $this->alternateRenderings($bodies, $mediaType, $references),
		);
	}

	/**
	 * A caption per security scheme. The key of a scheme is what a document uses
	 * to refer to it, and a good one names the mechanism — `sanctum`. Generators
	 * often key a scheme after its own type instead, and `http` is no caption at
	 * all, so in that case the scheme (`bearer`) or the header of an API key is
	 * the more useful label.
	 *
	 * @param  array<string, mixed>  $document
	 * @return array<string, string>
	 */
	private function securityLabels(array $document): array
	{
		$schemes = Documents::map(Documents::map($document, 'components'), 'securitySchemes');
		$labels = [];

		foreach (Documents::entries($schemes) as [$name, $entry]) {
			$scheme = Documents::toMap($entry);
			$type = Documents::string($scheme, 'type') ?? '';

			$labels[$name] = strcasecmp($name, $type) === 0
				? Documents::string($scheme, 'scheme') ?? Documents::string($scheme, 'name') ?? $name
				: $name;
		}

		return $labels;
	}

	/**
	 * The example a parameter or header declares, in either spelling: `example`
	 * where the document is 3.0, the first entry of `examples` where it is 3.1.
	 * Only a scalar is taken — an input holds one value.
	 *
	 * @param  array<string, mixed>  $source
	 */
	private static function exampleValue(array $source): string|int|float|bool|null
	{
		$scalar = Documents::scalar($source, 'example');

		if ($scalar !== null) {
			return $scalar;
		}

		$first = Documents::listFirst($source, 'examples');

		return is_scalar($first) ? $first : null;
	}

	/**
	 * Every media type of a body except the preferred one, each parsed in full:
	 * its own schema, its own example, encoded the way that type is written. XML
	 * documented beside JSON is a different body to read, not a second label on
	 * the same one.
	 *
	 * @param  array<string, mixed>  $content
	 * @return list<BodyRendering>
	 */
	private function alternateRenderings(array $content, ?string $preferred, ReferenceResolver $references): array
	{
		$renderings = [];

		foreach (Documents::entries($content) as [$mediaType, $entry]) {
			if ($mediaType === $preferred) {
				continue;
			}

			$definition = Documents::toMap($entry);
			$schema = Documents::map($definition, 'schema');

			$renderings[] = new BodyRendering(
				mediaType: $mediaType,
				schemaName: ReferenceResolver::nameOf($schema),
				fields: $schema === [] ? [] : $this->fields->rootFields($schema, $references),
				example: $this->examples->forMediaType($definition, $references, $mediaType),
				exampleSynthesised: !$this->examples->hasDocumentedExample($definition, $references),
			);
		}

		return $renderings;
	}

	/**
	 * JSON is preferred when a response is offered in several media types,
	 * because that is the format a reader is most likely after — and where the
	 * document offers more, the others are kept as alternates rather than lost.
	 *
	 * @param  array<string, mixed>  $content
	 * @return array{0: string|null, 1: array<string, mixed>}
	 */
	private function preferredContent(array $content): array
	{
		if ($content === []) {
			return [null, []];
		}

		foreach (Documents::entries($content) as [$mediaType, $definition]) {
			if (Str::contains($mediaType, 'json')) {
				return [$mediaType, Documents::toMap($definition)];
			}
		}

		[$mediaType, $definition] = Documents::entries($content)[0];

		return [$mediaType, Documents::toMap($definition)];
	}

	/**
	 * @param  array<string, mixed>  $headers
	 * @return list<Parameter>
	 */
	private function responseHeaders(array $headers, ReferenceResolver $references): array
	{
		$parameters = [];

		foreach (Documents::entries($headers) as [$name, $entry]) {
			$header = $references->resolve(Documents::toMap($entry));
			$schema = Documents::map($header, 'schema');
			$field = $this->fields->field(name: $name, schema: $schema, references: $references);

			$parameters[] = new Parameter(
				name: $name,
				in: ParameterLocation::Header,
				type: $field->type,
				required: Documents::isTrue($header, 'required'),
				description: Documents::string($header, 'description') ?? $field->description,
				enum: $field->enum,
				deprecated: Documents::isTrue($header, 'deprecated'),
				example: self::exampleValue($header) ?? self::exampleValue($schema),
			);
		}

		return $parameters;
	}

	/**
	 * @param  array<string, mixed>  $source
	 * @return list<string>
	 */
	private function securityNames(array $source): array
	{
		$names = [];

		foreach (Documents::list($source, 'security') as $requirement) {
			foreach (Documents::keys(Documents::toMap($requirement)) as $name) {
				if ($name !== '') {
					$names[] = $name;
				}
			}
		}

		return array_values(array_unique($names));
	}
}
