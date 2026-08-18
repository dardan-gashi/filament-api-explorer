<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Highlighting;

/**
 * Marks up a raw HTTP request.
 */
final class HttpHighlighter
{
    /**
     * The verb of the request line, which is the only all-caps word that starts
     * a line.
     */
    private const METHOD = '(?P<keyword>^[A-Z]+(?= ))';

    private const VERSION = '(?P<literal>HTTP\/[\d.]+)';

    /**
     * A field name: what starts a line and a colon follows.
     */
    private const HEADER = '(?P<property>^[A-Za-z][A-Za-z0-9-]*(?=:))';

    /**
     * The credential, written the way the HTTP clients of the editors read a
     * variable of their environment file.
     */
    private const VARIABLE = '(?P<variable>\{\{\w+\}\})';

    private const TOKENS = '/'.self::METHOD.'|'.self::VERSION.'|'.self::HEADER.'|'.self::VARIABLE.'/m';

    public static function highlight(string $code): string
    {
        return Highlighter::markUp($code, self::TOKENS);
    }
}
