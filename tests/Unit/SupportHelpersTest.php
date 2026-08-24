<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Support\Documents;
use DardanGashi\FilamentApiExplorer\Support\EndpointMeta;
use DardanGashi\FilamentApiExplorer\Support\GroupLabel;
use DardanGashi\FilamentApiExplorer\Support\HttpStatus;
use DardanGashi\FilamentApiExplorer\Support\InlineMarkdown;
use DardanGashi\FilamentApiExplorer\Support\InputKey;
use DardanGashi\FilamentApiExplorer\Support\MediaType;
use DardanGashi\FilamentApiExplorer\Support\PathParts;
use DardanGashi\FilamentApiExplorer\Support\SecretHeaders;

// ----------------------------------------------------------------------------------
// Support Helpers Test Suite
// Sections: Documents::entries, Documents::string, GroupLabel::for, HttpStatus::color,
//           InputKey::for, EndpointMeta::caption, EndpointMeta::icon,
//           PathParts::sharedPrefix, PathParts::within, InlineMarkdown::toHtml,
//           SecretHeaders::isSecret, SecretHeaders::redact, MediaType::label
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// Documents - entries
// ------------------------------------------------------------

describe('Documents - entries', function () {

	test('hands back a numeric key as the string the document wrote', function () {
		// PHP turns the "200" of a specification into the integer 200.
		expect(Documents::entries(['200' => 'ok', '422' => 'invalid']))
			->toBe([['200', 'ok'], ['422', 'invalid']]);
	});

	test('hands back an empty map as an empty list', function () {
		expect(Documents::entries([]))->toBe([]);
	});
});

// ------------------------------------------------------------
// Documents - string
// ------------------------------------------------------------

describe('Documents - string', function () {

	test('reads a string value', function () {
		expect(Documents::string(['title' => 'Bookshop API'], 'title'))->toBe('Bookshop API');
	});

	test('reads a number as a string, which is how yaml renders a version', function () {
		expect(Documents::string(['version' => 2.4], 'version'))->toBe('2.4');
	});

	test('reads a blank, missing or non-scalar value as null', function () {
		expect(Documents::string(['title' => ''], 'title'))->toBeNull()
			->and(Documents::string([], 'title'))->toBeNull()
			->and(Documents::string(['title' => ['nested']], 'title'))->toBeNull();
	});
});

// ------------------------------------------------------------
// GroupLabel - for
// ------------------------------------------------------------

describe('GroupLabel - for', function () {

	test('reads a tag as a heading rather than as a class name', function (string $tag, string $expected) {
		expect(GroupLabel::for($tag))->toBe($expected);
	})->with([
		// Generators tag operations with whatever handles them.
		['BookApi', 'Book Catalog'],
		['VoucherController', 'Voucher'],
		['OrderApiResource', 'Order Api'],
		['participants', 'Participants'],
		['shipping-zones', 'Shipping Zones'],
		['Vouchers', 'Vouchers'],
	]);

	test('keeps a tag that is nothing but a suffix', function () {
		expect(GroupLabel::for('API'))->toBe('API')
			->and(GroupLabel::for('Api'))->toBe('Api');
	});
});

// ------------------------------------------------------------
// HttpStatus - color
// ------------------------------------------------------------

describe('HttpStatus - color', function () {

	test('gives each class of status its own colour', function (int|string $status, string $color) {
		expect(HttpStatus::color($status))->toBe($color);
	})->with([
		[200, 'success'],
		['201', 'success'],
		[304, 'info'],
		[401, 'warning'],
		[422, 'warning'],
		[500, 'danger'],
		['default', 'gray'],
	]);

	test('recognises a successful status', function () {
		expect(HttpStatus::isSuccessful('204'))->toBeTrue()
			->and(HttpStatus::isSuccessful(404))->toBeFalse()
			->and(HttpStatus::isSuccessful('default'))->toBeFalse();
	});
});

// ------------------------------------------------------------
// InputKey - for
// ------------------------------------------------------------

describe('InputKey - for', function () {

	test('flattens the brackets livewire would read as property access', function () {
		expect(InputKey::for('filter[code]'))->not->toContain('[')
			->and(InputKey::for('filter[code]'))->toStartWith('filter_code_');
	});

	test('is stable for the same name', function () {
		expect(InputKey::for('sort'))->toBe(InputKey::for('sort'));
	});

	test('keeps names apart that flatten to the same characters', function () {
		expect(InputKey::for('filter[code]'))->not->toBe(InputKey::for('filter.code'));
	});

	test('still produces a key for a name made only of punctuation', function () {
		expect(InputKey::for('[]'))->toStartWith('value_');
	});
});

// ------------------------------------------------------------
// EndpointMeta - caption
// ------------------------------------------------------------

describe('EndpointMeta - caption', function () {

	test('words the extension it knows', function () {
		expect(EndpointMeta::caption('since', 'v2.0'))->toBe('since v2.0');
	});

	test('hands back an extension it does not know exactly as the document wrote it', function () {
		expect(EndpointMeta::caption('handler', 'VoucherController@index'))->toBe('VoucherController@index');
	});
});

// ------------------------------------------------------------
// EndpointMeta - icon
// ------------------------------------------------------------

describe('EndpointMeta - icon', function () {

	test('names an icon for the extensions it knows', function () {
		expect(EndpointMeta::icon('rate-limit'))->toBe('heroicon-o-clock')
			->and(EndpointMeta::icon('handler'))->toBe('heroicon-o-document-text')
			->and(EndpointMeta::icon('since'))->toBe('heroicon-o-bolt')
			->and(EndpointMeta::icon('abilities'))->toBe('heroicon-o-key');
	});

	test('leaves an extension it does not know without one', function () {
		// An icon is a claim about the value beside it, and a wrong claim is worse
		// than none.
		expect(EndpointMeta::icon('audience'))->toBeNull();
	});
});

// ------------------------------------------------------------
// PathParts - sharedPrefix
// ------------------------------------------------------------

describe('PathParts - sharedPrefix', function () {

	test('reads the prefix every path of a group shares', function () {
		expect(PathParts::sharedPrefix([
			'/v1/physical-products',
			'/v1/physical-products/{physicalProduct}',
			'/v1/physical-products/{physicalProduct}/variants',
		]))->toBe('/v1/physical-products');
	});

	test('cuts the prefix at a segment, not inside a word', function () {
		// `/orders` and `/order-items` share five letters and no segment.
		expect(PathParts::sharedPrefix(['/v1/orders', '/v1/order-items']))->toBe('/v1');
	});

	test('takes the whole path when two methods answer on it', function () {
		expect(PathParts::sharedPrefix(['/v1/leads', '/v1/leads']))->toBe('/v1/leads');
	});

	test('shares nothing with a single path', function () {
		expect(PathParts::sharedPrefix(['/v1/health']))->toBe('');
	});

	test('shares nothing when the first segment already differs', function () {
		expect(PathParts::sharedPrefix(['/orders', '/vouchers']))->toBe('');
	});
});

// ------------------------------------------------------------
// PathParts - within
// ------------------------------------------------------------

describe('PathParts - within', function () {

	test('drops the prefix the group heading already carries', function () {
		expect(PathParts::within('/v1/orders/{order}', '/v1/orders'))->toBe('/{order}');
	});

	test('collapses what repeats down the group and keeps the last segment whole', function () {
		// `/{order}` on every row says nothing the group has not said; `subscriptions`
		// is the reason this row exists.
		expect(PathParts::within('/v1/orders/{order}/subscriptions', '/v1/orders'))
			->toBe('/…/subscriptions');
	});

	test('reads the collection itself as the root of its group', function () {
		expect(PathParts::within('/v1/orders', '/v1/orders'))->toBe('/');
	});

	test('keeps a path that has one segment left whole', function () {
		expect(PathParts::within('/v1/health', '/v1'))->toBe('/health');
	});

	test('takes the path as it is when nothing is stated elsewhere', function () {
		expect(PathParts::within('/health', ''))->toBe('/health');
	});
});

// ------------------------------------------------------------
// InlineMarkdown - toHtml
// ------------------------------------------------------------

describe('InlineMarkdown - toHtml', function () {

	test('sets a code span as code', function () {
		expect(InlineMarkdown::toHtml('Alias of `discount_value`.'))
			->toBe('Alias of <code class="fae-inline-code">discount_value</code>.');
	});

	test('escapes the text around it', function () {
		expect(InlineMarkdown::toHtml('Pass <script> in `name`'))
			->toBe('Pass &lt;script&gt; in <code class="fae-inline-code">name</code>');
	});

	test('escapes what is inside it', function () {
		// A document is generated from source, and source is not to be trusted with
		// markup any more than a user is.
		expect(InlineMarkdown::toHtml('`<img onerror=alert(1)>`'))
			->toContain('&lt;img onerror=alert(1)&gt;')
			->not->toContain('<img');
	});

	test('leaves a lone backtick where it is', function () {
		expect(InlineMarkdown::toHtml('a ` b'))->toBe('a ` b');
	});

	test('makes nothing of nothing', function () {
		expect(InlineMarkdown::toHtml(null))->toBe('');
	});
});

// ------------------------------------------------------------
// SecretHeaders - isSecret
// ------------------------------------------------------------

describe('SecretHeaders - isSecret', function () {

	test('recognises a header that carries a credential', function (string $name) {
		expect(SecretHeaders::isSecret($name))->toBeTrue();
	})->with([
		['Authorization'],
		['authorization'],
		['X-Api-Key'],
		['Cookie'],
		['X-Signature'],
		['X-Auth-Token'],
	]);

	test('leaves an ordinary header alone', function (string $name) {
		expect(SecretHeaders::isSecret($name))->toBeFalse();
	})->with([
		['Accept-Language'],
		['If-None-Match'],
		['Content-Type'],
	]);
});

// ------------------------------------------------------------
// SecretHeaders - redact
// ------------------------------------------------------------

describe('SecretHeaders - redact', function () {

	test('keeps the auth scheme and replaces the credential', function () {
		expect(SecretHeaders::redact('Bearer abc123', '$TOKEN'))->toBe('Bearer $TOKEN')
			->and(SecretHeaders::redact('Basic dXNlcjpwYXNz', '$TOKEN'))->toBe('Basic $TOKEN');
	});

	test('replaces a bare credential entirely', function () {
		expect(SecretHeaders::redact('abc123', '$TOKEN'))->toBe('$TOKEN');
	});

	test('redacts only the credential-bearing headers of a set', function () {
		$redacted = SecretHeaders::redactAll([
			'Authorization' => 'Bearer abc123',
			'Accept-Language' => 'de',
		], '$TOKEN');

		expect($redacted)->toBe([
			'Authorization' => 'Bearer $TOKEN',
			'Accept-Language' => 'de',
		]);
	});
});

// ------------------------------------------------------------
// MediaType - label
// ------------------------------------------------------------

describe('MediaType - label', function () {

	test('drops the prefix every api type carries', function () {
		expect(MediaType::label('application/json'))->toBe('json')
			->and(MediaType::label('application/xml'))->toBe('xml')
			->and(MediaType::label('application/vnd.api+json'))->toBe('vnd.api+json');
	});

	test('leaves a type whose first half is the distinguishing one', function () {
		// `text/csv` and `image/png` are told apart by exactly the part a shortening
		// would drop.
		expect(MediaType::label('text/csv'))->toBe('text/csv')
			->and(MediaType::label(null))->toBe('');
	});
});
