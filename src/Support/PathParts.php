<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use Illuminate\Support\Str;

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
     * The longest prefix every one of these paths shares, cut at a segment
     * boundary. A single path shares nothing with anybody.
     *
     * @param  list<string>  $paths
     */
    public static function sharedPrefix(array $paths): string
    {
        if (count($paths) < 2) {
            return '';
        }

        $segmented = array_map(
            fn (string $path): array => explode('/', trim($path, '/')),
            $paths,
        );

        $first = array_shift($segmented);
        $shared = [];

        foreach ($first as $index => $segment) {
            foreach ($segmented as $other) {
                if (($other[$index] ?? null) !== $segment) {
                    return $shared === [] ? '' : '/'.implode('/', $shared);
                }
            }

            $shared[] = $segment;
        }

        // A path explodes into at least one segment, so reaching this line means at
        // least one of them was shared.
        return '/'.implode('/', $shared);
    }

    /**
     * How a path reads inside its group.
     *
     * The prefix the group shares is written on its heading, so the row carries
     * only what is left of it. What lies between that and the last segment
     * collapses: a `{order}` repeated down the whole group says nothing the group
     * has not said already. The last segment is never touched — that is where one
     * endpoint differs from the next.
     */
    public static function within(string $path, string $prefix): string
    {
        $remainder = trim($prefix === '' ? $path : (string) Str::after($path, $prefix), '/');

        if ($remainder === '') {
            return '/';
        }

        $segments = explode('/', $remainder);
        $last = array_pop($segments);

        return $segments === [] ? '/'.$last : '/…/'.$last;
    }

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
