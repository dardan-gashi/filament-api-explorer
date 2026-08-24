<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Support\Xml;

// ----------------------------------------------------------------------------------
// Xml Test Suite
// Sections: encode, format
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// Xml - encode
// ------------------------------------------------------------

describe('Xml - encode', function () {

	test('writes a map as elements under the root it was given', function () {
		expect(Xml::encode(['sku' => '1005444106', 'price' => 199], 'Thing'))->toBe(implode("\n", [
			'<?xml version="1.0" encoding="UTF-8"?>',
			'<Thing>',
			'  <sku>1005444106</sku>',
			'  <price>199</price>',
			'</Thing>',
		]));
	});

	test('repeats the element of a list instead of wrapping it', function () {
		// OpenAPI leaves an array unwrapped unless a document says otherwise, so
		// two tags are two entries and not a list element holding them.
		expect(Xml::encode(['tags' => ['neu', 'alt']]))->toContain(implode("\n", [
			'  <tags>neu</tags>',
			'  <tags>alt</tags>',
		]));
	});

	test('gives a list at the very top a root to live under', function () {
		// A document has exactly one root element, so repeating it is not an option.
		expect(Xml::encode(['a', 'b'], 'things'))->toContain(implode("\n", [
			'<things>',
			'  <item>a</item>',
			'  <item>b</item>',
			'</things>',
		]));
	});

	test('closes an empty element rather than writing nothing', function () {
		expect(Xml::encode(['art' => null, 'tags' => [], 'meta' => []]))
			->toContain('<art/>')
			->toContain('<tags/>')
			->toContain('<meta/>');
	});

	test('writes a boolean as a word and escapes the text', function () {
		expect(Xml::encode(['active' => true, 'name' => 'Fit & Fertig <heute>']))
			->toContain('<active>true</active>')
			->toContain('<name>Fit &amp; Fertig &lt;heute&gt;</name>');
	});

	test('makes an element name out of a key that is not one', function () {
		// A JSON key may start with a digit or hold a space; an element name may
		// not, and an invalid name is a document nothing parses.
		expect(Xml::encode(['2024 total' => 7, 'tax rate' => 19]))
			->toContain('<item-2024-total>7</item-2024-total>')
			->toContain('<tax-rate>19</tax-rate>');
	});
});

// ------------------------------------------------------------
// Xml - format
// ------------------------------------------------------------

describe('Xml - format', function () {

	test('indents a document it can parse', function () {
		expect(Xml::format('<a><b>1</b><c/></a>'))->toContain(implode("\n", [
			'<a>',
			'  <b>1</b>',
			'  <c/>',
			'</a>',
		]));
	});

	test('says nothing when the body is not XML at all', function () {
		// The caller shows the body exactly as it arrived in that case, which is
		// more use than a parser's complaint.
		expect(Xml::format('{"code": "SUMMER10"}'))->toBeNull()
			->and(Xml::format(''))->toBeNull();
	});
});
