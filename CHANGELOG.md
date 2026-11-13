# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release: it installs with Composer's default minimum-stability (stable).

### Release

- Requires `omnisense/contracts` `^1.0`, stable versions, where it required `@dev` ones:
  `composer require omnisense/omnilab` installs in a project at Composer's default minimum-stability
  (stable). `extra.branch-alias` names the branch (`dev-1.x`: `1.x-dev`).
- CI: `.github/workflows/ci.yml` (glitchr-studio/ci's php-package.yml, pinned: the package
  installed alone, PHPUnit, PHPStan against its baseline, a consumer install) and `release.yml`
  (that CI on the tag, then glitchr-studio/ci's php-release.yml); `phpunit.xml.dist` runs the
  package's own tests.
- README: the install line and a first call.

### Added

- On omnisense/contracts, the kernel of the omni families, with usage policies. Every HTTP request
  a source makes goes through its usage policy (`Policy\SourceGuard`): the `policy.json` its
  package declares (`extra.omnisense.policy`; `HttpSource::usagePolicy()`, the kernel's
  `DeclaresUsagePolicyInterface`; cams' is its own though it runs on cds' class), tightened by
  the site's (`policy`, never looser), checked against what the site is (`usage`: a use the terms
  forbid fails at the first request, `UsageNotPermitted`), counted in the registry's limiter (in
  memory by default, a shared one as `Registry`'s new third argument). `Registry::guard()`,
  `policy()`, `policies()`; `HttpSource::guard()`, `sourceGuard()`, `operation()`, `governed()`
  (a request a source sends itself: a file opened, a form posted); `Policy\PackagePolicy`; the
  options `policy`, `usage`, `max_wait`; the bundle's `policy`, `usage` (per source) and `limiter`.
- `RateLimitedException::notSent()`, `wasSent()`, `$limit`, and a fourth and fifth optional
  constructor argument (the limit, the message).
- Every source (56) declares its policy, read at the service's pages on 2026-11-10
  (docs/policies.md): PubChem's 5 a second and 400 a minute, NCBI's E-utilities (3 a second per
  address, 10 with a key) and Datasets (5, 10 with a key) counted apart, Ensembl's 15 a second and
  55,000 an hour, iNaturalist's 60 a minute and 10,000 a day, Open-Meteo's free tier, GWAS's,
  MPC's, KEGG's, HGNC's, STRING's, SIMBAD's, Wikidata's, Open Topo Data's, OpenAQ's, Materials
  Project's and Launch Library's figures; the data's licences (CC0, CC BY, ODbL, US public domain,
  per dataset for GBIF and OBIS), the credits, keys and User-Agents asked; unknown where nothing is
  published.
- `Testing\BareTestCase` (the kernel's bare check, the family's core allowed) and
  `Tests/LoadAllTest`: every class of the core loads with omnisense/contracts and the HTTP client
  contracts alone - symfony/dependency-injection, config and http-kernel stay suggested; the
  Symfony bundle throws a `LogicException` naming them when they are missing.
- PHPStan at level max on the core and every source (`phpstan.neon`), each baseline what was there
  on 2026-11-10; the harness runs it (`phpstan`), installs omnisense/contracts from its checkout
  (`OMNISENSE_CONTRACTS`) or GitHub, and runs on Docker's default bridge.

### Changed

- **A limit reached waits or refuses.** A request over a limit of the source's policy waits when
  the limit's window ends within `max_wait` seconds (10 by default) - the sleep the sources'
  `throttle` did, now read from the policy, kept for the callers that relied on it (the
  Collector's walks) - and refuses beyond: a `RateLimitedException` (an `UnavailableException`, as
  the service's 429 was) with `wasSent()` false. A source whose `throttle` was 0 now keeps to the
  figures its service publishes (GWAS, MPC, Open-Meteo, Launch Library, Materials Project...);
  Launch Library's sixteenth request of an hour is refused before it is sent. The `throttle`
  defaults stay, as the site's spacing on top.
- **A 429 holds the next requests** for its Retry-After (and a 503's Retry-After, a `RateLimit`
  field with nothing left): waited out within `max_wait`, refused beyond, where they were sent
  before. Its `retryAfter` reads a Retry-After given as a date too (null before).
- `Registry::get()` gives the factory the options configured plus `omnilab.name` and
  `omnilab.limiter`; `options()` is unchanged.
- `OmnilabException` extends omnisense/contracts' `ExceptionInterface`; `InvalidConfigException`
  its `InvalidConfigException` (an `\InvalidArgumentException`, a `\LogicException` as before);
  `Config` its `Config` (prefix `omnilab`, the same methods and messages); `Http\Redaction` its
  `Redaction`.

### Added

- `Omnilab\Http\Redaction`: a request's secrets (`secrets()`), a text without them (`text()`), a
  failure's copy to wrap in its place (`copy()`), a trace without arguments (`withoutArguments()`).

### Fixed

- No key in what `HttpSource` throws. An unreachable service's `UnavailableException` quoted the
  HTTP client's message, the whole URL with a key in its query, and wrapped the client's own
  exception, and every exception thrown by `fetch()` and `decode()` - unreachable, 429, 5xx, 4xx,
  not JSON - kept in its trace the query and headers with the key (`zend.exception_ignore_args`
  off). The message and the previous - now a copy of the client's exception, the same class and code
  (`Http\Redaction`) - have it masked, and none of them keeps its trace's arguments.
- A source's name in the bundle's `sources` is kept as written (`normalizeKeys(false)`): `air-quality`
  was the `Registry`'s `air_quality`, its service `omnilab.source.air_quality`, and a
  `merger.preferences` list naming `air-quality` matched nothing.
- `Scheme::LAUNCH_LIBRARY->domain()` is `Domain::OTHER`, where the case was missing from the
  `match` (`\UnhandledMatchError`).

### Changed

- `SourceInterface::series()` says what an unknown is: a variable the source has no value for at
  the place asked (no station near the Point, no sensor of it) is a series without points, so the
  list keeps one series per variable in the order asked; a station the source does not know (an
  `Identifier`) is an empty list, there being no place to give a series at. omnilab/openaq,
  omnilab/tides and omnilab/usgs-water follow it now; brightsky and noaa did.
