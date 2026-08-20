<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

// ----------------------------------------------------------------------------------
// View Markup Test Suite
// Sections: Expressions
// ----------------------------------------------------------------------------------

/**
 * Every Alpine and Livewire expression in the views, cut the way a browser cuts
 * it: from the opening double quote of the attribute to the very next one.
 *
 * @return array<string, string>
 */
function viewExpressions(): array
{
	$expressions = [];

	foreach (File::allFiles(__DIR__.'/../../resources/views') as $file) {
		/** @var SplFileInfo $file */
		preg_match_all(
			'/((?:x-|wire:)[A-Za-z0-9:._-]+)="([^"]*)"/',
			$file->getContents(),
			$matches,
			PREG_SET_ORDER,
		);

		foreach ($matches as $index => [, $attribute, $expression]) {
			$expressions[$file->getFilename().' '.$attribute.' #'.$index] = $expression;
		}
	}

	return $expressions;
}

/**
 * Whether every bracket an expression opens is closed inside it. An expression
 * that was cut in half by a quote never is.
 */
function bracketsBalance(string $expression): bool
{
	$opening = ['{' => '}', '(' => ')', '[' => ']'];
	$stack = [];

	foreach (str_split($expression) as $character) {
		if (isset($opening[$character])) {
			$stack[] = $opening[$character];

			continue;
		}

		if (in_array($character, $opening, true) && array_pop($stack) !== $character) {
			return false;
		}
	}

	return $stack === [];
}

// ------------------------------------------------------------
// View Markup - Expressions
// ------------------------------------------------------------

describe('View Markup - Expressions', function () {

	test('keeps every expression inside the attribute that carries it', function () {
		// A double quote inside an expression ends the attribute where the browser
		// meets it, and the rest of the expression is printed onto the page as
		// text. What is left behind is valid HTML, so nothing but reading the page
		// catches it — the halves of a cut expression do not balance their brackets.
		$cut = array_keys(array_filter(
			viewExpressions(),
			fn (string $expression): bool => !bracketsBalance($expression),
		));

		expect($cut)->toBe([]);
	});

	test('reads the expressions it is checking', function () {
		// The check above passes just as well when the pattern matches nothing.
		expect(viewExpressions())->not->toBeEmpty()
			->and(bracketsBalance("'[data-active=\"true\"]')"))->toBeFalse();
	});
});
