<?php

declare(strict_types=1);

return [

    'navigation' => [
        'label' => 'API Explorer',
    ],

    'header' => [
        'snapshot' => 'Snapshot of :time',
        'documented' => ':percentage % documented',
        'version' => 'Version',
        'endpoints' => ':count endpoints',
    ],

    'sidebar' => [
        'search' => 'Endpoint',
        'all' => 'All',
        'gaps' => 'Gaps',
        'empty' => 'No endpoint matches.',
        'incomplete' => 'Documentation is incomplete',
    ],

    'sections' => [
        'path' => 'Path parameters',
        'header' => 'Request headers',
        'query' => 'Query parameters',
        'cookie' => 'Cookies',
        'request' => 'Request',
        'responses' => 'Responses',
        'response_headers' => 'Response headers',
        'sender' => 'Send request',
        'live_response' => 'Live response',
    ],

    'labels' => [
        'required' => 'required',
        'nullable' => 'nullable',
        'deprecated' => 'deprecated',
        'default' => 'Default :value',
        'example' => 'Example :value',
        'field_search' => 'Find field',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'server' => 'Server',
        'send' => 'Send',
        'sending' => 'Sending…',
        'reset' => 'Reset',
        'duration' => ':duration ms',
        'value' => 'Value',
    ],

    'gaps' => [
        'description' => 'No summary or description',
        'responses' => 'No response documented',
        'response_schema' => 'Successful response without a schema',
        'parameters' => 'Parameters without a description',
    ],

    'notes' => [
        'unsafe_method' => 'The explorer only sends GET requests, so this endpoint can be read but not tried.',
        'sending_disabled' => 'Sending requests is disabled for this panel.',
        'request_body' => 'Request bodies are not documented yet.',
    ],

    'empty' => [
        'spec' => 'No OpenAPI document could be loaded.',
        'endpoint' => 'Select an endpoint to read its documentation.',
        'fields' => 'No structured fields documented.',
        'field_match' => 'No field matches.',
        'body' => 'No body documented.',
        'parameters' => 'No parameters documented.',
    ],

    'notifications' => [
        'refused' => 'Request refused',
        'failed' => 'Request failed',
    ],

];
