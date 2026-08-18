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
        'request_body' => 'Anfrage-Body',
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
        'toggle' => 'Ein- oder ausklappen',
        'discard_sample' => 'Verwerfen',
    ],

    'gaps' => [
        'description' => 'Keine Zusammenfassung oder Beschreibung',
        'responses' => 'Keine Antwort dokumentiert',
        'response_schema' => 'Erfolgsantwort ohne Schema',
        'parameters' => 'Parameter ohne Beschreibung',
        'request_body' => 'Kein Anfrage-Body dokumentiert',
    ],

    'notes' => [
        'unsafe_method' => 'Der Explorer sendet nur GET-Anfragen, dieser Endpunkt ist daher nur lesbar.',
        'sending_disabled' => 'Das Senden von Anfragen ist für dieses Panel deaktiviert.',
        'missing_credentials' => 'Das Feld :headers ist leer — der Header wurde nicht mitgesendet.',
        'capture' => 'Einmal senden — die echte Antwort ersetzt hier das Struktur-Beispiel.',
    ],

    'empty' => [
        'spec' => 'Es konnte kein OpenAPI-Dokument geladen werden.',
        'endpoint' => 'Endpunkt auswählen, um die Dokumentation zu lesen.',
        'fields' => 'Keine strukturierten Felder dokumentiert.',
        'field_match' => 'Kein Feld passt.',
        'body' => 'Kein Body dokumentiert.',
        'parameters' => 'Keine Parameter dokumentiert.',
    ],

    'examples' => [
        'captured' => 'Echte Antwort · :time',
        'documented' => 'Beispiel aus der Spezifikation',
        'synthesised' => 'Nur die Struktur, keine echten Werte',
        'request' => 'Anfrage-Body',
    ],

    'notifications' => [
        'refused' => 'Anfrage abgelehnt',
        'failed' => 'Anfrage fehlgeschlagen',
    ],

];
