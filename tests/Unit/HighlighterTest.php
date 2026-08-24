<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;
use DardanGashi\FilamentApiExplorer\Highlighting\Highlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\HttpHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\JavaScriptHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\JsonHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\PhpHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\PythonHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\ShellHighlighter;
use DardanGashi\FilamentApiExplorer\Highlighting\SnippetHighlighter;

// ----------------------------------------------------------------------------------
// Highlighter Test Suite
// Sections: Highlighter::markUp, Highlighter::lines, JsonHighlighter::highlight,
//           ShellHighlighter::highlight, HttpHighlighter::highlight,
//           PhpHighlighter::highlight, JavaScriptHighlighter::highlight,
//           PythonHighlighter::highlight, SnippetHighlighter::highlight
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// Highlighter - markUp
// ------------------------------------------------------------

describe('Highlighter - markUp', function () {

	test('names the token class after the group that matched', function () {
		$html = Highlighter::markUp(
			'let x = 1',
			'/(?P<keyword>\blet\b)|(?P<number>\d+)/',
		);

		expect($html)->toBe('<span class="fae-code-keyword">let</span> x = <span class="fae-code-number">1</span>');
	});

	test('escapes what it marks up', function () {
		$html = Highlighter::markUp('"<script>alert(1)</script>"', '/(?P<string>"[^"]*")/');

		expect($html)->not->toContain('<script>')
			->and($html)->toContain('&lt;script&gt;');
	});

	test('escapes what it recognises nothing in', function () {
		expect(Highlighter::markUp('<b>plain</b>', '/(?P<number>\d+)/'))
			->toBe('&lt;b&gt;plain&lt;/b&gt;');
	});

	test('marks up inside a token where a pattern says how', function () {
		// The credential sits inside a string the shell would expand, so the
		// string is marked up and the variable inside it as well.
		$html = Highlighter::markUp(
			'"Bearer $TOKEN"',
			'/(?P<string>"[^"]*")/',
			['string' => '/(?P<variable>\$\w+)/'],
		);

		expect($html)->toBe(
			'<span class="fae-code-string">&quot;Bearer <span class="fae-code-variable">$TOKEN</span>&quot;</span>'
		);
	});

	test('leaves an empty input empty', function () {
		expect(Highlighter::markUp('', '/(?P<number>\d+)/'))->toBe('');
	});
});

// ------------------------------------------------------------
// Highlighter - lines
// ------------------------------------------------------------

describe('Highlighter - lines', function () {

	test('cuts marked-up code into one string per line', function () {
		$lines = Highlighter::lines(JsonHighlighter::highlight("{\n    \"id\": 7\n}"));

		expect($lines)->toHaveCount(3)
			->and($lines[0])->toBe('{')
			->and($lines[1])->toContain('fae-code-property')
			->and($lines[2])->toBe('}');
	});

	test('closes a token at the break and opens it again after it', function () {
		// A heredoc, a template literal, a quoted string broken over two lines: the
		// span outlives the line, and half a span on each line is markup a browser
		// repairs by guessing.
		$lines = Highlighter::lines("<span class=\"fae-code-string\">&quot;a\nb&quot;</span> tail");

		expect($lines)->toBe([
			'<span class="fae-code-string">&quot;a</span>',
			'<span class="fae-code-string">b&quot;</span> tail',
		]);
	});

	test('keeps a blank line as a line of its own', function () {
		// The blank line between headers and body in the raw HTTP sample is not
		// decoration — dropping it would renumber everything under it.
		expect(Highlighter::lines("a\n\nb"))->toBe(['a', '', 'b']);
	});

	test('gives an empty input one empty line', function () {
		expect(Highlighter::lines(''))->toBe(['']);
	});
});

// ------------------------------------------------------------
// JsonHighlighter - highlight
// ------------------------------------------------------------

describe('JsonHighlighter - highlight', function () {

	test('marks up keys, strings, numbers and literals', function () {
		$html = JsonHighlighter::highlight('{"code": "SUMMER10", "total": 42, "is_active": true}');

		expect($html)->toContain('<span class="fae-code-property">&quot;code&quot;</span>')
			->and($html)->toContain('<span class="fae-code-string">&quot;SUMMER10&quot;</span>')
			->and($html)->toContain('<span class="fae-code-number">42</span>')
			->and($html)->toContain('<span class="fae-code-literal">true</span>');
	});

	test('escapes the payload it marks up', function () {
		$html = JsonHighlighter::highlight('{"note": "<script>alert(1)</script>"}');

		expect($html)->not->toContain('<script>')
			->and($html)->toContain('&lt;script&gt;');
	});

	test('escapes a payload it recognises nothing in', function () {
		expect(JsonHighlighter::highlight('<b>plain</b>'))->toBe('&lt;b&gt;plain&lt;/b&gt;');
	});

	test('leaves an empty payload empty', function () {
		expect(JsonHighlighter::highlight(''))->toBe('');
	});
});

// ------------------------------------------------------------
// ShellHighlighter - highlight
// ------------------------------------------------------------

describe('ShellHighlighter - highlight', function () {

	test('marks up the command, its flags and its arguments', function () {
		$html = ShellHighlighter::highlight("curl -G \\\n  \"https://example.test/orders\"");

		expect($html)->toContain('<span class="fae-code-call">curl</span>')
			->and($html)->toContain('<span class="fae-code-keyword">-G</span>')
			->and($html)->toContain('<span class="fae-code-string">&quot;https://example.test/orders&quot;</span>');
	});

	test('marks up the credential the shell would expand inside the quotes', function () {
		$html = ShellHighlighter::highlight('  -H "Authorization: Bearer $TOKEN"');

		expect($html)->toContain('Bearer <span class="fae-code-variable">$TOKEN</span>');
	});

	test('takes a flag only where an argument starts', function () {
		// A hyphen inside a path is part of the path, not an option.
		$html = ShellHighlighter::highlight('  "https://example.test/physical-products"');

		expect($html)->not->toContain('fae-code-keyword');
	});

	test('marks up a comment', function () {
		expect(ShellHighlighter::highlight('# the token is yours'))
			->toContain('<span class="fae-code-comment"># the token is yours</span>');
	});
});

// ------------------------------------------------------------
// HttpHighlighter - highlight
// ------------------------------------------------------------

describe('HttpHighlighter - highlight', function () {

	test('marks up the request line', function () {
		$html = HttpHighlighter::highlight('GET /api/v1/orders?per_page=25 HTTP/1.1');

		expect($html)->toContain('<span class="fae-code-keyword">GET</span>')
			->and($html)->toContain('<span class="fae-code-literal">HTTP/1.1</span>');
	});

	test('marks up a field name and leaves its value alone', function () {
		$html = HttpHighlighter::highlight("Host: api.bookshop.test\nAccept: application/json");

		expect($html)->toContain('<span class="fae-code-property">Host</span>: api.bookshop.test')
			->and($html)->toContain('<span class="fae-code-property">Accept</span>: application/json');
	});

	test('marks up the credential the editors resolve', function () {
		$html = HttpHighlighter::highlight('Authorization: Bearer {{token}}');

		expect($html)->toContain('Bearer <span class="fae-code-variable">{{token}}</span>');
	});
});

// ------------------------------------------------------------
// PhpHighlighter - highlight
// ------------------------------------------------------------

describe('PhpHighlighter - highlight', function () {

	test('marks up keywords, variables and calls', function () {
		$html = PhpHighlighter::highlight("use Illuminate\\Support\\Facades\\Http;\n\n\$response = Http::withHeaders([])->get('https://example.test');");

		expect($html)->toContain('<span class="fae-code-keyword">use</span>')
			->and($html)->toContain('<span class="fae-code-variable">$response</span>')
			->and($html)->toContain('<span class="fae-code-call">withHeaders</span>')
			->and($html)->toContain('<span class="fae-code-string">&#039;https://example.test&#039;</span>');
	});

	test('marks up a name being called and not one being mentioned', function () {
		$html = PhpHighlighter::highlight('$data = Http::get($url);');

		expect($html)->toContain('<span class="fae-code-call">get</span>')
			->and($html)->not->toContain('<span class="fae-code-call">Http</span>');
	});

	test('marks up the credential interpolated into a string', function () {
		$html = PhpHighlighter::highlight('$headers = ["Authorization" => "Bearer $token"];');

		expect($html)->toContain('<span class="fae-code-variable">$token</span>');
	});

	test('draws true, false and null like the words they are', function () {
		$html = PhpHighlighter::highlight('$flags = [true, false, null];');

		expect(substr_count($html, '<span class="fae-code-literal">'))->toBe(3);
	});

	test('escapes the sample it marks up', function () {
		$html = PhpHighlighter::highlight("\$note = '<script>alert(1)</script>';");

		expect($html)->not->toContain('<script>')
			->and($html)->toContain('&lt;script&gt;');
	});

	test('leaves an empty sample empty', function () {
		expect(PhpHighlighter::highlight(''))->toBe('');
	});
});

// ------------------------------------------------------------
// JavaScriptHighlighter - highlight
// ------------------------------------------------------------

describe('JavaScriptHighlighter - highlight', function () {

	test('marks up keywords, calls and object keys', function () {
		$html = JavaScriptHighlighter::highlight("const response = await fetch(url, {\n    headers: { Accept: 'application/json' },\n})");

		expect($html)->toContain('<span class="fae-code-keyword">const</span>')
			->and($html)->toContain('<span class="fae-code-keyword">await</span>')
			->and($html)->toContain('<span class="fae-code-call">fetch</span>')
			->and($html)->toContain('<span class="fae-code-property">headers</span>')
			->and($html)->toContain('<span class="fae-code-string">&#039;application/json&#039;</span>');
	});

	test('reads a quoted key as a key', function () {
		// A header name with a hyphen has to be quoted to be a valid key, and it
		// is still a key rather than a string.
		$html = JavaScriptHighlighter::highlight("{ 'X-Ability': 'orders:read' }");

		expect($html)->toContain('<span class="fae-code-property">&#039;X-Ability&#039;</span>')
			->and($html)->toContain('<span class="fae-code-string">&#039;orders:read&#039;</span>');
	});

	test('marks up the credential interpolated into a template literal', function () {
		$html = JavaScriptHighlighter::highlight('Authorization: `Bearer ${token}`,');

		expect($html)->toContain(
			'<span class="fae-code-template">`Bearer <span class="fae-code-variable">${token}</span>`</span>'
		);
	});

	test('marks up a comment', function () {
		expect(JavaScriptHighlighter::highlight('// the token is yours'))
			->toContain('<span class="fae-code-comment">// the token is yours</span>');
	});
});

// ------------------------------------------------------------
// PythonHighlighter - highlight
// ------------------------------------------------------------

describe('PythonHighlighter - highlight', function () {

	test('marks up keywords, calls and dictionary keys', function () {
		$html = PythonHighlighter::highlight("import requests\n\nresponse = requests.get('https://example.test', headers={'Accept': 'application/json'})");

		expect($html)->toContain('<span class="fae-code-keyword">import</span>')
			->and($html)->toContain('<span class="fae-code-call">get</span>')
			->and($html)->toContain('<span class="fae-code-property">&#039;Accept&#039;</span>')
			->and($html)->toContain('<span class="fae-code-string">&#039;application/json&#039;</span>');
	});

	test('marks up the credential interpolated into an f-string', function () {
		$html = PythonHighlighter::highlight("'Authorization': f'Bearer {token}',");

		expect($html)->toContain(
			'<span class="fae-code-template">f&#039;Bearer <span class="fae-code-variable">{token}</span>&#039;</span>'
		);
	});

	test('reads a string without the prefix as a plain string', function () {
		// Only an f-string interpolates, so braces in any other one are braces.
		$html = PythonHighlighter::highlight("url = 'https://example.test/orders/{order}'");

		expect($html)->not->toContain('fae-code-variable')
			->and($html)->toContain('<span class="fae-code-string">');
	});

	test('marks up a comment', function () {
		expect(PythonHighlighter::highlight('# the token is yours'))
			->toContain('<span class="fae-code-comment"># the token is yours</span>');
	});
});

// ------------------------------------------------------------
// SnippetHighlighter - highlight
// ------------------------------------------------------------

describe('SnippetHighlighter - highlight', function () {

	test('reads a curl sample as a shell command', function () {
		expect(SnippetHighlighter::highlight('curl -X POST', SnippetLanguage::Curl))
			->toContain('<span class="fae-code-keyword">-X</span>');
	});

	test('reads a raw request as HTTP', function () {
		expect(SnippetHighlighter::highlight('GET /api/v1/orders HTTP/1.1', SnippetLanguage::Http))
			->toContain('<span class="fae-code-keyword">GET</span>');
	});

	test('reads a PHP sample as PHP', function () {
		expect(SnippetHighlighter::highlight('$response = Http::get($url);', SnippetLanguage::Php))
			->toContain('<span class="fae-code-variable">$response</span>');
	});

	test('reads a JavaScript sample as JavaScript', function () {
		expect(SnippetHighlighter::highlight('const response = await fetch(url)', SnippetLanguage::JavaScript))
			->toContain('<span class="fae-code-keyword">const</span>');
	});

	test('reads a Python sample as Python', function () {
		expect(SnippetHighlighter::highlight('import requests', SnippetLanguage::Python))
			->toContain('<span class="fae-code-keyword">import</span>');
	});
});
