<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use DardanGashi\FilamentApiExplorer\ApiExplorerPlugin;
use DardanGashi\FilamentApiExplorer\Data\ApiSpec;
use DardanGashi\FilamentApiExplorer\Data\Endpoint;
use DardanGashi\FilamentApiExplorer\Data\ExecutedRequest;
use DardanGashi\FilamentApiExplorer\Data\Parameter;
use DardanGashi\FilamentApiExplorer\Data\RequestBlueprint;
use DardanGashi\FilamentApiExplorer\Enums\ParameterLocation;
use DardanGashi\FilamentApiExplorer\Enums\SnippetLanguage;
use DardanGashi\FilamentApiExplorer\Exceptions\RequestNotAllowed;
use DardanGashi\FilamentApiExplorer\Exceptions\SpecUnavailable;
use DardanGashi\FilamentApiExplorer\Services\EndpointNavigator;
use DardanGashi\FilamentApiExplorer\Services\RequestBlueprintFactory;
use DardanGashi\FilamentApiExplorer\Services\RequestExecutor;
use DardanGashi\FilamentApiExplorer\Services\SnippetRenderer;
use DardanGashi\FilamentApiExplorer\Services\SpecRepository;
use DardanGashi\FilamentApiExplorer\Support\InputKey;

class ApiExplorerPage extends Page
{
    protected static ?string $configurationClass = PageConfiguration::class;

    protected static ?string $slug = 'api-explorer';

    protected string $view = 'filament-api-explorer::page';

    /**
     * The configured source being read, which is also the version picker's value.
     */
    #[Url(as: 'source', history: true)]
    public ?string $source = null;

    #[Url(as: 'endpoint', history: true)]
    public ?string $endpointKey = null;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'gaps', history: true)]
    public bool $onlyGaps = false;

    /**
     * Narrows the schema trees of the selected endpoint.
     */
    public string $fieldSearch = '';

    public string $snippetLanguage = SnippetLanguage::Curl->value;

    public string $server = '';

    /**
     * @var array<string, string>
     */
    public array $pathValues = [];

    /**
     * @var array<string, string>
     */
    public array $queryValues = [];

    /**
     * @var array<string, string>
     */
    public array $headerValues = [];

    public ?ExecutedRequest $result = null;

    private ?ApiSpec $memoizedSpec = null;

    private ?string $specError = null;

    public function mount(): void
    {
        $this->source ??= $this->plugin()?->getSource() ?? $this->specs()->defaultName();
        $this->server = $this->defaultServer();

        $this->syncSelection();
    }

    // -----------------------------------------------------------------
    // Filament page configuration
    // -----------------------------------------------------------------

    public static function canAccess(): bool
    {
        return ApiExplorerPlugin::current()?->canAccess() ?? true;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->plugin()?->getTitle()
            ?? $this->spec()->title;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->plugin()?->getDescription()
            ?? $this->spec()->description;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return ($this->plugin()?->hasFullWidthLayout() ?? true)
            ? Width::Full
            : parent::getMaxContentWidth();
    }

    public static function getNavigationLabel(): string
    {
        return ApiExplorerPlugin::current()?->getNavigationLabel()
            ?? __('filament-api-explorer::explorer.navigation.label');
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return ApiExplorerPlugin::current()?->getNavigationIcon()
            ?? 'heroicon-o-code-bracket-square';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return ApiExplorerPlugin::current()?->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return ApiExplorerPlugin::current()?->getNavigationSort();
    }

    public static function getNavigationBadge(): ?string
    {
        $spec = app(SpecRepository::class)->tryGet(ApiExplorerPlugin::current()?->getSource());

        if ($spec === null) {
            return null;
        }

        return match (ApiExplorerPlugin::current()?->getNavigationBadgeMode()) {
            'count' => (string) $spec->endpointCount(),
            'coverage' => $spec->coverage()->percentage().'%',
            'version' => $spec->versionLabel(),
            default => null,
        };
    }

    // -----------------------------------------------------------------
    // State
    // -----------------------------------------------------------------

    public function updatedSource(): void
    {
        $this->memoizedSpec = null;
        $this->specError = null;
        $this->endpointKey = null;
        $this->server = $this->defaultServer();

        $this->syncSelection();
    }

    public function updatedSearch(): void
    {
        $this->syncSelection();
    }

    public function filterGaps(bool $onlyGaps): void
    {
        $this->onlyGaps = $onlyGaps;

        $this->syncSelection();
    }

    public function selectEndpoint(string $key): void
    {
        if ($this->spec()->find($key) === null) {
            return;
        }

        $this->endpointKey = $key;
        $this->fieldSearch = '';

        $this->prefillRequest();
    }

    public function setSnippetLanguage(string $language): void
    {
        if (SnippetLanguage::tryFrom($language) !== null) {
            $this->snippetLanguage = $language;
        }
    }

    public function resetRequest(): void
    {
        $this->prefillRequest();
    }

    /**
     * Send the selected endpoint's request and keep the response in state.
     */
    public function send(): void
    {
        $endpoint = $this->currentEndpoint();

        if ($endpoint === null || ! $this->canSend()) {
            return;
        }

        try {
            $this->result = app(RequestExecutor::class)->send($this->liveBlueprint($endpoint));
        } catch (RequestNotAllowed $exception) {
            $this->result = null;

            Notification::make()
                ->danger()
                ->title(__('filament-api-explorer::explorer.notifications.refused'))
                ->body($exception->getMessage())
                ->send();

            return;
        }

        if ($this->result->hasFailed()) {
            Notification::make()
                ->warning()
                ->title(__('filament-api-explorer::explorer.notifications.failed'))
                ->body($this->result->error)
                ->send();
        }
    }

    // -----------------------------------------------------------------
    // Derived data
    // -----------------------------------------------------------------

    public function spec(): ApiSpec
    {
        if ($this->memoizedSpec !== null) {
            return $this->memoizedSpec;
        }

        try {
            return $this->memoizedSpec = $this->specs()->get($this->source);
        } catch (SpecUnavailable $exception) {
            $this->specError = $exception->getMessage();

            return $this->memoizedSpec = ApiSpec::empty($this->source ?? 'default');
        }
    }

    public function currentEndpoint(): ?Endpoint
    {
        return $this->spec()->find($this->endpointKey);
    }

    public function canSend(): bool
    {
        return ($this->plugin()?->allowsRequestSending() ?? true)
            && ($this->currentEndpoint()?->isExecutable() ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $spec = $this->spec();
        $endpoint = $this->currentEndpoint();

        return [
            'spec' => $spec,
            'coverage' => $spec->coverage(),
            'groups' => app(EndpointNavigator::class)->groups($spec, $this->search, $this->onlyGaps),
            'endpoint' => $endpoint,
            'sourceNames' => $this->specs()->names(),
            'serverOptions' => $this->serverOptions(),
            'parameterSections' => $endpoint === null ? [] : $this->parameterSections($endpoint),
            'senderSections' => $endpoint === null ? [] : $this->senderSections($endpoint),
            'snippet' => $endpoint === null ? '' : $this->snippet($endpoint),
            'snippetLanguages' => app(SnippetRenderer::class)->languages(),
            'canSend' => $this->canSend(),
            'sendingEnabled' => $this->plugin()?->allowsRequestSending() ?? true,
            'specError' => $this->specError,
        ];
    }

    /**
     * The sample for the current snippet tab. Documented values fill in for the
     * required headers the user has not typed, so the sample stays complete
     * while the live request only ever uses real input.
     */
    private function snippet(Endpoint $endpoint): string
    {
        return app(SnippetRenderer::class)->render(
            SnippetLanguage::tryFrom($this->snippetLanguage) ?? SnippetLanguage::default(),
            $this->documentationBlueprint($endpoint),
        );
    }

    private function documentationBlueprint(Endpoint $endpoint): RequestBlueprint
    {
        $headers = $this->valuesFor($endpoint, ParameterLocation::Header);

        foreach ($endpoint->parametersIn(ParameterLocation::Header) as $parameter) {
            if (! $parameter->required || ($headers[$parameter->name] ?? '') !== '') {
                continue;
            }

            $headers[$parameter->name] = $parameter->suggestedValue() ?? '';
        }

        return $this->blueprints()->make(
            endpoint: $endpoint,
            server: $this->server,
            pathParameters: $this->valuesFor($endpoint, ParameterLocation::Path),
            queryParameters: $this->valuesFor($endpoint, ParameterLocation::Query),
            headers: $headers,
        );
    }

    private function liveBlueprint(Endpoint $endpoint): RequestBlueprint
    {
        return $this->blueprints()->make(
            endpoint: $endpoint,
            server: $this->server,
            pathParameters: $this->valuesFor($endpoint, ParameterLocation::Path),
            queryParameters: $this->valuesFor($endpoint, ParameterLocation::Query),
            headers: $this->valuesFor($endpoint, ParameterLocation::Header),
        );
    }

    /**
     * The typed values of one parameter location, keyed by the real parameter
     * name again. Input state is keyed by {@see InputKey} because a name like
     * `filter[code]` cannot be bound to directly.
     *
     * @return array<string, string>
     */
    private function valuesFor(Endpoint $endpoint, ParameterLocation $in): array
    {
        $state = match ($in) {
            ParameterLocation::Path => $this->pathValues,
            ParameterLocation::Query => $this->queryValues,
            ParameterLocation::Header => $this->headerValues,
            ParameterLocation::Cookie => [],
        };

        $values = [];

        foreach ($endpoint->parametersIn($in) as $parameter) {
            $values[$parameter->name] = trim($state[InputKey::for($parameter->name)] ?? '');
        }

        return $values;
    }

    /**
     * The documentation sections, one per parameter location that is used.
     *
     * @return list<array{label: string, parameters: list<Parameter>}>
     */
    private function parameterSections(Endpoint $endpoint): array
    {
        $sections = [];

        foreach (ParameterLocation::cases() as $location) {
            $parameters = $endpoint->parametersIn($location);

            if ($parameters !== []) {
                $sections[] = [
                    'label' => (string) __($location->translationKey()),
                    'parameters' => $parameters,
                ];
            }
        }

        return $sections;
    }

    /**
     * The inputs of the request panel. Cookies are documented but not sent, so
     * they get no input.
     *
     * @return list<array{label: string, fields: list<array{bind: string, name: string, placeholder: string, required: bool}>}>
     */
    private function senderSections(Endpoint $endpoint): array
    {
        $sections = [];

        foreach ([
            [ParameterLocation::Path, 'pathValues'],
            [ParameterLocation::Query, 'queryValues'],
            [ParameterLocation::Header, 'headerValues'],
        ] as [$location, $property]) {
            $fields = [];

            foreach ($endpoint->parametersIn($location) as $parameter) {
                $fields[] = [
                    'bind' => $property.'.'.InputKey::for($parameter->name),
                    'name' => $parameter->name,
                    'placeholder' => $parameter->suggestedValue() ?? '',
                    'required' => $parameter->required,
                ];
            }

            if ($fields !== []) {
                $sections[] = [
                    'label' => (string) __($location->translationKey()),
                    'fields' => $fields,
                ];
            }
        }

        return $sections;
    }

    /**
     * Keep the selection in step with the filters, so the detail pane always
     * shows something the sidebar still lists.
     */
    private function syncSelection(): void
    {
        $resolved = app(EndpointNavigator::class)->resolveSelected(
            spec: $this->spec(),
            key: $this->endpointKey,
            term: $this->search,
            onlyGaps: $this->onlyGaps,
        );

        if ($resolved?->key === $this->endpointKey) {
            return;
        }

        $this->endpointKey = $resolved?->key;

        $this->prefillRequest();
    }

    /**
     * Query values start from the documented defaults; header values start
     * empty, because a documented header example is a placeholder and not a
     * credential anybody should send by accident.
     */
    private function prefillRequest(): void
    {
        $endpoint = $this->currentEndpoint();
        $this->result = null;

        if ($endpoint === null) {
            $this->pathValues = $this->queryValues = $this->headerValues = [];

            return;
        }

        $this->pathValues = $this->suggestedState($endpoint, ParameterLocation::Path);
        $this->queryValues = $this->suggestedState($endpoint, ParameterLocation::Query);
        $this->headerValues = array_map(fn (): string => '', $this->suggestedState($endpoint, ParameterLocation::Header));
    }

    /**
     * @return array<string, string>
     */
    private function suggestedState(Endpoint $endpoint, ParameterLocation $in): array
    {
        $state = [];

        foreach ($this->blueprints()->suggestions($endpoint, $in) as $name => $value) {
            $state[InputKey::for($name)] = $value;
        }

        return $state;
    }

    /**
     * @return list<string>
     */
    private function serverOptions(): array
    {
        $servers = $this->spec()->servers;
        $applicationUrl = (string) config('app.url');

        if ($servers === []) {
            return [$applicationUrl];
        }

        return $servers;
    }

    private function defaultServer(): string
    {
        return $this->serverOptions()[0] ?? (string) config('app.url');
    }

    private function plugin(): ?ApiExplorerPlugin
    {
        return ApiExplorerPlugin::current();
    }

    private function specs(): SpecRepository
    {
        return app(SpecRepository::class);
    }

    private function blueprints(): RequestBlueprintFactory
    {
        return app(RequestBlueprintFactory::class);
    }
}
