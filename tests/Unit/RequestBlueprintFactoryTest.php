<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\Parameter;
use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;
use DardanGashi\FilamentApiExplorer\Services\RequestBlueprintFactory;

// ----------------------------------------------------------------------------------
// RequestBlueprintFactory Test Suite
// Sections: make, suggestions
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// RequestBlueprintFactory - make
// ------------------------------------------------------------

describe('RequestBlueprintFactory - make', function () {

    test('joins the server and the path into an absolute url', function () {
        $blueprint = (new RequestBlueprintFactory)->make(
            endpoint: endpoint(path: '/vouchers'),
            server: 'https://api.bookshop.test/api/v2/',
        );

        expect($blueprint->url)->toBe('https://api.bookshop.test/api/v2/vouchers');
    });

    test('substitutes a path parameter that has a value', function () {
        $blueprint = (new RequestBlueprintFactory)->make(
            endpoint: endpoint(path: '/vouchers/{code}', parameters: [
                new Parameter(name: 'code', in: ParameterLocation::Path, required: true),
            ]),
            server: 'https://api.bookshop.test',
            pathParameters: ['code' => 'SUMMER 10'],
        );

        expect($blueprint->url)->toBe('https://api.bookshop.test/vouchers/SUMMER%2010');
    });

    test('leaves a path placeholder that has no value', function () {
        $blueprint = (new RequestBlueprintFactory)->make(
            endpoint: endpoint(path: '/vouchers/{code}', parameters: [
                new Parameter(name: 'code', in: ParameterLocation::Path, required: true),
            ]),
            server: 'https://api.bookshop.test',
            pathParameters: ['code' => '   '],
        );

        expect($blueprint->url)->toBe('https://api.bookshop.test/vouchers/{code}');
    });

    test('keeps the query values of documented parameters', function () {
        $blueprint = (new RequestBlueprintFactory)->make(
            endpoint: endpoint(parameters: [
                new Parameter(name: 'sort', in: ParameterLocation::Query),
                new Parameter(name: 'cursor', in: ParameterLocation::Query),
            ]),
            server: 'https://api.bookshop.test',
            queryParameters: ['sort' => '-created_at', 'cursor' => ''],
        );

        expect($blueprint->query)->toBe(['sort' => '-created_at']);
    });

    test('drops a value that belongs to no documented parameter', function () {
        $blueprint = (new RequestBlueprintFactory)->make(
            endpoint: endpoint(parameters: [new Parameter(name: 'sort', in: ParameterLocation::Query)]),
            server: 'https://api.bookshop.test',
            queryParameters: ['sort' => 'code', 'injected' => 'value'],
            headers: ['X-Injected' => 'value'],
        );

        expect($blueprint->query)->toBe(['sort' => 'code'])
            ->and($blueprint->headers)->toBe([]);
    });

    test('trims what the user typed', function () {
        $blueprint = (new RequestBlueprintFactory)->make(
            endpoint: endpoint(parameters: [new Parameter(name: 'sort', in: ParameterLocation::Query)]),
            server: 'https://api.bookshop.test',
            queryParameters: ['sort' => '  code  '],
        );

        expect($blueprint->query)->toBe(['sort' => 'code']);
    });

    test('carries the endpoint method', function () {
        $blueprint = (new RequestBlueprintFactory)->make(endpoint(), 'https://api.bookshop.test');

        expect($blueprint->method)->toBe(endpoint()->method);
    });
});

// ------------------------------------------------------------
// RequestBlueprintFactory - suggestions
// ------------------------------------------------------------

describe('RequestBlueprintFactory - suggestions', function () {

    test('suggests the example, then the default, then the first allowed value', function () {
        $suggestions = (new RequestBlueprintFactory)->suggestions(
            endpoint(parameters: [
                new Parameter(name: 'code', in: ParameterLocation::Query, example: 'SUMMER10', default: 'X'),
                new Parameter(name: 'per_page', in: ParameterLocation::Query, default: 25),
                new Parameter(name: 'sort', in: ParameterLocation::Query, enum: ['code', '-code']),
                new Parameter(name: 'cursor', in: ParameterLocation::Query),
            ]),
            ParameterLocation::Query,
        );

        expect($suggestions)->toBe([
            'code' => 'SUMMER10',
            'per_page' => '25',
            'sort' => 'code',
            'cursor' => '',
        ]);
    });

    test('renders a boolean default the way a reader expects', function () {
        $suggestions = (new RequestBlueprintFactory)->suggestions(
            endpoint(parameters: [new Parameter(name: 'filter[is_active]', in: ParameterLocation::Query, default: true)]),
            ParameterLocation::Query,
        );

        expect($suggestions)->toBe(['filter[is_active]' => 'true']);
    });

    test('returns nothing for a location with no parameters', function () {
        expect((new RequestBlueprintFactory)->suggestions(endpoint(), ParameterLocation::Header))->toBe([]);
    });
});
