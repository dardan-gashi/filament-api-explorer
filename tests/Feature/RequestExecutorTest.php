<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Exceptions\RequestNotAllowed;
use DardanGashi\FilamentApiExplorer\Services\RequestExecutor;
use DardanGashi\FilamentApiExplorer\Support\ExecutionPolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// ----------------------------------------------------------------------------------
// RequestExecutor Test Suite
// Sections: send
// ----------------------------------------------------------------------------------

function executor(bool $enabled = true, array $hosts = ['api.bookshop.test']): RequestExecutor
{
	return new RequestExecutor(
		http: app(HttpFactory::class),
		policy: new ExecutionPolicy(enabled: $enabled, allowedHosts: $hosts),
		timeout: 5,
	);
}

function getRequest(array $query = [], array $headers = []): RequestBlueprint
{
	return new RequestBlueprint(
		method: HttpMethod::Get,
		url: 'https://api.bookshop.test/api/v2/vouchers',
		query: $query,
		headers: $headers,
	);
}

// ------------------------------------------------------------
// RequestExecutor - send
// ------------------------------------------------------------

describe('RequestExecutor - send', function () {

	test('reports the status, body and headers of the response', function () {
		Http::fake([
			'api.bookshop.test/*' => Http::response('{"data":[]}', 200, ['ETag' => '"a3f1"']),
		]);

		$result = executor()->send(getRequest());

		expect($result->status)->toBe(200)
			->and($result->body)->toBe('{"data":[]}')
			->and($result->headers)->toHaveKey('ETag')
			->and($result->headers['ETag'])->toBe('"a3f1"')
			->and($result->hasFailed())->toBeFalse()
			->and($result->durationMs)->toBeGreaterThanOrEqual(0);
	});

	test('sends the query and the headers that were built', function () {
		Http::fake(['api.bookshop.test/*' => Http::response([], 200)]);

		executor()->send(getRequest(
			query: ['sort' => '-created_at', 'per_page' => '25'],
			headers: ['Authorization' => 'Bearer token'],
		));

		Http::assertSent(function (Request $request): bool {
			return $request->url() === 'https://api.bookshop.test/api/v2/vouchers?sort=-created_at&per_page=25'
				&& $request->hasHeader('Authorization', 'Bearer token')
				&& $request->method() === 'GET';
		});
	});

	test('drops the entries the user left blank', function () {
		Http::fake(['api.bookshop.test/*' => Http::response([], 200)]);

		executor()->send(getRequest(query: ['sort' => '', 'per_page' => '25']));

		Http::assertSent(fn (Request $request): bool => !str_contains($request->url(), 'sort='));
	});

	test('reports a status the api answered with, whatever it was', function () {
		Http::fake(['api.bookshop.test/*' => Http::response('{"message":"Unauthenticated."}', 401)]);

		$result = executor()->send(getRequest());

		expect($result->status)->toBe(401)
			->and($result->isSuccessful())->toBeFalse()
			->and($result->hasFailed())->toBeFalse();
	});

	test('reports a connection that never came up', function () {
		Http::fake(fn () => throw new ConnectionException('Connection timed out'));

		$result = executor()->send(getRequest());

		expect($result->hasFailed())->toBeTrue()
			->and($result->error)->toBe('Connection timed out')
			->and($result->status)->toBe(0);
	});

	test('does not follow a redirect, which could leave the allowed host', function () {
		Http::fake(['api.bookshop.test/*' => Http::response('', 302, ['Location' => 'https://evil.test/'])]);

		$result = executor()->send(getRequest());

		expect($result->status)->toBe(302);
		Http::assertSentCount(1);
	});

	test('refuses a request the policy rejects before any traffic', function () {
		Http::fake();

		expect(fn () => executor(hosts: ['api.example.com'])->send(getRequest()))
			->toThrow(RequestNotAllowed::class);

		Http::assertNothingSent();
	});

	test('refuses every request while sending is disabled', function () {
		Http::fake();

		expect(fn () => executor(enabled: false)->send(getRequest()))
			->toThrow(RequestNotAllowed::class);

		Http::assertNothingSent();
	});

	test('refuses a method with side effects', function () {
		Http::fake();

		$blueprint = new RequestBlueprint(method: HttpMethod::Delete, url: 'https://api.bookshop.test/api/v2/vouchers/1');

		expect(fn () => executor()->send($blueprint))->toThrow(RequestNotAllowed::class);

		Http::assertNothingSent();
	});
});
