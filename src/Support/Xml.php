<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use DOMDocument;

/**
 * The two things this page does with XML: write an example of it, and make one
 * it received readable.
 */
final class Xml
{
	/**
	 * A value rendered as an XML document.
	 *
	 * A list repeats its element rather than wrapping it, which is what OpenAPI
	 * does by default (`xml.wrapped` is false unless a document says otherwise).
	 * A list at the very top has no name to repeat, so its entries become `item`
	 * — a document with one root element is the one thing XML insists on.
	 */
	public static function encode(mixed $value, string $root = 'response'): string
	{
		$value = is_array($value) && array_is_list($value) ? ['item' => $value] : $value;

		return '<?xml version="1.0" encoding="UTF-8"?>'."\n".self::element($root, $value, 0);
	}

	/**
	 * A received document, indented. Null when it does not parse, so a caller can
	 * fall back to showing it exactly as it arrived.
	 */
	public static function format(string $xml): ?string
	{
		// An empty string is a ValueError rather than a parse error, and a document
		// with nothing in it has nothing to indent either way.
		if (trim($xml) === '' || !class_exists(DOMDocument::class)) {
			return null;
		}

		$document = new DOMDocument;
		$document->preserveWhiteSpace = false;
		$document->formatOutput = true;

		// Entities are not loaded — libxml has refused that by default for years,
		// and this parses a body from whichever server the reader pointed at.
		$errors = libxml_use_internal_errors(true);
		$parsed = $document->loadXML($xml, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		if (!$parsed) {
			return null;
		}

		$formatted = $document->saveXML();

		return $formatted === false ? null : trim($formatted);
	}

	private static function element(string $name, mixed $value, int $depth): string
	{
		$pad = str_repeat('  ', $depth);

		if (is_array($value) && array_is_list($value)) {
			if ($value === []) {
				return $pad.'<'.$name.'/>';
			}

			return implode("\n", array_map(
				fn (mixed $entry): string => self::element($name, $entry, $depth),
				$value,
			));
		}

		if (is_array($value)) {
			$children = [];

			foreach ($value as $key => $child) {
				$children[] = self::element(self::tag((string) $key), $child, $depth + 1);
			}

			return $pad.'<'.$name.'>'."\n".implode("\n", $children)."\n".$pad.'</'.$name.'>';
		}

		if ($value === null) {
			return $pad.'<'.$name.'/>';
		}

		return $pad.'<'.$name.'>'.self::text($value).'</'.$name.'>';
	}

	/**
	 * A JSON key is not necessarily an element name: it can start with a digit or
	 * hold a space, and an invalid name would produce a document nothing parses.
	 */
	private static function tag(string $key): string
	{
		$name = (string) preg_replace('/[^A-Za-z0-9_.-]+/', '-', $key);
		$name = trim($name, '-');

		if ($name === '' || !preg_match('/^[A-Za-z_]/', $name)) {
			$name = 'item'.($name === '' ? '' : '-'.$name);
		}

		return $name;
	}

	private static function text(mixed $value): string
	{
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}

		return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}
