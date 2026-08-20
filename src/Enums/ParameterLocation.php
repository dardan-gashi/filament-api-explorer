<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Enums;

/**
 * Where a request parameter is carried. The case order is the order the
 * sections are rendered in, so path parameters always precede headers.
 */
enum ParameterLocation: string
{
	case Path = 'path';
	case Header = 'header';
	case Query = 'query';
	case Cookie = 'cookie';

	public function translationKey(): string
	{
		return "filament-api-explorer::explorer.sections.{$this->value}";
	}

	public static function tryFromName(string $name): ?self
	{
		return self::tryFrom(strtolower(trim($name)));
	}
}
