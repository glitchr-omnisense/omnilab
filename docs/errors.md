---
title: Errors
order: 50
---

# Errors

Unknown is not an error: `entity()` answers null, `search()`, `occurrences()`, `products()` and
`datasets()` an empty `Page`, `series()` a series without points (an empty list for a station it
does not know). Everything else a source
cannot or will not answer is an exception.

## The exceptions

Every exception of `Omnilab\Exception` implements `OmnilabException` (an interface that extends
`\Throwable`):

| Exception | Extends | Thrown | Holds |
|---|---|---|---|
| `NotSupportedException` | `\LogicException` | before any call: the source does not do that operation, cannot read that identifier's scheme, a filter key, a box, a station, a step, a sort | its message only (`The "chebi" source cannot look up "pubchem.cid:2519".`, `The "open-meteo" source does not list products.`) |
| `InvalidConfigException` | `\LogicException` | a source not configured, a factory not installed, a required option empty (`Registry::get()`, `SourceFactory::create()`); and at call time, a key a call needs (iucn, ebird) | its message |
| `ProviderException` | `\RuntimeException` | the service answered an error (a 4xx other than 404, 410 and 429), or an answer that is not what was asked (not JSON, not a JSON object) | `source` (`string`), `status` (`?int`, the HTTP status), the message prefixed `[source]` |
| `UnavailableException` | `ProviderException` | the service is down or unreachable: a 5xx, a timeout, a network error, a web page answered where JSON was asked (a maintenance page) | as `ProviderException`; never "not found" |
| `RateLimitedException` | `UnavailableException` | 429, or a budget spent | `retryAfter` (`?int`, seconds, from `Retry-After` when numeric), `status` 429 |

`NotSupportedException::identifier($source, $identifier, $what = 'look up')` and
`::operation($source, $operation)` make its two messages; a source uses them so that the
`Collector` can offer it identifiers at no cost.

## Where the core throws them

| Where | Throws | When |
|---|---|---|
| `Registry::get()` | `InvalidConfigException` | `No "x" source; configured: a, b.`; `No "x" factory for the "y" source; installed: ...` |
| `SourceFactory::create()` (`Config::validateNotEmpty()`) | `InvalidConfigException` | `The "x" source needs: key.` |
| `HttpSource::entity()`, `search()`, `series()`, `products()`, `occurrences()`, `datasets()`, `open()` not overridden | `NotSupportedException` | `The "x" source does not look up entities.` (`search`, `serve series`, `list products`, `list occurrences`, `search datasets`, `open files`) |
| `HttpSource::station()` | `NotSupportedException` | a series asked at a station of a scheme the source does not read |
| `HttpSource::filters()` | `NotSupportedException` | an occurrence filter key the source does not read (`... (it reads "taxon", "country")`) |
| every call of `HttpSource` | `RateLimitedException`, `UnavailableException`, `ProviderException` | 404, 410 and 204 are null; 429 rate limited; 5xx and transport errors unavailable; any other 4xx a provider error with what the service said ([how each service writes it](sources.md#errors)) |
| `HttpSource::postForJson()`, `getForJson()` | `\InvalidArgumentException` | a body that cannot be written as JSON |
| `SeriesQuery`, `ProductQuery`, `OccurrenceQuery` constructors | `\InvalidArgumentException` | no variable, a place that is no station, a period that ends before it starts, a radius without a `Point` or not above 0 |
| `Point`, `BBox` (constructors and `parse()`) | `\InvalidArgumentException` | coordinates out of range, a south above the north, a text that is not `lat,lon` / `west,south,east,north` |
| `Identifier::of()` | `\InvalidArgumentException` | an unknown scheme, a value not valid for it (`tryOf()` answers null instead) |
| `Stac::parameters()` | `\InvalidArgumentException` | a cloud dialect other than `Stac::CQL2`, `Stac::QUERY` |
| `Merger::combine()` | `\InvalidArgumentException` | nothing to combine |

The `\InvalidArgumentException`s are the caller's mistakes, thrown before anything is asked of a
service; they do not implement `OmnilabException`.

A source's key stays out of what `HttpSource` throws: in the query (`api_key=`) or a header (a
bearer, CDS's `PRIVATE-TOKEN`), it is masked in the HTTP client's message (`Http\Redaction::text()`),
the previous exception is a copy of the client's (`Redaction::copy()`: the same class and code),
and no trace of the chain keeps its arguments, where `fetch()`'s query and headers stood
(`zend.exception_ignore_args` off).

## What the sources add

Beyond `HttpSource`'s mapping, a source throws:

- `NotSupportedException` for what it reads and what it does not: a scheme it cannot read, a step
  or aggregation the service does not have, a box where it reads a point, a sort it cannot
  apply, a period it cannot serve (each source's page lists its own under "Errors");
- `ProviderException` for an error the service writes with a 200 (RCSB's GraphQL, JPL Horizons,
  NOAA CO-OPS), and for a call refused for want of a key (materials-project, without
  `api_key`, before any call);
- `InvalidConfigException` at call time when a key the call needs is missing: iucn for every
  call, ebird for every call but the taxonomy;
- `RateLimitedException` once a retry is spent (clinvar makes a 429'd call again once, after the
  wait NCBI asks).

`series()` gives one series per variable asked, in the order asked, at a `Point`: a variable the
source has no value for there - no station near (noaa, tides, usgs-water), no location that
measures it (openaq), a product the station does not have - is a series without points, at the
point asked, so `[$a, $b] = $source->series(...)` holds. A station named by its `Identifier` that
the source does not know (brightsky, noaa, tides, usgs-water) answers an empty list: there is no
place to give the series at.

omnilab/emsc's event whose id it cannot read is a `ProviderException` (`The SeismicPortal answered
an event without an id it reads`), as every answer a source cannot read.

## What to catch

```php
use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Exception\RateLimitedException;
use Omnilab\Exception\UnavailableException;

try {
    $compound = $registry->get('pubchem')->entity($name);   // null: PubChem does not know it
} catch (NotSupportedException $e) {
    // the source cannot read that: a programming or routing matter, not a service's answer
} catch (RateLimitedException $e) {
    // slow down; $e->retryAfter seconds when the service says
} catch (UnavailableException $e) {
    // down or unreachable: try later, and never cache this as "nothing"
} catch (ProviderException $e) {
    // the service refused the request: $e->status, $e->getMessage() says why
}
```

Order the `catch` blocks from the narrowest: `RateLimitedException` before
`UnavailableException`, both before `ProviderException`. The `Collector` catches
`UnavailableException` (and `NotSupportedException`) itself: it skips the source, names it in
`$collector->incomplete` with the message, and the others still answer
([merging](merging.md)). A `ProviderException` that is not unavailability reaches the caller.
