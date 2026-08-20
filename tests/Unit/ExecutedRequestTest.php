<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\ExecutedRequest;

// ----------------------------------------------------------------------------------
// ExecutedRequest Test Suite
// Sections: failed, isSuccessful, color, prettyBody, toLivewire, fromLivewire
// ----------------------------------------------------------------------------------

// ------------------------------------------------------------
// ExecutedRequest - failed
// ------------------------------------------------------------

describe('ExecutedRequest - failed', function () {

    test('records a request that never produced a response', function () {
        $result = ExecutedRequest::failed('Connection timed out', durationMs: 10_000);

        expect($result->hasFailed())->toBeTrue()
            ->and($result->status)->toBe(0)
            ->and($result->error)->toBe('Connection timed out')
            ->and($result->durationMs)->toBe(10_000);
    });
});

// ------------------------------------------------------------
// ExecutedRequest - isSuccessful
// ------------------------------------------------------------

describe('ExecutedRequest - isSuccessful', function () {

    test('reads a 2xx response as successful', function () {
        expect((new ExecutedRequest(status: 204))->isSuccessful())->toBeTrue()
            ->and((new ExecutedRequest(status: 422))->isSuccessful())->toBeFalse();
    });

    test('never reads a failure as successful', function () {
        expect(ExecutedRequest::failed('gone')->isSuccessful())->toBeFalse();
    });
});

// ------------------------------------------------------------
// ExecutedRequest - color
// ------------------------------------------------------------

describe('ExecutedRequest - color', function () {

    test('colours by status', function () {
        expect((new ExecutedRequest(status: 200))->color())->toBe('success')
            ->and((new ExecutedRequest(status: 500))->color())->toBe('danger');
    });

    test('colours a failure as an error', function () {
        expect(ExecutedRequest::failed('gone')->color())->toBe('danger');
    });
});

// ------------------------------------------------------------
// ExecutedRequest - prettyBody
// ------------------------------------------------------------

describe('ExecutedRequest - prettyBody', function () {

    test('indents a json body', function () {
        $result = new ExecutedRequest(status: 200, body: '{"code":"SUMMER10"}');

        expect($result->prettyBody())->toBe(implode("\n", [
            '{',
            '    "code": "SUMMER10"',
            '}',
        ]));
    });

    test('leaves a body that is not json untouched', function () {
        expect((new ExecutedRequest(status: 200, body: 'plain text'))->prettyBody())->toBe('plain text');
    });

    test('leaves an empty body empty', function () {
        expect((new ExecutedRequest(status: 204))->prettyBody())->toBe('');
    });

    test('does not escape the slashes of a url', function () {
        $result = new ExecutedRequest(status: 200, body: '{"url":"https://api.bookshop.test/api"}');

        expect($result->prettyBody())->toContain('https://api.bookshop.test/api');
    });
});

// ------------------------------------------------------------
// ExecutedRequest - toLivewire
// ------------------------------------------------------------

describe('ExecutedRequest - toLivewire', function () {

    test('writes every part of the result into the component state', function () {
        $result = new ExecutedRequest(
            status: 200,
            body: '{}',
            headers: ['ETag' => '"abc"'],
            durationMs: 42,
        );

        expect($result->toLivewire())->toBe([
            'status' => 200,
            'body' => '{}',
            'headers' => ['ETag' => '"abc"'],
            'durationMs' => 42,
            'error' => null,
        ]);
    });
});

// ------------------------------------------------------------
// ExecutedRequest - fromLivewire
// ------------------------------------------------------------

describe('ExecutedRequest - fromLivewire', function () {

    test('rebuilds a result that travelled over the wire', function () {
        $result = new ExecutedRequest(status: 200, body: '{}', headers: ['ETag' => '"abc"'], durationMs: 42);

        expect(ExecutedRequest::fromLivewire($result->toLivewire()))->toEqual($result);
    });

    test('rebuilds a failure', function () {
        $result = ExecutedRequest::failed('Connection timed out', 5);

        expect(ExecutedRequest::fromLivewire($result->toLivewire()))->toEqual($result);
    });

    test('survives state that is missing or the wrong shape', function () {
        $result = ExecutedRequest::fromLivewire(['status' => '200', 'headers' => ['ETag' => ['a', 'b']]]);

        expect($result->status)->toBe(200)
            ->and($result->body)->toBe('')
            ->and($result->headers)->toBe([])
            ->and(ExecutedRequest::fromLivewire(null)->status)->toBe(0);
    });
});
