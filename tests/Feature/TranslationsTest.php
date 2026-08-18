<?php

declare(strict_types=1);

use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Enums\HttpMethod;
use DardanGashi\FilamentApiExplorer\Pages\ApiExplorerPage;

use function Pest\Livewire\livewire;

// ----------------------------------------------------------------------------------
// Translations Test Suite
// Sections: Locales, Page
// ----------------------------------------------------------------------------------

/**
 * @return list<string>
 */
function translationKeys(string $locale): array
{
    /** @var array<string, mixed> $translations */
    $translations = require __DIR__."/../../resources/lang/{$locale}/explorer.php";

    $keys = [];

    foreach ($translations as $group => $entries) {
        foreach (array_keys((array) $entries) as $key) {
            $keys[] = "{$group}.{$key}";
        }
    }

    sort($keys);

    return $keys;
}

// ------------------------------------------------------------
// Translations - Locales
// ------------------------------------------------------------

describe('Translations - Locales', function () {

    test('ships the same keys in every locale', function () {
        expect(translationKeys('de'))->toBe(translationKeys('en'));
    });
});

// ------------------------------------------------------------
// Translations - Page
// ------------------------------------------------------------

describe('Translations - Page', function () {

    test('renders the interface in the panel locale', function () {
        app()->setLocale('de');

        livewire(ApiExplorerPage::class)
            ->assertSee('Endpunkt suchen')
            ->assertSee('Lücken')
            ->assertSee('Anfrage-Header')
            ->assertSee('Query-Parameter')
            ->assertSee('Antworten')
            ->assertSee('Feld suchen')
            // A key in the wrong group resolves to nothing and renders as itself,
            // which no assertion on the markup around it would notice.
            ->assertSee('Blättern')
            ->assertSee('Zurück')
            ->assertSee('57 % dokumentiert')
            ->assertSee('Snapshot vom');
    });

    test('names the origin of an example in the panel locale', function () {
        app()->setLocale('de');

        livewire(ApiExplorerPage::class)
            ->assertSee('Beispiel aus der Spezifikation')
            ->assertSee('Nur die Struktur, keine echten Werte')
            ->assertSee('Einmal senden');
    });

    test('names the gaps in the panel locale', function () {
        app()->setLocale('de');

        livewire(ApiExplorerPage::class)
            ->call('filterGaps', true)
            ->assertSee('Keine Zusammenfassung oder Beschreibung')
            ->assertSee('Keine Antwort dokumentiert')
            // A gap is named on the endpoint that has it, so the body gap needs the
            // endpoint that takes a body without documenting one.
            ->call('selectEndpoint', Endpoint::keyFor(HttpMethod::Patch, '/participants/{participant}'))
            ->assertSee('Kein Anfrage-Body dokumentiert');
    });

    test('falls back to english for a locale it does not ship', function () {
        app()->setLocale('fr');

        livewire(ApiExplorerPage::class)->assertSee('Endpoint');
    });
});
