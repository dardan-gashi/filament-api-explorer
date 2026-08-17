<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Illuminate\Contracts\Support\Htmlable;
use DardanGashi\FilamentApiExplorer\Pages\ApiExplorerPage;
use Throwable;
use UnitEnum;

/**
 * Registers the explorer with a panel.
 *
 * Every option falls back to `config/filament-api-explorer.php`, so a panel
 * only states what it wants to differ — and two panels can register the same
 * plugin with different options.
 */
final class ApiExplorerPlugin implements Plugin
{
    public const ID = 'api-explorer';

    private ?string $slug = null;

    private ?string $navigationLabel = null;

    private string|BackedEnum|Htmlable|null $navigationIcon = null;

    private string|UnitEnum|null $navigationGroup = null;

    private ?int $navigationSort = null;

    private ?string $navigationBadge = null;

    private bool $navigationBadgeWasSet = false;

    private ?string $title = null;

    private ?string $description = null;

    private ?string $source = null;

    private ?bool $enabledInProduction = null;

    private ?bool $fullWidth = null;

    private ?bool $requestSending = null;

    private ?Closure $authorizeUsing = null;

    public static function make(): static
    {
        return app(self::class);
    }

    /**
     * The plugin as registered on the current panel, or `null` outside a panel
     * — during a console command, for instance.
     */
    public static function current(): ?self
    {
        try {
            $plugin = Filament::getPlugin(self::ID);
        } catch (Throwable) {
            return null;
        }

        return $plugin instanceof self ? $plugin : null;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        if (app()->isProduction() && ! $this->isEnabledInProduction()) {
            return;
        }

        $panel
            ->pages([
                ApiExplorerPage::make()->slug($this->getSlug()),
            ])
            ->assets([
                Css::make('api-explorer', __DIR__.'/../resources/css/api-explorer.css'),
            ], package: 'dardangashi/filament-api-explorer');
    }

    public function boot(Panel $panel): void {}

    public function slug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationIcon(string|BackedEnum|Htmlable|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    /**
     * @param  'count'|'coverage'|'version'|null  $badge
     */
    public function navigationBadge(?string $badge): static
    {
        $this->navigationBadge = $badge;
        $this->navigationBadgeWasSet = true;

        return $this;
    }

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Which configured source the page opens with.
     */
    public function source(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function enabledInProduction(bool $condition = true): static
    {
        $this->enabledInProduction = $condition;

        return $this;
    }

    public function fullWidth(bool $condition = true): static
    {
        $this->fullWidth = $condition;

        return $this;
    }

    /**
     * Whether the page may send requests and show live responses.
     */
    public function requestSending(bool $condition = true): static
    {
        $this->requestSending = $condition;

        return $this;
    }

    /**
     * Restrict who may open the page, on top of the panel's own access rules.
     *
     * @param  Closure(): bool  $callback
     */
    public function authorizeUsing(Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug ?? $this->configString('page.slug') ?? 'api-explorer';
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel ?? $this->configString('navigation.label');
    }

    public function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->navigationIcon ?? $this->configString('navigation.icon');
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup ?? $this->configString('navigation.group');
    }

    public function getNavigationSort(): ?int
    {
        if ($this->navigationSort !== null) {
            return $this->navigationSort;
        }

        $sort = config('filament-api-explorer.navigation.sort');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public function getNavigationBadgeMode(): ?string
    {
        return $this->navigationBadgeWasSet
            ? $this->navigationBadge
            : $this->configString('navigation.badge');
    }

    public function getTitle(): ?string
    {
        return $this->title ?? $this->configString('page.title');
    }

    public function getDescription(): ?string
    {
        return $this->description ?? $this->configString('page.description');
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function isEnabledInProduction(): bool
    {
        return $this->enabledInProduction ?? (bool) config('filament-api-explorer.page.enabled_in_production', false);
    }

    public function hasFullWidthLayout(): bool
    {
        return $this->fullWidth ?? (bool) config('filament-api-explorer.page.full_width', true);
    }

    public function allowsRequestSending(): bool
    {
        return $this->requestSending ?? (bool) config('filament-api-explorer.execution.enabled', true);
    }

    public function canAccess(): bool
    {
        return $this->authorizeUsing === null || ($this->authorizeUsing)() === true;
    }

    private function configString(string $key): ?string
    {
        $value = config("filament-api-explorer.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }
}
