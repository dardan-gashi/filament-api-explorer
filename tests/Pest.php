<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Data\Parameter;
use DardanGashi\FilamentApiExplorer\Data\RequestBodyDefinition;
use DardanGashi\FilamentApiExplorer\Data\ResponseDefinition;
use DardanGashi\FilamentApiExplorer\Data\SchemaField;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Services\ExampleFactory;
use DardanGashi\FilamentApiExplorer\Services\SchemaFieldFactory;
use DardanGashi\FilamentApiExplorer\Services\SpecParser;
use DardanGashi\FilamentApiExplorer\Support\ReferenceResolver;
use DardanGashi\FilamentApiExplorer\Tests\Fixtures\ScrambleTestCase;
use DardanGashi\FilamentApiExplorer\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

// The Scramble integration needs that package booted and a couple of routes to
// describe, which is a different application than the rest of the suite runs in.
pest()->extend(ScrambleTestCase::class)->in('Scramble');

/**
 * A parser wired up the way the container wires it, for tests that do not need
 * a booted application.
 */
function parser(int $maxDepth = 6): SpecParser
{
	return new SpecParser(new SchemaFieldFactory($maxDepth), new ExampleFactory($maxDepth));
}

/**
 * @param  array<string, mixed>  $document
 */
function references(array $document = []): ReferenceResolver
{
	return new ReferenceResolver($document);
}

/**
 * The fixture document, which mirrors the shape of a Scramble-generated
 * specification: tagged operations, `$ref`ed resources and documented headers.
 *
 * @return array<string, mixed>
 */
function fixtureDocument(): array
{
	/** @var array<string, mixed> */
	return json_decode((string) file_get_contents(__DIR__.'/Fixtures/openapi.json'), associative: true);
}

/**
 * @param  list<Parameter>  $parameters
 * @param  list<ResponseDefinition>  $responses
 */
function endpoint(
	HttpMethod $method = HttpMethod::Get,
	string $path = '/api/v2/books',
	?string $summary = 'Lists books',
	string $group = 'Books',
	array $parameters = [],
	array $responses = [],
	?RequestBodyDefinition $requestBody = null,
): Endpoint {
	return new Endpoint(
		key: Endpoint::keyFor($method, $path),
		method: $method,
		path: $path,
		summary: $summary,
		group: $group,
		parameters: $parameters,
		requestBody: $requestBody,
		responses: $responses,
	);
}

/**
 * A documented request body, for the methods that need one to count as
 * documented at all.
 */
function requestBody(): RequestBodyDefinition
{
	return new RequestBodyDefinition(
		mediaType: 'application/json',
		fields: [new SchemaField(name: 'code', type: 'string')],
		required: true,
	);
}
