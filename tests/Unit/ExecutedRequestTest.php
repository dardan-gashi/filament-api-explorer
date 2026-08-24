<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\ExecutedRequest;

// ----------------------------------------------------------------------------------
// ExecutedRequest Test Suite
// Sections: failed, isSuccessful, color, contentType, prettyBody, toLivewire,
//           fromLivewire
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// ExecutedRequest - failed
// ------------------------------------------------------------

describe('ExecutedRequest - failed', function () {

	test('records a request that never produced a response', function () {
		$result = ExecutedRequest::failed('Connection timed out', durationMs: 10_000);

		expect($result->hasFailed())->toBeTrue()
			->and($result->status)->toBe(0)
			->and($result->error)->toBe('Connection timed out')
			->and($result->durationMs)->toBe(10_000);
	});
});

// ------------------------------------------------------------
// ExecutedRequest - isSuccessful
// ------------------------------------------------------------

describe('ExecutedRequest - isSuccessful', function () {

	test('reads a 2xx response as successful', function () {
		expect((new ExecutedRequest(status: 204))->isSuccessful())->toBeTrue()
			->and((new ExecutedRequest(status: 422))->isSuccessful())->toBeFalse();
	});

	test('never reads a failure as successful', function () {
		expect(ExecutedRequest::failed('gone')->isSuccessful())->toBeFalse();
	});
});

// ------------------------------------------------------------
// ExecutedRequest - color
// ------------------------------------------------------------

describe('ExecutedRequest - color', function () {

	test('colours by status', function () {
		expect((new ExecutedRequest(status: 200))->color())->toBe('success')
			->and((new ExecutedRequest(status: 500))->color())->toBe('danger');
	});

	test('colours a failure as an error', function () {
		expect(ExecutedRequest::failed('gone')->color())->toBe('danger');
	});
});

// ------------------------------------------------------------
// ExecutedRequest - prettyBody
// ------------------------------------------------------------

describe('ExecutedRequest - contentType', function () {

	test('reads the type the server sent, without its charset', function () {
		$result = new ExecutedRequest(
			status: 200,
			headers: ['Content-Type' => 'application/xml; charset=UTF-8'],
		);

		expect($result->contentType())->toBe('application/xml');
	});

	test('finds the header whatever case it arrived in', function () {
		// A header name is case-insensitive on the wire, and clients differ.
		$result = new ExecutedRequest(status: 200, headers: ['content-type' => 'application/json']);

		expect($result->contentType())->toBe('application/json');
	});

	test('says nothing when the response did not', function () {
		expect((new ExecutedRequest(status: 204))->contentType())->toBeNull();
	});
});

// ------------------------------------------------------------
// ExecutedRequest - prettyBody
// ------------------------------------------------------------

describe('ExecutedRequest - prettyBody', function () {

	test('indents a json body', function () {
		$result = new ExecutedRequest(status: 200, body: '{"code":"LEGUIN-01"}');

		expect($result->prettyBody())->toBe(implode("\n", [
			'{',
			'    "code": "LEGUIN-01"',
			'}',
		]));
	});

	test('leaves a body that is not json untouched', function () {
		expect((new ExecutedRequest(status: 200, body: 'plain text'))->prettyBody())->toBe('plain text');
	});

	test('indents an xml body the response declared as one', function () {
		$result = new ExecutedRequest(
			status: 200,
			body: '<Thing><sku>1005444106</sku><art/></Thing>',
			headers: ['Content-Type' => 'application/xml'],
		);

		expect($result->prettyBody())->toContain(implode("\n", [
			'<Thing>',
			'  <sku>1005444106</sku>',
			'  <art/>',
			'</Thing>',
		]));
	});

	test('shows xml that does not parse exactly as it arrived', function () {
		// A malformed body is the interesting one: a parser's complaint would hide
		// the very thing the reader is looking at.
		$result = new ExecutedRequest(
			status: 500,
			body: '<Thing><sku>1005',
			headers: ['Content-Type' => 'application/xml'],
		);

		expect($result->prettyBody())->toBe('<Thing><sku>1005');
	});

	test('leaves an empty body empty', function () {
		expect((new ExecutedRequest(status: 204))->prettyBody())->toBe('');
	});

	test('does not escape the slashes of a url', function () {
		$result = new ExecutedRequest(status: 200, body: '{"url":"https://api.bookshop.test/api"}');

		expect($result->prettyBody())->toContain('https://api.bookshop.test/api');
	});
});

// ------------------------------------------------------------
// ExecutedRequest - toLivewire
// ------------------------------------------------------------

describe('ExecutedRequest - toLivewire', function () {

	test('writes every part of the result into the component state', function () {
		$result = new ExecutedRequest(
			status: 200,
			body: '{}',
			headers: ['ETag' => '"abc"'],
			durationMs: 42,
		);

		expect($result->toLivewire())->toBe([
			'status' => 200,
			'body' => '{}',
			'headers' => ['ETag' => '"abc"'],
			'durationMs' => 42,
			'error' => null,
		]);
	});
});

// ------------------------------------------------------------
// ExecutedRequest - fromLivewire
// ------------------------------------------------------------

describe('ExecutedRequest - fromLivewire', function () {

	test('rebuilds a result that travelled over the wire', function () {
		$result = new ExecutedRequest(status: 200, body: '{}', headers: ['ETag' => '"abc"'], durationMs: 42);

		expect(ExecutedRequest::fromLivewire($result->toLivewire()))->toEqual($result);
	});

	test('rebuilds a failure', function () {
		$result = ExecutedRequest::failed('Connection timed out', 5);

		expect(ExecutedRequest::fromLivewire($result->toLivewire()))->toEqual($result);
	});

	test('survives state that is missing or the wrong shape', function () {
		$result = ExecutedRequest::fromLivewire(['status' => '200', 'headers' => ['ETag' => ['a', 'b']]]);

		expect($result->status)->toBe(200)
			->and($result->body)->toBe('')
			->and($result->headers)->toBe([])
			->and(ExecutedRequest::fromLivewire(null)->status)->toBe(0);
	});
});
