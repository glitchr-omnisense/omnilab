---
title: Testing
order: 60
---

# Testing

Omnilab has no conformance suite: the core ships no `*ContractTests` class for a source to
extend, and no fake gateway. A source package's tests are its own (recorded answers on a
`MockHttpClient`, below); an application tests its code with the same two tools: a
`MockHttpClient` under a real source, or a source of its own that answers fixed entities.

The core's `Tests\StubSource` and `Tests\StubSourceFactory` are test classes of this package,
autoloaded in development only (`autoload-dev`): an application that installs
`omnisense/omnilab` does not get them.

## A real source on recorded answers

Every factory takes the HTTP client to call with. Give it a `MockHttpClient` that answers each
path with a file recorded from the service: the source maps them exactly as it maps the live
answers, errors included (404 is null, 503 an `UnavailableException`).

```php
<?php

use Omnilab\Exception\UnavailableException;
use Omnilab\Model\Scheme;
use Omnilab\PubChem\PubChemSourceFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// Recorded answers, by the path the source asks (PubChem's PUG REST).
$fixtures = __DIR__.'/Fixtures/';
$http = new MockHttpClient(static function (string $method, string $url) use ($fixtures): MockResponse {
    $path = (string) parse_url($url, PHP_URL_PATH);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return match (true) {
        str_ends_with($path, '/compound/name/cids/JSON') && 'caffeine' === ($query['name'] ?? null) => new MockResponse(file_get_contents($fixtures.'name-caffeine.json')),
        str_contains($path, '/compound/cid/2519/property/') => new MockResponse(file_get_contents($fixtures.'properties-2519.json')),
        str_ends_with($path, '/compound/cid/2519/synonyms/JSON') => new MockResponse(file_get_contents($fixtures.'synonyms-2519.json')),
        str_ends_with($path, '/compound/cid/2519/description/JSON') => new MockResponse(file_get_contents($fixtures.'description-2519.json')),
        default => new MockResponse('', ['http_code' => 404]),
    };
});
$pubchem = (new PubChemSourceFactory($http))->create(['throttle' => 0]);

$caffeine = $pubchem->entity('caffeine');
echo $caffeine->label, ' ', $caffeine->id, ' ', $caffeine->property('molecular_weight'), ' CAS ', $caffeine->identifier(Scheme::CAS), "\n";
var_dump($pubchem->entity('no-such-compound'));                 // a 404: null

$down = (new PubChemSourceFactory(new MockHttpClient(new MockResponse('', ['http_code' => 503]))))->create(['throttle' => 0]);
try {
    $down->entity('caffeine');
} catch (UnavailableException $e) {
    echo get_class($e), ': ', $e->getMessage(), ' (', $e->status, ")\n";
}
```

```
Caffeine pubchem.cid:2519 194.19 g/mol CAS 58-08-2
NULL
Omnilab\Exception\UnavailableException: [pubchem] the service answered 503 (503)
```

(run on 2026-10-27 in the harness image, with no network, on the fixtures of
`omnilab/pubchem`'s `Tests/Fixtures/`). Give `throttle: 0`: a source that spaces its calls
(PubChem's 0.2 s) would otherwise sleep in a test. The paths and queries each source asks are on
its page ("Which API", "Calls") and in its own tests, which are the most complete list.

## A source of the application's own

A test of code that takes a `Registry` or a `Collector` needs no HTTP at all: a source that
extends `HttpSource` and overrides what it answers, its factory, and a `Registry` built on them.
`HttpSource` already refuses every operation not overridden with `NotSupportedException`, and
`identify()` reads identifiers as every source does.

```php
<?php

use Omnilab\Collector;
use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Registry;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\SourceFactoryInterface;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\MockHttpClient;

/** A source that knows the entities it is given, by every identifier of the schemes it reads. */
final class FixedSource extends HttpSource
{
    /** @param list<Entity> $entities @param list<Scheme> $schemes */
    public function __construct(private readonly string $name, private readonly array $entities, private readonly array $schemes)
    {
        parent::__construct(new MockHttpClient(), 'https://fixed.invalid/'); // never called
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::CROSS_REFERENCES];
    }

    public function entity(Identifier|string $id): ?Entity
    {
        $id = self::identify($id, ...$this->schemes) ?? throw NotSupportedException::identifier($this->name, (string) $id);
        foreach ($this->entities as $entity) {
            if ($entity->identifiers()->has($id)) {
                return $entity;
            }
        }

        return null;
    }
}

final class FixedSourceFactory implements SourceFactoryInterface
{
    public function getName(): string
    {
        return 'fixed';
    }

    public function create(array $options = []): SourceInterface
    {
        return new FixedSource($options['name'], $options['entities'] ?? [], $options['schemes'] ?? []);
    }
}

$cid = Identifier::cid(2519);
$chebi = Identifier::chebi(27732);
$registry = new Registry([new FixedSourceFactory()], [
    'pubchem' => ['factory' => 'fixed', 'options' => ['name' => 'pubchem', 'schemes' => [Scheme::PUBCHEM_CID], 'entities' => [
        new Entity($cid, 'Caffeine', Domain::CHEMISTRY, properties: [new Property('molecular_weight', 'Molecular weight', new Quantity(194.19, 'g/mol'), 'pubchem')], identifiers: Identifiers::of($chebi), source: 'pubchem'),
    ]]],
    'chebi' => ['factory' => 'fixed', 'options' => ['name' => 'chebi', 'schemes' => [Scheme::CHEBI], 'entities' => [
        new Entity($chebi, 'caffeine', Domain::CHEMISTRY, properties: [new Property('molecular_weight', 'Molecular weight', new Quantity(194.194, 'g/mol'), 'chebi')], source: 'chebi'),
    ]]],
]);

$caffeine = (new Collector($registry))->collect('pubchem', '2519');
echo $caffeine->label, ' from ', implode(', ', $caffeine->sources), ': ', implode(', ', array_map(static fn (Property $p) => $p.' ['.$p->source.']', $caffeine->properties('molecular_weight'))), "\n";
```

```
Caffeine from pubchem, chebi: 194.19 g/mol [pubchem], 194.194 g/mol [chebi]
```

(run on 2026-10-27 in the harness image, with no network). The `Collector` offered the PubChem
entity's ChEBI id to the `chebi` source, which read it, and the `Merger` kept both weights, the
first source's first.

## In a Symfony application

The bundle builds every factory on the application's `http_client` service
([Symfony](symfony.md#services)), so Symfony's own mock replaces the network for the whole
test environment:

```yaml
# config/packages/test/framework.yaml
framework:
    http_client:
        mock_response_factory: App\Tests\RecordedAnswers   # a callable: (string $method, string $url, array $options) => ResponseInterface
```

The `Registry` service is public: `static::getContainer()->get(Registry::class)` in a
`KernelTestCase` gives the configured sources. An application's own factory (a
`SourceFactoryInterface` under `tests/`, registered as a service in the test environment) is
autoconfigured, so a fixed source can be configured in `config/packages/test/omnilab.yaml` by its
factory's name.

## The packages' own tests

Run from the workspace that holds every checkout (`~/Sites/omnilab`: its `phpunit.xml.dist` has
one test suite per package), or in the harness (`docker compose run --rm omnilab test`):

```sh
docker run --rm --network none -v "$PWD":/w:ro -w /w --entrypoint "" omniscient/omnilab-harness \
    php vendor/bin/phpunit --do-not-cache-result
```

```
OK (781 tests, 6195 assertions)
```

(2026-10-27, `~/Sites/omnilab` mounted read-only, no network: every test reads recorded answers.)
The core's 245 tests:

| Test class | Tests | Checks |
|---|---|---|
| `IdentifierTest` | 187 | 7 test methods, most on data providers: every scheme's normalisation, the shapes `parse()` reads and those it leaves to a scheme, the pages and identifiers.org links read, `url()`, `display()` |
| `HttpSourceErrorsTest` | 25 | each service's error body read into the exception's message (one case per form [Sources](sources.md#errors) lists), a maintenance page answered for JSON is unavailable, a long text cut |
| `ModelsTest` | 13 | `Quantity` and `Property` as text (the very small and very large keep their digits), places, a series, a product and its assets, a job, the queries' refusals, a series at a station, an occurrence query and the filters a source refuses, `postForJson()` (204 is nothing) and `getForJson()` |
| `RegistryTest` | 6 | the sources by name, built once; a required option; the `Collector` one round at a time, into the sources named, null when the first source does not know it, a search across sources merged |
| `BareTest` | 5 | in a process of its own: the registry built by hand, each shape and the `Collector` on recorded answers with no class of a framework loaded; the bundle does load the framework |
| `MergerTest` | 5 | entities that share an identifier are one, each property with its source; two CIDs stay two whatever they share; another identifier of its own scheme is still itself; related things join nothing; combining one gives its source |
| `StacTest` | 3 | a query as an item search (`parameters()`), a page of items (`page()`, `item()`), a collection as a dataset (`collection()`) |
| `Bridge\OmnilabBundleTest` | 1 | a kernel booted with the bundle ([Symfony](symfony.md#what-is-tested)) |

Each source package's count is on [Verified, and not](verified.md).

A source package's tests follow one pattern: a `MockHttpClient` whose callback matches the path
and query asked to a file of `Tests/Fixtures/` (each recorded from the service on a stated day,
some cut down, the README of the fixtures or the test's docblock says how), the calls logged and
asserted, and the errors checked on built responses (`MockResponse('', ['http_code' => 503])`).
