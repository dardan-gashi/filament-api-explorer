<?php

declare(strict_types=1);

return [

    'navigation' => [
        'label' => 'API-Explorer',
    ],

    'header' => [
        'snapshot' => 'Snapshot vom :time',
        'documented' => ':percentage % dokumentiert',
        'version' => 'Version',
        'endpoints' => ':count Endpunkte',
    ],

    'sidebar' => [
        'search' => 'Endpunkt',
        'all' => 'Alle',
        'gaps' => 'Lücken',
        'empty' => 'Kein Endpunkt passt.',
        'incomplete' => 'Dokumentation ist unvollständig',
    ],

    'sections' => [
        'path' => 'Pfad-Parameter',
        'header' => 'Anfrage-Header',
        'query' => 'Query-Parameter',
        'cookie' => 'Cookies',
        'request' => 'Anfrage',
        'responses' => 'Antworten',
        'response_headers' => 'Antwort-Header',
        'sender' => 'Anfrage senden',
        'live_response' => 'Live-Antwort',
    ],

    'labels' => [
        'required' => 'erforderlich',
        'nullable' => 'kann null sein',
        'deprecated' => 'veraltet',
        'default' => 'Standard :value',
        'example' => 'Beispiel :value',
        'field_search' => 'Feld suchen',
        'copy' => 'Kopieren',
        'copied' => 'Kopiert',
        'server' => 'Server',
        'send' => 'Senden',
        'sending' => 'Wird gesendet…',
        'reset' => 'Zurücksetzen',
        'duration' => ':duration ms',
        'value' => 'Wert',
    ],

    'gaps' => [
        'description' => 'Keine Zusammenfassung oder Beschreibung',
        'responses' => 'Keine Antwort dokumentiert',
        'response_schema' => 'Erfolgsantwort ohne Schema',
        'parameters' => 'Parameter ohne Beschreibung',
    ],

    'notes' => [
        'unsafe_method' => 'Der Explorer sendet nur GET-Anfragen, dieser Endpunkt ist daher nur lesbar.',
        'sending_disabled' => 'Das Senden von Anfragen ist für dieses Panel deaktiviert.',
        'request_body' => 'Anfrage-Bodies sind noch nicht dokumentiert.',
    ],

    'empty' => [
        'spec' => 'Es konnte kein OpenAPI-Dokument geladen werden.',
        'endpoint' => 'Endpunkt auswählen, um die Dokumentation zu lesen.',
        'fields' => 'Keine strukturierten Felder dokumentiert.',
        'field_match' => 'Kein Feld passt.',
        'body' => 'Kein Body dokumentiert.',
        'parameters' => 'Keine Parameter dokumentiert.',
    ],

    'notifications' => [
        'refused' => 'Anfrage abgelehnt',
        'failed' => 'Anfrage fehlgeschlagen',
    ],

];
