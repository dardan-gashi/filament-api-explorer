<?php

declare(strict_types=1);

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
            ->assertSee('Endpunkt')
            ->assertSee('Lücken')
            ->assertSee('Anfrage-Header')
            ->assertSee('Query-Parameter')
            ->assertSee('Antworten')
            ->assertSee('Feld suchen')
            ->assertSee('71 % dokumentiert')
            ->assertSee('Snapshot vom');
    });

    test('names the gaps in the panel locale', function () {
        app()->setLocale('de');

        livewire(ApiExplorerPage::class)
            ->call('filterGaps', true)
            ->assertSee('Keine Zusammenfassung oder Beschreibung')
            ->assertSee('Keine Antwort dokumentiert');
    });

    test('falls back to english for a locale it does not ship', function () {
        app()->setLocale('fr');

        livewire(ApiExplorerPage::class)->assertSee('Endpoint');
    });
});
