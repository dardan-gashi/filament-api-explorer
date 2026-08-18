<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

/**
 * Splits a path into the part that may be shortened and the part that must not.
 *
 * Endpoint paths in a list are long and differ at the end — `/physical-products`
 * and `/physical-products/{physicalProduct}/tier-prices` share everything a
 * reader would use to tell them apart until the very last segment. Clipping the
 * tail is therefore the one thing a path list must not do.
 */
final class PathParts
{
    /**
     * @return array{head: string, tail: string}
     */
    public static function split(string $path): array
    {
        $position = strrpos($path, '/');

        if ($position === false) {
            return ['head' => '', 'tail' => $path];
        }

        return [
            'head' => substr($path, 0, $position + 1),
            'tail' => substr($path, $position + 1),
        ];
    }
}
