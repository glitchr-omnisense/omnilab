---
title: Writing a provider
order: 80
---

# Writing a provider

A provider is a package `omnilab/<slug>` (repository `github.com/glitchr-omnisense/omnilab-<slug>`,
namespace `Omnilab\<Name>\`) holding a factory and a source. Its 56 predecessors follow one
layout; [Sources](sources.md#writing-a-source) states the rules in short, this page step by step.

## The package

```
omnilab-<slug>/
    <Name>Source.php          the source: extends Omnilab\Source\HttpSource
    <Name>SourceFactory.php   its factory: extends Omnilab\Source\SourceFactory
    Tests/<Name>SourceTest.php
    Tests/Fixtures/           the service's answers, recorded on a stated day
    docs/index.md             its page of the manual
    README.md                 what it reads, its terms of use, its Responsibility section
    LICENSE                   MIT
    composer.json
```

```json
{
    "name": "omnilab/<slug>",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.2",
        "omnisense/omnilab": "^1.0@dev",
        "symfony/http-client": "^6.4|^7.0|^8.0"
    },
    "require-dev": { "phpunit/phpunit": "^11.0" },
    "autoload": { "psr-4": { "Omnilab\\<Name>\\": "" }, "exclude-from-classmap": ["/Tests/"] },
    "autoload-dev": { "psr-4": { "Omnilab\\<Name>\\Tests\\": "Tests/" } }
}
```

A package that calls `mb_*` functions requires `ext-mbstring` or `symfony/polyfill-mbstring`
(the thirteen that do today do not: [a known defect](installation.md#requirements)).

## The factory

`populate()` names the factory, lists the options it requires and sets the defaults of the
others; `build()` makes the source from the filled `Config`. `SourceFactory` adds the defaults of
`mailto`, `user_agent` and `throttle` (0.0) after `populate()`, checks the required options, and
builds the User-Agent (`self::userAgent($c)`).

```php
namespace Omnilab\Oqmd;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

final class OqmdSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'oqmd',
            'omnilab.required_options' => [],
            'throttle' => 0.0,
            'base_uri' => OqmdSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new OqmdSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('base_uri', OqmdSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}
```

- The name (`omnilab.factory_name`) is what `factory:` names: the slug, lower case.
- The throttle's default is the service's published rate (PubChem's five calls a second: 0.2),
  0 when none is published.
- Each option the source reads has a default here, and a line of the factory's docblock that
  says what it does: the provider's page copies them into its options table.
- A key the service needs for every call may be a required option (`InvalidConfigException` at
  `create()`); the packages written so far keep it optional and refuse the call instead, so that a
  registry configured without the key still builds.

## The source

```php
final class OqmdSource extends HttpSource
{
    public const BASE_URI = 'https://oqmd.org/oqmdapi/';

    public function getName(): string
    {
        return 'oqmd';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::SEARCH];
    }

    public function entity(Identifier|string $id): ?Entity
    {
        $identifier = self::identify($id, Scheme::OQMD) ?? throw NotSupportedException::identifier('oqmd', (string) $id);
        $data = $this->getJson('formationenergy', ['filter' => 'entry_id='.$identifier->value, 'limit' => 1, 'format' => 'json'])['data'] ?? [];
        // ... the row read into an Entity, or null
    }
}
```

What `HttpSource` gives:

| Member | |
|---|---|
| `entity()`, `search()`, `series()`, `products()`, `occurrences()`, `datasets()`, `open()` | throw `NotSupportedException` until overridden: override only what the service answers, and list it in `capabilities()` |
| `getJson($path, $query, $headers)` | a GET read as JSON; null on 404, 410 and 204 |
| `getJsonWithHeaders(...)` | the same with the answer's headers by lower-case name, for a service that pages or counts in them |
| `postForJson($path, $body, $query, $headers)` | a JSON body POSTed (GraphQL, a search API, a batch lookup), JSON read |
| `getForJson(...)` | a JSON body on a GET (the Minor Planet Center's APIs) |
| `get($path, $query, $headers)` | a GET read as text (a CSV, a flat file, a VOTable) |
| `station(SeriesQuery $query, Scheme ...$schemes)` | the station a series query names, when of a scheme the source reads; null for a place; `NotSupportedException` for another station |
| `filters(OccurrenceQuery $query, string ...$known)` | the occurrence filters, when the source reads every key; `NotSupportedException` for another key |
| `identify(Identifier\|string $id, Scheme ...$schemes)` | an identifier of one of the schemes, from an `Identifier` or a string (`scheme:value`, a page, a value its shape or the first scheme reads); null for anything else - a name, for a source that reads names |
| `text(?string)` | tags stripped, entities decoded, white space collapsed; null when empty |
| `time(?string)` | an ISO 8601 time; null when absent or unreadable |
| `$http`, `$baseUri`, `$headers`, `$throttle` | the constructor's, for a call `HttpSource` does not make (a form POST, a stream) |

A path starting with `http` is called as it is; any other is joined to the base URI. A query
value that is a list repeats its key; null and `''` are left out; a boolean is `true` / `false`.
Every call waits the throttle, and its answer is mapped as [Errors](errors.md#where-the-core-throws-them)
says. A call a source makes by itself (`$this->http->request()`) maps its own errors the same way.

## The rules a source keeps

- **Refuse before any call.** An identifier of a scheme it does not read, an operation, a filter,
  a box, a station, a step it cannot serve: `NotSupportedException`, before calling anything. The
  `Collector` offers every identifier to every source and relies on it.
- **Unknown is null**, an empty page, a series without points; never an exception. Down is an
  `UnavailableException`, never null: a caller caches null.
- **Identifiers name the thing itself**; other things it is tied to go in `related`, under a
  relation that says what (`organism`, `parent`, `target`, `taxon`...), reusing a relation's name
  before making one ([decisions](decisions.md)).
- **Property keys are shared**: reuse a key another source gives for the same meaning
  (`molecular_weight`, `mass`, `taxon_rank`, `magnitude`) before making one; lower case, snake
  case ([models](models.md#entity)).
- **Units as the service gives them**, in a `Quantity`: no conversion.
- **An occurrence has a time and a place**: leave out the records that lack either; what it is
  of is `related`, never an identifier ([occurrences](occurrences.md)).
- **A series per variable asked, in the order asked**; a variable it does not know is refused
  before the call, or answered by the service's error.
- **The `Page::$next` cursor is the source's own**: an offset, a token; null on the last page.

## A new scheme

A base whose identifiers have no scheme yet adds one to the core first (`Model\Scheme`), in one
change of `omnisense/omnilab`:

- the case and its value (lower case, dots for a sub-kind: `ebird.checklist`);
- its `label()` and its `domain()` (both are `match` expressions over every case: a case
  missing from either throws `\UnhandledMatchError`, as `LAUNCH_LIBRARY` does from `domain()`
  today, [a known defect](models.md#the-methods-of-scheme));
- its normalisation in `Identifier::tryOf()`, its page in `url()`, its pages read back in
  `fromUrl()`, its prefixes (identifiers.org's, the base's own) in `Identifier::PREFIXES`, and
  its shape in `parse()` only when no other scheme shares it;
- its cases in `Tests/IdentifierTest.php`, and its row in [models](models.md#identifier).

## Registering it

| Where | What |
|---|---|
| `Bridge/Symfony/OmnilabBundle.php` | the factory in `FACTORIES` (and its `use`), its name in the `sources` node's `info()` |
| `composer.json` (core) | a `suggest` line |
| `docker/harness/plugins.php` | the slug, the tests' namespace, the factory class |
| `docker/harness/config/sources.php` | the source's options from the environment, and `needs` when it requires a key |
| `docker/.env.dist` | the variables it reads |
| the workspace (`~/Sites/omnilab/composer.json`, `phpunit.xml.dist`) | a path repository and a test suite |
| `README.md`, [index](index.md#the-providers), [Sources](sources.md) | its row; what it does and does not; its throttle and contact |
| `Tests/Bridge/OmnilabBundleTest.php` | its factory's name among those registered |

## Its tests

One test class, a `MockHttpClient` whose callback matches each path and query to a recorded
answer of `Tests/Fixtures/` (the day of the recording in the docblock; a file cut down says so),
the calls logged and asserted (how many, in which order), and at least:

- each operation on a recorded answer, every field and property mapped;
- what is refused before any call (an identifier of another scheme, each refused query);
- null for an unknown thing, an empty page for no match;
- the errors: a 404, a 429 with `Retry-After`, a 5xx, an error the service writes with a 200;
- `capabilities()`, and the options read (the User-Agent sent, a key in its header).

See [Testing](testing.md) for the pattern, and any provider's `Tests/` for a complete one.

## Its page

`docs/index.md`, one file, h1 the package name, then: the service and the API(s) it calls;
Installation; Credentials; Options (every option, type, default, effect; the per-call options
such as occurrence filters); Capabilities (every capability, supported or not); the behaviour of
each call; an example in plain PHP and the Symfony configuration; Errors; Verified, and not;
Responsibility. The existing pages are the model.
