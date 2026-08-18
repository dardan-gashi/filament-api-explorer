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
        'request_body' => 'Request body',
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
        'field_search' => 'Find field',
        'copy_link' => 'Copy link',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'server' => 'Server',
        'send' => 'Send',
        'sending' => 'Sending…',
        'reset' => 'Reset',
        'duration' => ':duration ms',
        'value' => 'Value',
        'toggle' => 'Expand or collapse',
        'discard_sample' => 'Discard',
    ],

    /*
     | The badges carry the words the specification itself uses, because those are
     | the words a reader will search the document for. What they mean is said once,
     | here, instead of on every row.
     */
    'legend' => [
        'nullable' => 'can be null',
        'deprecated' => 'will be removed',
    ],

    /*
     | Captions the explorer can name. Any other `x-` extension is shown as it is.
     */
    'meta' => [
        'since' => 'since :value',
    ],

    'gaps' => [
        'description' => 'No summary or description',
        'responses' => 'No response documented',
        'response_schema' => 'Successful response without a schema',
        'parameters' => 'Parameters without a description',
        'request_body' => 'No request body documented',
    ],

    'notes' => [
        'unsafe_method' => 'The explorer only sends GET requests, so this endpoint can be read but not tried.',
        'sending_disabled' => 'Sending requests is disabled for this panel.',
        'missing_credentials' => 'The :headers field is empty, so the header was not sent at all.',
        'capture' => 'Send it once — the real response replaces the structure example here.',
    ],

    'empty' => [
        'spec' => 'No OpenAPI document could be loaded.',
        'endpoint' => 'Select an endpoint to read its documentation.',
        'fields' => 'No structured fields documented.',
        'field_match' => 'No field matches.',
        'body' => 'No body documented.',
        'parameters' => 'No parameters documented.',
    ],

    'examples' => [
        'captured' => 'Real response · :time',
        'documented' => 'Example from the specification',
        'synthesised' => 'Structure only, no real values',
        'request' => 'Request body',
    ],

    'notifications' => [
        'refused' => 'Request refused',
        'failed' => 'Request failed',
    ],

];
