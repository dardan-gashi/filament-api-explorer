<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Exceptions\RequestNotAllowed;

// ----------------------------------------------------------------------------------
// RequestNotAllowed Test Suite
// Sections: reason, getMessage
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// RequestNotAllowed - reason
// ------------------------------------------------------------

describe('RequestNotAllowed - reason', function () {

	test('says why in the language of the panel', function () {
		app()->setLocale('de');

		expect(RequestNotAllowed::hostNotAllowed('localhost')->reason())
			->toBe('Der Host localhost steht nicht in execution.allowed_hosts.');
	});

	test('names the setting that decides it', function () {
		// A refusal the reader can act on beats one they have to look up: the
		// sentence carries the key it comes from.
		expect(RequestNotAllowed::hostNotAllowed('localhost')->reason())
			->toContain('execution.allowed_hosts')
			->and(RequestNotAllowed::insecureScheme('ftp')->reason())
			->toContain('execution.allowed_schemes');
	});

	test('counts what it is asking for', function () {
		expect(RequestNotAllowed::unresolvedPath(['order'])->reason())
			->toBe('Fill in the path parameter order before sending.')
			->and(RequestNotAllowed::unresolvedPath(['order', 'item'])->reason())
			->toBe('Fill in the path parameters order, item before sending.');
	});

	test('names the method it refused', function () {
		expect(RequestNotAllowed::unsafeMethod(HttpMethod::Delete)->reason())
			->toBe('Only safe requests are sent, so DEL was refused.');
	});
});

// ------------------------------------------------------------
// RequestNotAllowed - getMessage
// ------------------------------------------------------------

describe('RequestNotAllowed - getMessage', function () {

	test('stays in English for whoever reads the log', function () {
		// Two audiences, two sentences: a log is read by one person in one language,
		// the panel by whoever is in front of it.
		app()->setLocale('de');

		expect(RequestNotAllowed::hostNotAllowed('localhost')->getMessage())
			->toBe('The host [localhost] is not in the allowed hosts list.');
	});
});
