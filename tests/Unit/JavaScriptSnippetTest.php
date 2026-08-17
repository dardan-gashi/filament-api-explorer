<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Snippets\JavaScriptSnippet;

// ----------------------------------------------------------------------------------
// JavaScriptSnippet Test Suite
// Sections: language, render
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// JavaScriptSnippet - language
// ------------------------------------------------------------

describe('JavaScriptSnippet - language', function () {

    test('renders the js tab', function () {
        expect((new JavaScriptSnippet)->language()->value)->toBe('js');
    });
});

// ------------------------------------------------------------
// JavaScriptSnippet - render
// ------------------------------------------------------------

describe('JavaScriptSnippet - render', function () {

    test('builds a fetch call with a query and headers', function () {
        $snippet = (new JavaScriptSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
            query: ['per_page' => '25'],
            headers: ['Accept-Language' => 'de'],
        ));

        expect($snippet)->toBe(<<<'JS'
        const url = new URL('https://api.bookshop.test/api/v2/vouchers')

        url.search = new URLSearchParams({
            per_page: '25',
        }).toString()

        const response = await fetch(url, {
            headers: {
                'Accept-Language': 'de',
            },
        })

        const data = await response.json()
        JS);
    });

    test('builds a bare fetch when there is nothing to pass', function () {
        $snippet = (new JavaScriptSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
        ));

        expect($snippet)->toContain('const response = await fetch(url)')
            ->and($snippet)->not->toContain('URLSearchParams');
    });

    test('quotes a key that is not a valid identifier', function () {
        $snippet = (new JavaScriptSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
            query: ['filter[code]' => 'SUMMER10', 'sort' => 'code'],
        ));

        expect($snippet)->toContain("'filter[code]': 'SUMMER10'")
            ->and($snippet)->toContain("sort: 'code'");
    });

    test('interpolates a credential placeholder in a template literal', function () {
        $snippet = (new JavaScriptSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
            headers: ['Authorization' => 'Bearer real-token'],
        ));

        expect($snippet)->toContain('`Bearer ${token}`')
            ->and($snippet)->not->toContain('real-token');
    });
});
