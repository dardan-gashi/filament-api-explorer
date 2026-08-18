<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

use Illuminate\Support\Str;

/**
 * How a list of endpoint paths is written short without losing what tells them apart.
 *
 * `/physical-products` and `/physical-products/{physicalProduct}/tier-prices` share
 * everything a reader would use to distinguish them until the very last segment. So
 * the shared part is stated once for the whole group, and the last segment is the
 * one thing that is never touched.
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
}
