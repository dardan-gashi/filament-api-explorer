<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Support;

/**
 * Marks up the JavaScript sample.
 */
final class JavaScriptHighlighter
{
    private const COMMENT = '(?P<comment>\/\/[^\n]*)';

    /**
     * An object key, quoted or not. A header name with a hyphen in it has to be
     * quoted to be a valid key, and it is still a key.
     */
    private const PROPERTY = '(?P<property>\'(?:\\\\.|[^\'\\\\])*\'(?=\s*:)|"(?:\\\\.|[^"\\\\])*"(?=\s*:)|[A-Za-z_$][\w$]*(?=\s*:))';

    private const TEMPLATE = '(?P<template>`(?:\\\\.|[^`\\\\])*`)';

    private const STRING = '(?P<string>\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")';

    private const KEYWORD = '(?P<keyword>\b(?:async|await|const|function|let|new|return|var)\b)';

    private const LITERAL = '(?P<literal>\b(?:false|null|true|undefined)\b)';

    private const CALL = '(?P<call>[A-Za-z_$][\w$]*(?=\())';

    private const NUMBER = '(?P<number>\b\d+(?:\.\d+)?\b)';

    /**
     * Keys come before strings so a quoted key reads as a key, and both come
     * before everything else so nothing inside a string is mistaken for code.
     */
    private const TOKENS = '/'
        .self::COMMENT.'|'
        .self::PROPERTY.'|'
        .self::TEMPLATE.'|'
        .self::STRING.'|'
        .self::KEYWORD.'|'
        .self::LITERAL.'|'
        .self::CALL.'|'
        .self::NUMBER.'/';

    /**
     * The one thing a template literal is used for in a sample is interpolating
     * the credential, so that is what gets marked up inside it.
     */
    private const IN_TEMPLATE = '/(?P<variable>\$\{[^}]*\})/';

    public static function highlight(string $code): string
    {
        return Highlighter::markUp($code, self::TOKENS, ['template' => self::IN_TEMPLATE]);
    }
}
