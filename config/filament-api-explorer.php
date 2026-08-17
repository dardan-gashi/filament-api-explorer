<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Specification sources
    |--------------------------------------------------------------------------
    |
    | Each entry is one OpenAPI document. The key is the name shown in the
    | version picker, and the first entry is the one the page opens with.
    |
    | The "file" driver reads a JSON or YAML document from disk. Register your
    | own driver with SpecSourceManager::extend() to load a document from
    | somewhere else, for example straight out of a generator.
    |
    */

    'sources' => [
        'default' => [
            'driver' => 'file',
            'path' => storage_path('api-docs/openapi.json'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Parsed specification cache
    |--------------------------------------------------------------------------
    |
    | Parsing is repeated on every page load unless it is cached. The cache key
    | contains the document's last-modified time, so a regenerated document is
    | picked up without clearing anything by hand.
    |
    */

    'cache' => [
        'enabled' => false,
        'store' => null,
        'ttl' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema rendering
    |--------------------------------------------------------------------------
    |
    | How deep a response schema is expanded. Deeply nested and recursive
    | schemas stop at this depth instead of growing without end.
    |
    */

    'schema' => [
        'max_depth' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Page
    |--------------------------------------------------------------------------
    |
    | Leave "title" and "description" empty to fall back to the values in the
    | document's info object. The page is kept out of production panels by
    | default, because an API reference is usually an internal tool.
    |
    */

    'page' => [
        'slug' => 'api-explorer',
        'title' => null,
        'description' => null,
        'full_width' => true,
        'enabled_in_production' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | The badge is one of "count", "coverage", "version" or null.
    |
    */

    'navigation' => [
        'label' => null,
        'icon' => 'heroicon-o-code-bracket-square',
        'group' => null,
        'sort' => null,
        'badge' => 'count',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sending requests
    |--------------------------------------------------------------------------
    |
    | The explorer can send an endpoint's request and show the live response.
    | This runs server-side, so it is restricted on purpose: only safe methods,
    | only the schemes below, and only hosts listed here. Leave "allowed_hosts"
    | empty to allow the application's own host, and use patterns such as
    | "*.staging.example.com" to cover a set of environments.
    |
    */

    'execution' => [
        'enabled' => true,
        'allowed_hosts' => [],
        'allowed_schemes' => ['https', 'http'],
        'timeout' => 10,
    ],

];
