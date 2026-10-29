# Changelog

## [Unreleased]

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
