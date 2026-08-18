<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Exceptions\RequestNotAllowed;
use DardanGashi\FilamentApiExplorer\Support\ExecutionPolicy;

// ----------------------------------------------------------------------------------
// ExecutionPolicy Test Suite
// Sections: authorize, unresolved path, placeholder headers, allows, allowedHosts
// ----------------------------------------------------------------------------------

function executionPolicy(bool $enabled = true, array $hosts = ['api.bookshop.test'], array $schemes = ['https', 'http']): ExecutionPolicy
{
    return new ExecutionPolicy(enabled: $enabled, allowedHosts: $hosts, allowedSchemes: $schemes);
}

function policyRequest(string $url = 'https://api.bookshop.test/api/v2/vouchers', HttpMethod $method = HttpMethod::Get): RequestBlueprint
{
    return new RequestBlueprint(method: $method, url: $url);
}

// ------------------------------------------------------------
// ExecutionPolicy - authorize
// ------------------------------------------------------------

describe('ExecutionPolicy - authorize', function () {

    test('passes a safe request to an allowed host', function () {
        executionPolicy()->authorize(policyRequest());
    })->throwsNoExceptions();

    test('refuses every request while sending is disabled', function () {
        executionPolicy(enabled: false)->authorize(policyRequest());
    })->throws(RequestNotAllowed::class, 'disabled');

    test('refuses a method with side effects', function (HttpMethod $method) {
        executionPolicy()->authorize(policyRequest(method: $method));
    })->with([
        [HttpMethod::Post],
        [HttpMethod::Put],
        [HttpMethod::Patch],
        [HttpMethod::Delete],
    ])->throws(RequestNotAllowed::class);

    test('refuses a scheme that is not allowed', function () {
        executionPolicy()->authorize(policyRequest('file:///etc/passwd'));
    })->throws(RequestNotAllowed::class);

    test('refuses a host that is not on the list', function () {
        executionPolicy()->authorize(policyRequest('https://evil.test/api'));
    })->throws(RequestNotAllowed::class, 'evil.test');

    test('refuses a url with no host at all', function () {
        executionPolicy()->authorize(policyRequest('not-a-url'));
    })->throws(RequestNotAllowed::class);

    test('refuses everything when no host is allowed', function () {
        executionPolicy(hosts: [])->authorize(policyRequest());
    })->throws(RequestNotAllowed::class);
});

// ------------------------------------------------------------
// ExecutionPolicy - unresolved path
// ------------------------------------------------------------

describe('ExecutionPolicy - unresolved path', function () {

    test('refuses a request whose path is still a template', function () {
        // Laravel's HTTP client expands `{...}` as an URI template and an unknown
        // placeholder expands to nothing, so this would ask for
        // `/orders//subscriptions` — a different endpoint, or none at all.
        executionPolicy()->authorize(policyRequest('https://api.bookshop.test/api/v2/orders/{order}/subscriptions'));
    })->throws(RequestNotAllowed::class, 'Fill in the path parameter [order] before sending.');

    test('names every segment that is still missing', function () {
        executionPolicy()->authorize(policyRequest('https://api.bookshop.test/api/v2/orders/{order}/items/{item}'));
    })->throws(RequestNotAllowed::class, 'Fill in the path parameters [order], [item] before sending.');

    test('passes a request whose segments are all filled in', function () {
        executionPolicy()->authorize(policyRequest('https://api.bookshop.test/api/v2/orders/42/subscriptions'));
    })->throwsNoExceptions();
});

// ------------------------------------------------------------
// ExecutionPolicy - placeholder headers
// ------------------------------------------------------------

describe('ExecutionPolicy - placeholder headers', function () {

    test('refuses a header that still holds the documented example', function () {
        // `Bearer <token>` describes what belongs in the field. Sending it can only
        // produce a 401, and a 401 nobody understands.
        executionPolicy()->authorize(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
            headers: ['Authorization' => 'Bearer <token>'],
        ));
    })->throws(RequestNotAllowed::class, 'The header [Authorization] still holds the example');

    test('passes a header that holds a real value', function () {
        executionPolicy()->authorize(new RequestBlueprint(
            method: HttpMethod::Get,
            url: 'https://api.bookshop.test/api/v2/vouchers',
            headers: ['Authorization' => 'Bearer 7|abcdef', 'Accept' => 'application/json'],
        ));
    })->throwsNoExceptions();
});

// ------------------------------------------------------------
// ExecutionPolicy - allows
// ------------------------------------------------------------

describe('ExecutionPolicy - allows', function () {

    test('answers without raising', function () {
        expect(executionPolicy()->allows(policyRequest()))->toBeTrue()
            ->and(executionPolicy()->allows(policyRequest('https://evil.test/api')))->toBeFalse();
    });

    test('matches a host pattern', function () {
        $subject = executionPolicy(hosts: ['*.staging.bookshop.test']);

        expect($subject->allows(policyRequest('https://api.staging.bookshop.test/v2')))->toBeTrue()
            ->and($subject->allows(policyRequest('https://api.bookshop.test/v2')))->toBeFalse();
    });

    test('ignores the casing of a host', function () {
        expect(executionPolicy(hosts: ['API.bookshop.TEST'])->allows(policyRequest('https://Api.BOOKSHOP.test/api')))->toBeTrue();
    });

    test('allows a scheme an operator opted into', function () {
        expect(executionPolicy(schemes: ['http'])->allows(policyRequest('http://api.bookshop.test/api')))->toBeTrue()
            ->and(executionPolicy(schemes: ['http'])->allows(policyRequest('https://api.bookshop.test/api')))->toBeFalse();
    });
});

// ------------------------------------------------------------
// ExecutionPolicy - allowedHosts
// ------------------------------------------------------------

describe('ExecutionPolicy - allowedHosts', function () {

    test('reports the hosts it was configured with', function () {
        expect(executionPolicy(hosts: ['api.bookshop.test', '*.example.com'])->allowedHosts())
            ->toBe(['api.bookshop.test', '*.example.com']);
    });
});
