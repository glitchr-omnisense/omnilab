# Changelog

## [Unreleased]

### Fixed

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
