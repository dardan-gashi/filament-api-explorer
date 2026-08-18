<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Highlighting;

/**
 * Marks up the Python sample.
 */
final class PythonHighlighter
{
    private const COMMENT = '(?P<comment>#[^\n]*)';

    /**
     * A key of a dictionary, which in Python is always a quoted string.
     */
    private const PROPERTY = '(?P<property>\'(?:\\\\.|[^\'\\\\])*\'(?=\s*:)|"(?:\\\\.|[^"\\\\])*"(?=\s*:))';

    /**
     * An f-string, the only kind that interpolates — and so the only kind a
     * credential can be written into.
     */
    private const FORMAT = '(?P<template>[fF]\'(?:\\\\.|[^\'\\\\])*\'|[fF]"(?:\\\\.|[^"\\\\])*")';

    private const STRING = '(?P<string>\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")';

    private const KEYWORD = '(?P<keyword>\b(?:and|as|assert|async|await|break|class|continue|def|del|elif|else|except|finally|for|from|global|if|import|in|is|lambda|not|or|pass|raise|return|try|while|with|yield)\b)';

    private const LITERAL = '(?P<literal>\b(?:True|False|None)\b)';

    private const CALL = '(?P<call>[A-Za-z_]\w*(?=\())';

    private const NUMBER = '(?P<number>\b\d+(?:\.\d+)?\b)';

    /**
     * The f-string comes before the plain one, or the prefix would be left
     * behind as a name of its own.
     */
    private const TOKENS = '/'
        .self::COMMENT.'|'
        .self::PROPERTY.'|'
        .self::FORMAT.'|'
        .self::STRING.'|'
        .self::KEYWORD.'|'
        .self::LITERAL.'|'
        .self::CALL.'|'
        .self::NUMBER.'/';

    private const IN_FORMAT = '/(?P<variable>\{[^{}]*\})/';

    public static function highlight(string $code): string
    {
        return Highlighter::markUp($code, self::TOKENS, ['template' => self::IN_FORMAT]);
    }
}
