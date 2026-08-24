<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\BodyRendering;

// ----------------------------------------------------------------------------------
// BodyRendering Test Suite
// Sections: pick, mediaTypesOf
// ----------------------------------------------------------------------------------

function bodyRendering(?string $mediaType, ?string $example = null): BodyRendering
{
	return new BodyRendering(mediaType: $mediaType, example: $example);
}

// ------------------------------------------------------------
// BodyRendering - pick
// ------------------------------------------------------------

describe('BodyRendering - pick', function () {

	test('hands back the preferred one where no format was asked for', function () {
		$preferred = bodyRendering('application/json');

		expect(BodyRendering::pick(null, $preferred, [bodyRendering('application/xml')]))
			->toBe($preferred);
	});

	test('hands back the one written in the media type that was asked for', function () {
		$xml = bodyRendering('application/xml');

		expect(BodyRendering::pick('application/xml', bodyRendering('application/json'), [$xml]))
			->toBe($xml);
	});

	test('matches a media type however the document cased it', function () {
		// A media type is case-insensitive by RFC 9110, and a hand-written document
		// spells one `Application/XML` often enough to matter.
		$xml = bodyRendering('Application/XML');

		expect(BodyRendering::pick('application/xml', bodyRendering('application/json'), [$xml]))
			->toBe($xml);
	});

	test('falls back to the preferred one for a media type this body has not got', function () {
		// The reader switched the endpoint to XML and this particular body — an
		// error, usually — exists only as JSON. Showing it as JSON, labelled JSON,
		// is the honest answer; showing nothing would hide a documented response.
		$preferred = bodyRendering('application/json');

		expect(BodyRendering::pick('application/xml', $preferred, []))->toBe($preferred);
	});
});

// ------------------------------------------------------------
// BodyRendering - mediaTypesOf
// ------------------------------------------------------------

describe('BodyRendering - mediaTypesOf', function () {

	test('names the preferred media type first and the alternates behind it', function () {
		expect(BodyRendering::mediaTypesOf(bodyRendering('application/json'), [
			bodyRendering('application/xml'),
			bodyRendering('text/csv'),
		]))->toBe(['application/json', 'application/xml', 'text/csv']);
	});

	test('leaves out a rendering that names no media type at all', function () {
		// A response documented with a description and no content: there is nothing
		// to offer a format for.
		expect(BodyRendering::mediaTypesOf(bodyRendering(null), []))->toBe([]);
	});
});
