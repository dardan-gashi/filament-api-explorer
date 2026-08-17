<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Enums;

enum HttpMethod: string
{
    case Get = 'get';
    case Post = 'post';
    case Put = 'put';
    case Patch = 'patch';
    case Delete = 'delete';
    case Head = 'head';
    case Options = 'options';

    /**
     * The badge caption. Long verbs are abbreviated so every badge keeps the
     * same width in the endpoint sidebar.
     */
    public function label(): string
    {
        return match ($this) {
            self::Delete => 'DEL',
            self::Options => 'OPT',
            default => strtoupper($this->value),
        };
    }

    /**
     * The Filament colour name used for the method badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::Get, self::Head => 'success',
            self::Post => 'warning',
            self::Put, self::Patch => 'info',
            self::Delete => 'danger',
            self::Options => 'gray',
        };
    }

    /**
     * Safe methods carry no side effects, so the explorer is allowed to send
     * them on the user's behalf.
     */
    public function isSafe(): bool
    {
        return match ($this) {
            self::Get, self::Head, self::Options => true,
            default => false,
        };
    }

    /**
     * Resolve a method from any casing, e.g. an OpenAPI operation key or the
     * `GET` spelling used in a route listing.
     */
    public static function tryFromName(string $name): ?self
    {
        return self::tryFrom(strtolower(trim($name)));
    }
}
