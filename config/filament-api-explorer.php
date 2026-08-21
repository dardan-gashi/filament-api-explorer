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
	| The "file" driver reads a JSON or YAML document from disk. The "scramble"
	| driver generates one from the routes themselves, so the reference cannot go
	| stale behind an export nobody re-ran — it needs dedoc/scramble and takes an
	| optional "api" name and "watch" paths:
	|
	|     'api' => ['driver' => 'scramble', 'api' => 'default'],
	|
	| Register a driver of your own with SpecSourceManager::extend().
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
	| Scramble
	|--------------------------------------------------------------------------
	|
	| Where dedoc/scramble is installed, this package adds the facts an OpenAPI
	| schema has no field for to every operation it generates: the action that
	| answers the endpoint, its throttle, and the token abilities it insists on.
	| They travel as x-handler, x-rate-limit and x-abilities, and the page reads
	| them back as the captions under an endpoint title. Set this to false to
	| leave the generated document exactly as Scramble wrote it.
	|
	*/

	'scramble' => [
		'facts' => true,
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
	| Recorded response examples
	|--------------------------------------------------------------------------
	|
	| An example built from a schema says "status": "string" where the API says
	| "status": "paid" — correct, and of no use to anyone. So the explorer keeps
	| the responses it actually receives and shows those instead, one per status.
	|
	| Be aware of what that means: a recorded sample is real response data, held
	| in the cache and shown to everyone who can open the page. Set "capture" to
	| false where that is not acceptable.
	|
	*/

	'examples' => [
		'capture' => true,
		'store' => null,
		'ttl' => 86400,
		'max_bytes' => 65536,
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
	| Leave "title" empty to fall back to the title in the document's info
	| object. The page is kept out of production panels by default, because an
	| API reference is usually an internal tool.
	|
	*/

	'page' => [
		'slug' => 'api-explorer',
		'title' => null,
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
	| only the schemes below, and only hosts you list here — the hosts are yours to
	| name, because only you know which environments this installation may reach.
	| An empty list means this application's own host, which is the one host an
	| explorer needs in order to try its own API, and patterns such as
	| "*.staging.example.com" cover a set of environments.
	|
	| Naming a host reachable from everywhere means it is reachable from everywhere:
	| put production in this list and a laptop can call production the moment
	| somebody picks that server from the list on the page.
	|
	*/

	'execution' => [
		'enabled' => true,
		'allowed_hosts' => [],
		'allowed_schemes' => ['https', 'http'],
		'timeout' => 10,
	],

];
