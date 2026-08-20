<?php

declare(strict_types=1);

namespace DardanGashi\FilamentApiExplorer;

use DardanGashi\FilamentApiExplorer\Services\EndpointNavigator;
use DardanGashi\FilamentApiExplorer\Services\ExampleFactory;
use DardanGashi\FilamentApiExplorer\Services\RequestBlueprintFactory;
use DardanGashi\FilamentApiExplorer\Services\RequestExecutor;
use DardanGashi\FilamentApiExplorer\Services\ResponseSampleStore;
use DardanGashi\FilamentApiExplorer\Services\SchemaFieldFactory;
use DardanGashi\FilamentApiExplorer\Services\SnippetRenderer;
use DardanGashi\FilamentApiExplorer\Services\SpecParser;
use DardanGashi\FilamentApiExplorer\Services\SpecRepository;
use DardanGashi\FilamentApiExplorer\Snippets\CurlSnippet;
use DardanGashi\FilamentApiExplorer\Snippets\HttpSnippet;
use DardanGashi\FilamentApiExplorer\Snippets\JavaScriptSnippet;
use DardanGashi\FilamentApiExplorer\Snippets\PhpSnippet;
use DardanGashi\FilamentApiExplorer\Snippets\PythonSnippet;
use DardanGashi\FilamentApiExplorer\Sources\SpecSourceManager;
use DardanGashi\FilamentApiExplorer\Support\Documents;
use DardanGashi\FilamentApiExplorer\Support\ExecutionPolicy;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ApiExplorerServiceProvider extends PackageServiceProvider
{
	public static string $name = 'filament-api-explorer';

	public function configurePackage(Package $package): void
	{
		$package
			->name(self::$name)
			->hasConfigFile()
			->hasViews()
			->hasTranslations();
	}

	public function packageRegistered(): void
	{
		$this->app->singleton(
			SchemaFieldFactory::class,
			fn (): SchemaFieldFactory => new SchemaFieldFactory($this->intConfig('schema.max_depth', 6)),
		);

		$this->app->singleton(
			ExampleFactory::class,
			fn (): ExampleFactory => new ExampleFactory($this->intConfig('schema.max_depth', 6)),
		);

		$this->app->singleton(SpecParser::class);
		$this->app->singleton(EndpointNavigator::class);
		$this->app->singleton(RequestBlueprintFactory::class);

		$this->app->singleton(
			SpecSourceManager::class,
			fn (): SpecSourceManager => new SpecSourceManager($this->sources()),
		);

		// Scoped, so one request parses each document at most once.
		$this->app->scoped(SpecRepository::class, fn (): SpecRepository => new SpecRepository(
			sources: $this->app->make(SpecSourceManager::class),
			parser: $this->app->make(SpecParser::class),
			cache: $this->app->make(CacheFactory::class),
			cacheEnabled: (bool) config('filament-api-explorer.cache.enabled', false),
			cacheStore: $this->stringConfig('cache.store'),
			cacheTtl: $this->intConfig('cache.ttl', 300),
			context: (string) url('/'),
		));

		$this->app->singleton(SnippetRenderer::class, fn (): SnippetRenderer => new SnippetRenderer([
			new CurlSnippet,
			new HttpSnippet,
			new PhpSnippet,
			new JavaScriptSnippet,
			new PythonSnippet,
		]));

		$this->app->singleton(ExecutionPolicy::class, fn (): ExecutionPolicy => new ExecutionPolicy(
			enabled: (bool) config('filament-api-explorer.execution.enabled', true),
			allowedHosts: $this->allowedHosts(),
			allowedSchemes: $this->stringList('execution.allowed_schemes', ['https', 'http']),
		));

		$this->app->singleton(RequestExecutor::class, fn (): RequestExecutor => new RequestExecutor(
			http: $this->app->make(HttpFactory::class),
			policy: $this->app->make(ExecutionPolicy::class),
			timeout: $this->intConfig('execution.timeout', 10),
		));

		$this->app->singleton(ResponseSampleStore::class, fn (): ResponseSampleStore => new ResponseSampleStore(
			cache: $this->app->make(CacheFactory::class),
			enabled: (bool) config('filament-api-explorer.examples.capture', true),
			store: $this->stringConfig('examples.store'),
			ttl: $this->intConfig('examples.ttl', 86400),
			maxBytes: $this->intConfig('examples.max_bytes', 65536),
		));
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function sources(): array
	{
		$sources = [];

		foreach (Documents::toMap(config('filament-api-explorer.sources')) as $name => $source) {
			$sources[$name] = Documents::toMap($source);
		}

		return $sources;
	}

	/**
	 * Hosts default to the application's own, which is the only host an
	 * explorer needs in order to try its own API.
	 *
	 * @return list<string>
	 */
	private function allowedHosts(): array
	{
		$configured = $this->stringList('execution.allowed_hosts', []);

		if ($configured !== []) {
			return $configured;
		}

		$host = parse_url((string) config('app.url'), PHP_URL_HOST);

		return is_string($host) && $host !== '' ? [$host] : [];
	}

	/**
	 * @param  list<string>  $default
	 * @return list<string>
	 */
	private function stringList(string $key, array $default): array
	{
		$values = Documents::toList(config("filament-api-explorer.{$key}"));

		if ($values === []) {
			return $default;
		}

		return array_values(array_map(strval(...), array_filter($values, 'is_scalar')));
	}

	private function intConfig(string $key, int $default): int
	{
		$value = config("filament-api-explorer.{$key}", $default);

		return is_numeric($value) ? (int) $value : $default;
	}

	private function stringConfig(string $key): ?string
	{
		$value = config("filament-api-explorer.{$key}");

		return is_string($value) && $value !== '' ? $value : null;
	}
}
