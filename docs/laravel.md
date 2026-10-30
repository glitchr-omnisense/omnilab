---
title: Laravel
order: 16
---

# Laravel

There is no Laravel bridge for Omnilab: `omnibridge/laravel` has no provider for this family, and
this package ships none. Nothing is needed beyond what plain PHP does
([installation](installation.md#plain-php)): a service provider of the application builds the
`Registry` once, as a singleton, from the factories of the source packages it installed.

## A service provider of the application's own

```php
<?php // config/omnilab.php

return [
    'sources' => [
        'pubchem' => ['factory' => 'pubchem', 'options' => ['mailto' => env('OMNILAB_MAILTO')]],
        'chebi' => ['factory' => 'chebi', 'options' => ['mailto' => env('OMNILAB_MAILTO')]],
        'wikidata' => ['factory' => 'wikidata', 'options' => ['mailto' => env('OMNILAB_MAILTO'), 'language' => 'en']],
        'meteo' => ['factory' => 'open-meteo'],
        'air-quality' => ['factory' => 'open-meteo', 'options' => ['api' => 'air_quality']],
    ],
    'preferences' => [
        'default' => ['pubchem'],
    ],
];
```

```php
<?php // app/Providers/OmnilabServiceProvider.php

namespace App\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Omnilab\Chebi\ChebiSourceFactory;
use Omnilab\Collector;
use Omnilab\Merger;
use Omnilab\OpenMeteo\OpenMeteoSourceFactory;
use Omnilab\PubChem\PubChemSourceFactory;
use Omnilab\Registry;
use Omnilab\Wikidata\WikidataSourceFactory;
use Symfony\Component\HttpClient\HttpClient;

final class OmnilabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class, static function (): Registry {
            $http = HttpClient::create();

            return new Registry([
                new PubChemSourceFactory($http),
                new ChebiSourceFactory($http),
                new WikidataSourceFactory($http),
                new OpenMeteoSourceFactory($http),
            ], config('omnilab.sources'));
        });
        $this->app->singleton(Merger::class, static fn (): Merger => new Merger(config('omnilab.preferences', [])));
        $this->app->singleton(Collector::class, static fn (Application $app): Collector => new Collector($app->make(Registry::class), $app->make(Merger::class)));
    }
}
```

Register the provider where the application lists its providers (`bootstrap/providers.php` since
Laravel 11, `config/app.php`'s `providers` before). The `Registry`, the `Merger` and the
`Collector` are then injected by their class:

```php
use Omnilab\Collector;
use Omnilab\Registry;

final class CompoundController
{
    public function show(string $name, Collector $collector, Registry $registry)
    {
        $compound = $collector->collect('pubchem', $name) ?? abort(404);
        $meteo = $registry->get('meteo');
        // ...
    }
}
```

## What differs from the Symfony bundle

- The factories are listed by hand: the bundle registers every `omnilab/*` package installed
  ([Symfony](symfony.md#services)), a hand-written provider only those it names. A factory not
  listed makes `Registry::get()` throw `InvalidConfigException` ("No "x" factory for the "y"
  source; installed: ...").
- No source is injected by its name: there is no `SourceInterface $pubchem` alias. Inject the
  `Registry` and ask it (`$registry->get('pubchem')`), or bind a name of the application's own
  (`$this->app->bind('lab.pubchem', fn ($app) => $app->make(Registry::class)->get('pubchem'))`).
- The source names are kept as written: `air-quality` stays `air-quality`, as under the Symfony
  bundle ([its configuration tree](symfony.md#the-configuration-tree)).
- The HTTP client is Symfony's (`symfony/http-client`, which every source package requires),
  not Laravel's `Http` facade: the factories take a `Symfony\Contracts\HttpClient\HttpClientInterface`.

This provider is plain construction of the classes documented in this manual; it has not been
run in a Laravel application (the harness has none).
