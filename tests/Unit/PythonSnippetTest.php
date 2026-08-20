<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Snippets\PythonSnippet;

// ----------------------------------------------------------------------------------
// PythonSnippet Test Suite
// Sections: language, render
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// PythonSnippet - language
// ------------------------------------------------------------

describe('PythonSnippet - language', function () {

    test('renders the python tab', function () {
        expect((new PythonSnippet)->language()->value)->toBe('python');
    });
});

// ------------------------------------------------------------
// PythonSnippet - render
// ------------------------------------------------------------

describe('PythonSnippet - render', function () {

    test('passes the query as the parameters of the request', function () {
        $snippet = (new PythonSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
            query: ['sort' => '-created_at'],
            headers: ['Accept' => 'application/json'],
        ));

        expect($snippet)->toBe(implode("\n", [
            'import requests',
            '',
            'response = requests.get(',
            '    \'https://api.bookshop.test/api/v2/vouchers\',',
            '    params={',
            '        \'sort\': \'-created_at\',',
            '    },',
            '    headers={',
            '        \'Accept\': \'application/json\',',
            '    },',
            ')',
            '',
            'data = response.json()',
        ]));
    });

    test('calls the method it is given', function () {
        $snippet = (new PythonSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Delete,
            url: 'https://api.bookshop.test/api/v2/participants/1',
        ));

        expect($snippet)->toContain('response = requests.delete(');
    });

    test('leaves out what the request does not carry', function () {
        $snippet = (new PythonSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
        ));

        expect($snippet)->not->toContain('params=')
            ->and($snippet)->not->toContain('headers=');
    });

    test('interpolates a credential and nothing else', function () {
        // Only an f-string interpolates, so the prefix belongs on the value that
        // carries the placeholder and on no other.
        $snippet = (new PythonSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
            headers: ['Authorization' => 'Bearer real-token', 'Accept-Language' => 'de'],
        ));

        expect($snippet)->toContain("'Authorization': f'Bearer {token}',")
            ->and($snippet)->toContain("'Accept-Language': 'de',")
            ->and($snippet)->not->toContain('real-token');
    });

    test('quotes a value that carries a quote', function () {
        $snippet = (new PythonSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
            query: ['filter[code]' => "it's"],
        ));

        expect($snippet)->toContain("'filter[code]': 'it\\'s',");
    });

    test('drops the entries the user left blank', function () {
        $snippet = (new PythonSnippet)->render(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
            query: ['sort' => '', 'per_page' => '25'],
            headers: ['If-None-Match' => ''],
        ));

        expect($snippet)->not->toContain('sort')
            ->and($snippet)->not->toContain('If-None-Match')
            ->and($snippet)->toContain("'per_page': '25',");
    });
});
