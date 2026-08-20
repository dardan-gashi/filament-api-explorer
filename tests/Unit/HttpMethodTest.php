<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;

// ----------------------------------------------------------------------------------
// HttpMethod Test Suite
// Sections: label, color, isSafe, tryFromName
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// HttpMethod - label
// ------------------------------------------------------------

describe('HttpMethod - label', function () {

	test('upper-cases the verb', function () {
		expect(HttpMethod::Get->label())->toBe('GET')
			->and(HttpMethod::Patch->label())->toBe('PATCH');
	});

	test('abbreviates the verbs that would widen the badge', function () {
		expect(HttpMethod::Delete->label())->toBe('DEL')
			->and(HttpMethod::Options->label())->toBe('OPT');
	});
});

// ------------------------------------------------------------
// HttpMethod - color
// ------------------------------------------------------------

describe('HttpMethod - color', function () {

	test('gives every method a colour', function () {
		foreach (HttpMethod::cases() as $method) {
			expect($method->color())->toBeIn(['success', 'warning', 'info', 'danger', 'gray']);
		}
	});

	test('separates reading from writing', function () {
		expect(HttpMethod::Get->color())->toBe('success')
			->and(HttpMethod::Post->color())->toBe('warning')
			->and(HttpMethod::Delete->color())->toBe('danger');
	});
});

// ------------------------------------------------------------
// HttpMethod - isSafe
// ------------------------------------------------------------

describe('HttpMethod - isSafe', function () {

	test('treats the read-only methods as safe', function (HttpMethod $method) {
		expect($method->isSafe())->toBeTrue();
	})->with([
		[HttpMethod::Get],
		[HttpMethod::Head],
		[HttpMethod::Options],
	]);

	test('treats every method with side effects as unsafe', function (HttpMethod $method) {
		expect($method->isSafe())->toBeFalse();
	})->with([
		[HttpMethod::Post],
		[HttpMethod::Put],
		[HttpMethod::Patch],
		[HttpMethod::Delete],
	]);
});

// ------------------------------------------------------------
// HttpMethod - tryFromName
// ------------------------------------------------------------

describe('HttpMethod - tryFromName', function () {

	test('resolves a verb whatever its casing or padding', function (string $name) {
		expect(HttpMethod::tryFromName($name))->toBe(HttpMethod::Get);
	})->with([
		['get'],
		['GET'],
		['Get'],
		['  get  '],
	]);

	test('returns null for anything that is not a method', function () {
		expect(HttpMethod::tryFromName('summary'))->toBeNull()
			->and(HttpMethod::tryFromName('parameters'))->toBeNull();
	});
});
