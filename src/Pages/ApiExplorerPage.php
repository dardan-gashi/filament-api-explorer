<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer\Pages;

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
use DardanGashi\FilamentApiExplorer\Services\ResponseSampleStore;
use DardanGashi\FilamentApiExplorer\Services\SnippetRenderer;
use DardanGashi\FilamentApiExplorer\Services\SpecRepository;
use DardanGashi\FilamentApiExplorer\Support\GroupLabel;
use DardanGashi\FilamentApiExplorer\Support\InputKey;
use DardanGashi\FilamentApiExplorer\Support\PathParts;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

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

	/**
	 * The line under the title, and only where a panel asks for one: the
	 * document's own description is prose of any length, and on a page opened
	 * daily it is in the way rather than read.
	 */
	public function getSubheading(): string|Htmlable|null
	{
		return $this->plugin()?->getDescription();
	}

	/**
	 * The width the panel gives its pages, unless a panel asks for the window: a
	 * plugin that overrides the layout convention of the application it is
	 * installed into has decided something that was not its to decide.
	 */
	public function getMaxContentWidth(): Width|string|null
	{
		return ($this->plugin()?->hasFullWidthLayout() ?? false)
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
		$endpoint = $this->spec()->find($key);

		if ($endpoint === null) {
			return;
		}

		$this->endpointKey = $key;
		$this->fieldSearch = '';

		$this->prefillRequest();
	}

	public function clearSearch(): void
	{
		$this->search = '';

		$this->syncSelection();
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

		if ($endpoint === null || !$this->canSend()) {
			return;
		}

		try {
			$result = app(RequestExecutor::class)->send($this->liveBlueprint($endpoint));
		} catch (RequestNotAllowed $exception) {
			$this->result = null;

			Notification::make()
				->danger()
				->title(__('filament-api-explorer::explorer.notifications.refused'))
				->body($exception->reason())
				->send();

			return;
		}

		$this->result = $result;

		if ($result->hasFailed()) {
			Notification::make()
				->warning()
				->title(__('filament-api-explorer::explorer.notifications.failed'))
				->body($result->error)
				->send();

			return;
		}

		$this->samples()->remember($this->source ?? '', $endpoint->key, $result);
	}

	/**
	 * Drop a recorded response, which puts the documented or synthesised example
	 * back in its place.
	 */
	public function discardSample(string $status): void
	{
		$endpoint = $this->currentEndpoint();

		if ($endpoint !== null) {
			$this->samples()->forget($this->source ?? '', $endpoint->key, $status);
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
			'resources' => $resources = $this->resources($spec),
			// The resource the palette opens on: the one the reader is looking at.
			'openResource' => $endpoint?->group,
			'paletteKey' => $this->paletteKey($endpoint),
			'siblings' => $endpoint === null ? [] : $this->siblings($resources, $endpoint),
			'resourceCaption' => $endpoint === null ? '' : $this->resourceCaption($resources, $endpoint),
			'endpoint' => $endpoint,
			'sourceNames' => $this->specs()->names(),
			'serverOptions' => $this->serverOptions(),
			'parameterSections' => $endpoint === null ? [] : $this->parameterSections($endpoint),
			'senderSections' => $endpoint === null ? [] : $this->senderSections($endpoint),
			'snippet' => $endpoint === null ? '' : $this->snippet($endpoint),
			'snippetLanguages' => app(SnippetRenderer::class)->languages(),
			'snippetSyntax' => $this->snippetSyntax(),
			'exampleSections' => $endpoint === null ? [] : $this->exampleSections($endpoint),
			'emptyRequiredHeaders' => $endpoint === null ? [] : $this->emptyRequiredHeaders($endpoint),
			'captureEnabled' => $this->samples()->isEnabled(),
			'canSend' => $this->canSend(),
			'sendingEnabled' => $this->plugin()?->allowsRequestSending() ?? true,
			'specError' => $this->specError,
		];
	}

	/**
	 * The identity of the palette's copy of the navigation.
	 *
	 * The palette reads the whole structure once, when Alpine initialises it, and
	 * an attribute that changes afterwards is never read again — so a filtered
	 * structure would never reach a palette that is already on the page. A changed
	 * key is how Livewire is told that this is a different element, which makes it
	 * a new one, initialised from what the server sent this time.
	 */
	private function paletteKey(?Endpoint $endpoint): string
	{
		return substr(md5(implode('|', [
			$this->search,
			(int) $this->onlyGaps,
			// `??` covers the null itself, which makes a nullsafe arrow redundant.
			$endpoint->group ?? '',
		])), 0, 8);
	}

	/**
	 * The sample for the current snippet tab. Documented values fill in for the
	 * required headers the user has not typed, so the sample stays complete
	 * while the live request only ever uses real input.
	 */
	private function snippet(Endpoint $endpoint): string
	{
		return app(SnippetRenderer::class)->render(
			$this->snippetSyntax(),
			$this->documentationBlueprint($endpoint),
		);
	}

	/**
	 * The language of the current snippet tab, which decides both what is
	 * rendered and how it is marked up. The property behind it is a string,
	 * because it comes from the query string.
	 */
	private function snippetSyntax(): SnippetLanguage
	{
		return SnippetLanguage::tryFrom($this->snippetLanguage) ?? SnippetLanguage::default();
	}

	private function documentationBlueprint(Endpoint $endpoint): RequestBlueprint
	{
		$headers = $this->valuesFor($endpoint, ParameterLocation::Header);

		foreach ($endpoint->parametersIn(ParameterLocation::Header) as $parameter) {
			if (!$parameter->required || ($headers[$parameter->name] ?? '') !== '') {
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
	 * Required headers the user has left empty.
	 *
	 * An empty header is simply not sent, and the API answers a request without
	 * credentials the only way it can: 401. That is a correct answer to a
	 * question nobody meant to ask, so the result says which field was empty
	 * rather than leaving the reader to guess.
	 *
	 * @return list<string>
	 */
	private function emptyRequiredHeaders(Endpoint $endpoint): array
	{
		$typed = $this->valuesFor($endpoint, ParameterLocation::Header);
		$names = [];

		foreach ($endpoint->parametersIn(ParameterLocation::Header) as $parameter) {
			if ($parameter->required && ($typed[$parameter->name] ?? '') === '') {
				$names[] = $parameter->name;
			}
		}

		return $names;
	}

	/**
	 * The payloads shown beside an endpoint, best first.
	 *
	 * A response the explorer has actually seen beats one the document declares,
	 * and both beat a skeleton built from the schema — which says `"status":
	 * "string"` where the API says `"status": "paid"`. That skeleton is worth
	 * having as a shape reference and worth nothing as an example, so it arrives
	 * collapsed.
	 *
	 * @return list<array{key: string, status: string|null, color: string, origin: string, body: string, captured: bool, collapsed: bool, headers: list<Parameter>}>
	 */
	private function exampleSections(Endpoint $endpoint): array
	{
		$samples = $this->samples()->findMany(
			$this->source ?? '',
			$endpoint->key,
			$endpoint->responseStatuses(),
		);

		$sections = [];
		$body = $endpoint->requestBody;

		if ($body !== null && $body->example !== null) {
			$sections[] = [
				'key' => 'request',
				'status' => null,
				'color' => 'gray',
				'origin' => (string) __('filament-api-explorer::explorer.examples.request'),
				'body' => $body->example,
				'captured' => false,
				'collapsed' => $body->exampleSynthesised,
				'headers' => [],
			];
		}

		foreach ($endpoint->responses as $response) {
			$sample = $samples[$response->status] ?? null;

			if ($sample !== null) {
				$sections[] = [
					'key' => $response->status,
					'status' => $response->status,
					'color' => $response->color(),
					'origin' => (string) __('filament-api-explorer::explorer.examples.captured', [
						'time' => $sample->capturedAt->diffForHumans(),
					]),
					'body' => $sample->body,
					'captured' => true,
					'collapsed' => false,
					'headers' => $response->headers,
				];

				continue;
			}

			if ($response->example === null) {
				continue;
			}

			$sections[] = [
				'key' => $response->status,
				'status' => $response->status,
				'color' => $response->color(),
				'origin' => (string) __($response->exampleSynthesised
					? 'filament-api-explorer::explorer.examples.synthesised'
					: 'filament-api-explorer::explorer.examples.documented'),
				'body' => $response->example,
				'captured' => false,
				'collapsed' => $response->exampleSynthesised,
				'headers' => $response->headers,
			];
		}

		return $sections;
	}

	/**
	 * The whole navigation, as one structure.
	 *
	 * It travels to the browser once and is both browsed and searched there: a
	 * document of this size is a few dozen kilobytes, and a round trip per keystroke
	 * is felt on every one of them. `haystack` is what a term is matched against —
	 * method, path, summary and resource in one lowercased string, so `ord sub`
	 * finds `GET /orders/{order}/subscriptions`.
	 *
	 * Filters stay on the server, because they belong to the address: what `?q=` and
	 * `?gaps=` leave out never reaches the palette.
	 *
	 * @return list<array{group: string, label: string, prefix: string, endpoints: list<array{key: string, method: string, color: string, path: string, label: string, summary: string, deprecated: bool, documented: bool, haystack: string}>}>
	 */
	private function resources(ApiSpec $spec): array
	{
		$groups = app(EndpointNavigator::class)->groups($spec, $this->search, $this->onlyGaps);
		$documentPrefix = $spec->commonPathPrefix();
		$resources = [];

		foreach ($groups as $group => $endpoints) {
			$prefix = PathParts::sharedPrefix(array_map(
				fn (Endpoint $endpoint): string => $endpoint->path,
				$endpoints,
			)) ?: $documentPrefix;

			$resources[] = [
				'group' => $group,
				'label' => GroupLabel::for($group),
				'prefix' => (string) Str::after($prefix, $documentPrefix),
				'endpoints' => array_map(
					fn (Endpoint $endpoint): array => $this->paletteEndpoint($endpoint, $prefix),
					$endpoints,
				),
			];
		}

		return $resources;
	}

	/**
	 * @return array{key: string, method: string, color: string, path: string, label: string, summary: string, deprecated: bool, documented: bool, haystack: string}
	 */
	private function paletteEndpoint(Endpoint $endpoint, string $prefix): array
	{
		$summary = $endpoint->summary ?? '';

		return [
			'key' => $endpoint->key,
			'method' => $endpoint->method->label(),
			'color' => $endpoint->method->color(),
			'path' => $endpoint->path,
			// Inside its resource an endpoint is only what the resource is not.
			'label' => PathParts::within($endpoint->path, $prefix),
			'summary' => $summary,
			'deprecated' => $endpoint->deprecated,
			'documented' => $endpoint->isDocumented(),
			'haystack' => mb_strtolower(implode(' ', [
				$endpoint->method->label(),
				$endpoint->path,
				$summary,
				$endpoint->group,
			])),
		];
	}

	/**
	 * What the resource of an endpoint is called in the breadcrumb: the path its
	 * endpoints share, or the name of the tag where they share none.
	 *
	 * @param  list<array{group: string, label: string, prefix: string, endpoints: list<array<string, mixed>>}>  $resources
	 */
	private function resourceCaption(array $resources, Endpoint $endpoint): string
	{
		foreach ($resources as $resource) {
			if ($resource['group'] === $endpoint->group) {
				return $resource['prefix'] !== '' ? $resource['prefix'] : $resource['label'];
			}
		}

		return '';
	}

	/**
	 * The endpoints beside the one on screen, for the breadcrumb of its header.
	 *
	 * That a path also answers PATCH, or that the resource has a sub-resource, is
	 * not something a reader went looking for and exactly what they need to know.
	 *
	 * @param  list<array{group: string, label: string, prefix: string, endpoints: list<array<string, mixed>>}>  $resources
	 * @return list<array<string, mixed>>
	 */
	private function siblings(array $resources, Endpoint $endpoint): array
	{
		foreach ($resources as $resource) {
			if ($resource['group'] === $endpoint->group) {
				return $resource['endpoints'];
			}
		}

		return [];
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
	 * @return list<array{label: string, fields: list<array{bind: string, name: string, placeholder: string, required: bool, scheme: string|null}>}>
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
					// A path segment with nothing to suggest shows its own
					// placeholder, so an empty input reads as the hole it is.
					'placeholder' => $parameter->credentialPlaceholder()
						?? ($location === ParameterLocation::Path ? '{'.$parameter->name.'}' : ''),
					'required' => $parameter->required,
					// Stated beside the input, so the field asks for the credential
					// and not for a whole header value.
					'scheme' => $parameter->headerScheme(),
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
	 *
	 * A header already filled in is carried over to the next endpoint that asks
	 * for the same one: a token is typed to try an API, not a single endpoint,
	 * and retyping it per endpoint is the kind of friction that ends in a token
	 * pasted somewhere it can be found again. It lives in this component and
	 * nowhere else — a reload asks for it again.
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
		$typed = $this->headerValues;
		$this->headerValues = [];

		foreach (array_keys($this->suggestedState($endpoint, ParameterLocation::Header)) as $key) {
			$this->headerValues[$key] = $typed[$key] ?? '';
		}
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

	private function samples(): ResponseSampleStore
	{
		return app(ResponseSampleStore::class);
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
