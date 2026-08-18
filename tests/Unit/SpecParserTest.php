<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Enums\DocumentationGap;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;

// ----------------------------------------------------------------------------------
// SpecParser Test Suite
// Sections: parseDocument
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// SpecParser - parseDocument
// ------------------------------------------------------------

describe('SpecParser - parseDocument', function () {

    test('reads the api identity from the info object', function () {
        $spec = parser()->parseDocument('v2', fixtureDocument());

        expect($spec->name)->toBe('v2')
            ->and($spec->title)->toBe('Bookshop API')
            ->and($spec->version)->toBe('2.4.1')
            ->and($spec->description)->toContain('catalogue and order API');
    });

    test('falls back to the source name when the document has no title', function () {
        expect(parser()->parseDocument('v1', [])->title)->toBe('v1');
    });

    test('keeps the snapshot time it was given', function () {
        $generatedAt = CarbonImmutable::parse('2026-08-17 09:14:00');

        expect(parser()->parseDocument('v2', [], $generatedAt)->generatedAt)->toBe($generatedAt);
    });

    test('resolves the template variables of a server url', function () {
        expect(parser()->parseDocument('v2', fixtureDocument())->servers)
            ->toBe(['https://api.bookshop.test/api/v2']);
    });

    test('turns every operation of every path into an endpoint', function () {
        $spec = parser()->parseDocument('v2', fixtureDocument());

        expect($spec->endpointCount())->toBe(7)
            ->and(array_map(fn (Endpoint $endpoint): string => $endpoint->key, $spec->endpoints))
            ->toContain(Endpoint::keyFor(HttpMethod::Delete, '/participants/{participant}'));
    });

    test('ignores the keys of a path item that are not methods', function () {
        $spec = parser()->parseDocument('v2', [
            'paths' => [
                '/vouchers' => [
                    'summary' => 'Not an operation',
                    'parameters' => [],
                    'get' => ['summary' => 'Lists vouchers'],
                ],
            ],
        ]);

        expect($spec->endpointCount())->toBe(1)
            ->and($spec->endpoints[0]->method)->toBe(HttpMethod::Get);
    });

    test('groups an endpoint by its first tag', function () {
        $spec = parser()->parseDocument('v2', fixtureDocument());

        expect($spec->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'))?->group)->toBe('Vouchers');
    });

    test('falls back to a shared group when an operation has no tag', function () {
        $spec = parser()->parseDocument('v2', [
            'paths' => ['/health' => ['get' => ['summary' => 'Health']]],
        ]);

        expect($spec->endpoints[0]->group)->toBe('General');
    });

    test('collects the vendor extensions of an operation as captions', function () {
        $endpoint = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'));

        expect($endpoint?->meta)->toBe([
            'handler' => 'VoucherController@index',
            'rate-limit' => '60/min',
            'since' => 'v2.0',
        ]);
    });

    test('inherits the document security when an operation declares none', function () {
        $endpoint = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'));

        expect($endpoint?->security)->toBe(['sanctum']);
    });

    test('lets an operation opt out of the document security', function () {
        $document = fixtureDocument();
        $document['paths']['/vouchers']['get']['security'] = [];

        $endpoint = parser()->parseDocument('v2', $document)
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'));

        expect($endpoint?->security)->toBe([]);
    });

    test('adds the authentication header implied by a bearer scheme', function () {
        $endpoint = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'));

        $authorization = $endpoint?->parametersIn(ParameterLocation::Header)[0];

        expect($authorization?->name)->toBe('Authorization')
            ->and($authorization?->required)->toBeTrue()
            ->and($authorization?->example)->toBe('Bearer <token>')
            ->and($authorization?->description)->toBe('Personal access token issued by the panel.');
    });

    test('marks the implied authentication header as inferred', function () {
        // A generator that leaves its security scheme undescribed — Scramble
        // does — must not cost the endpoint its documented standing.
        $document = fixtureDocument();
        unset($document['components']['securitySchemes']['sanctum']['description']);

        $endpoint = parser()->parseDocument('v2', $document)
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'));

        $authorization = $endpoint?->parametersIn(ParameterLocation::Header)[0];

        expect($authorization?->inferred)->toBeTrue()
            ->and($authorization?->description)->toBeNull()
            ->and($endpoint?->gaps())->toBe([]);
    });

    test('leaves an authentication header the document declares itself', function () {
        $document = fixtureDocument();
        $document['paths']['/vouchers']['get']['parameters'][] = [
            'name' => 'authorization',
            'in' => 'header',
            'description' => 'Documented by hand.',
            'schema' => ['type' => 'string'],
        ];

        $headers = parser()->parseDocument('v2', $document)
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'))
            ?->parametersIn(ParameterLocation::Header) ?? [];

        $names = array_map(fn ($header): string => strtolower($header->name), $headers);

        expect(array_count_values($names)['authorization'] ?? 0)->toBe(1);
    });

    test('merges the parameters a path shares into each of its operations', function () {
        $endpoint = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers/{code}'));

        $path = $endpoint?->parametersIn(ParameterLocation::Path)[0];

        expect($path?->name)->toBe('code')
            ->and($path?->required)->toBeTrue()
            ->and($path?->example)->toBe('SUMMER10');
    });

    test('reads the type, default and allowed values of a query parameter', function () {
        $parameters = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'))
            ?->parametersIn(ParameterLocation::Query) ?? [];

        $sort = collect($parameters)->firstWhere('name', 'sort');
        $perPage = collect($parameters)->firstWhere('name', 'per_page');

        expect($sort?->type)->toBe('string')
            ->and($sort?->default)->toBe('-created_at')
            ->and($sort?->enum)->toBe(['code', '-code', 'created_at', '-created_at'])
            ->and($perPage?->type)->toBe('integer')
            ->and($perPage?->default)->toBe(25);
    });

    test('keeps the response status as a string', function () {
        $responses = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'))
            ?->responses ?? [];

        expect(array_map(fn ($response): string => $response->status, $responses))
            ->toBe(['200', '304', '401', '422']);
    });

    test('reads a description that is only a name as the name', function () {
        // Scramble describes a response as `OrderDetailResource`, where OpenAPI wants
        // a sentence. Left in the description it would be set as prose beside the
        // `Not found` of the next response; as a name it lands where every other
        // response carries its schema name.
        $document = fixtureDocument();
        $document['paths']['/courses']['get']['responses']['200'] = [
            'description' => '`CourseListResource`',
            'content' => ['application/json' => ['schema' => ['type' => 'object']]],
        ];

        $response = parser()->parseDocument('v2', $document)
            ->find(Endpoint::keyFor(HttpMethod::Get, '/courses'))
            ?->response('200');

        expect($response?->schemaName)->toBe('CourseListResource')
            ->and($response?->description)->toBeNull();
    });

    test('leaves a description that says something alone', function () {
        $document = fixtureDocument();
        $document['paths']['/courses']['get']['responses']['200'] = [
            'description' => 'One page of `CourseResource` items.',
            'content' => ['application/json' => ['schema' => ['type' => 'object']]],
        ];

        $response = parser()->parseDocument('v2', $document)
            ->find(Endpoint::keyFor(HttpMethod::Get, '/courses'))
            ?->response('200');

        expect($response?->description)->toBe('One page of `CourseResource` items.')
            ->and($response?->schemaName)->toBeNull();
    });

    test('reads the schema name, media type and fields of a response', function () {
        $response = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'))
            ?->response('200');

        expect($response?->schemaName)->toBe('VoucherListResource')
            ->and($response?->mediaType)->toBe('application/json')
            ->and(array_map(fn ($field): string => $field->name, $response?->fields ?? []))
            ->toBe(['data', 'meta']);
    });

    test('reads the documented headers of a response', function () {
        $headers = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'))
            ?->response('200')?->headers ?? [];

        expect(array_map(fn ($header): string => $header->name, $headers))
            ->toBe(['ETag', 'Cache-Control', 'X-RateLimit-Remaining'])
            ->and($headers[2]->example)->toBe(58);
    });

    test('uses the example the document provides', function () {
        $example = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'))
            ?->response('200')?->example;

        expect($example)->toContain('SUMMER10')
            ->and($example)->toContain('"total": 42');
    });

    test('documents a response that carries no body at all', function () {
        $response = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'))
            ?->response('304');

        expect($response?->mediaType)->toBeNull()
            ->and($response?->fields)->toBe([])
            ->and($response?->example)->toBeNull();
    });

    test('marks the endpoints whose documentation is incomplete', function () {
        $spec = parser()->parseDocument('v2', fixtureDocument());

        expect($spec->find(Endpoint::keyFor(HttpMethod::Get, '/participants'))?->gaps())
            ->toBe([DocumentationGap::Description, DocumentationGap::Responses])
            ->and($spec->find(Endpoint::keyFor(HttpMethod::Get, '/courses'))?->gaps())
            ->toBe([DocumentationGap::ResponseSchema, DocumentationGap::Parameters])
            ->and($spec->coverage()->percentage())->toBe(57);
    });

    test('reads the body an operation expects', function () {
        $body = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Post, '/vouchers'))
            ?->requestBody;

        expect($body?->mediaType)->toBe('application/json')
            ->and($body?->required)->toBeTrue()
            ->and($body?->description)->toBe('The voucher to create.')
            ->and(array_map(fn ($field): string => $field->name, $body?->fields ?? []))
            ->toBe(['code', 'value', 'expires_at']);
    });

    test('reports a method that takes a body without documenting one', function () {
        // The fixture's PATCH documents everything but what it expects to be sent,
        // which is the one gap the coverage figure used to be blind to.
        $spec = parser()->parseDocument('v2', fixtureDocument());

        expect($spec->find(Endpoint::keyFor(HttpMethod::Patch, '/participants/{participant}'))?->gaps())
            ->toBe([DocumentationGap::RequestBody])
            ->and($spec->find(Endpoint::keyFor(HttpMethod::Delete, '/participants/{participant}'))?->gaps())
            ->toBe([]);
    });

    test('marks a body example it had to build itself', function () {
        $body = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Post, '/vouchers'))
            ?->requestBody;

        expect($body?->exampleSynthesised)->toBeTrue()
            ->and($body?->example)->toContain('"code": "string"');
    });

    test('separates a declared response example from one it synthesised', function () {
        $endpoint = parser()->parseDocument('v2', fixtureDocument())
            ->find(Endpoint::keyFor(HttpMethod::Get, '/vouchers'));

        expect($endpoint?->response('200')?->exampleSynthesised)->toBeFalse()
            ->and($endpoint?->response('401')?->exampleSynthesised)->toBeTrue();
    });

    test('names a security scheme after the mechanism, not after its own type', function () {
        $document = fixtureDocument();
        $document['components']['securitySchemes']['http'] = ['type' => 'http', 'scheme' => 'bearer'];

        $spec = parser()->parseDocument('v2', $document);

        expect($spec->securityLabel('sanctum'))->toBe('sanctum')
            ->and($spec->securityLabel('http'))->toBe('bearer')
            ->and($spec->securityLabel('unknown'))->toBe('unknown');
    });

    test('reads a deprecated operation', function () {
        $document = fixtureDocument();
        $document['paths']['/vouchers']['get']['deprecated'] = true;

        expect(parser()->parseDocument('v2', $document)->endpoints[0]->deprecated)->toBeTrue();
    });

    test('survives a document with nothing in it', function () {
        $spec = parser()->parseDocument('v2', []);

        expect($spec->endpoints)->toBe([])
            ->and($spec->servers)->toBe([])
            ->and($spec->coverage()->percentage())->toBe(100);
    });

    test('survives a document whose sections are the wrong shape', function () {
        $spec = parser()->parseDocument('v2', [
            'info' => 'nonsense',
            'servers' => 'nonsense',
            'paths' => ['/vouchers' => 'nonsense'],
        ]);

        expect($spec->title)->toBe('v2')
            ->and($spec->endpoints)->toBe([]);
    });
});
