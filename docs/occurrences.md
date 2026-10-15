# Occurrences: the things of a place and a period

The fourth shape. The three others answer "what is this thing" (an entity), "how does this variable
go at this place" (a series) and "what was catalogued over this area" (products); this one answers
**"what was found, or happened, here, then"**: the lions observed within 100 km of Skukuza in
2025, the earthquakes of magnitude 4 or more over Europe in the first week of October 2026. Each
answer is an `Entity` - with its identifier, its properties and units, its related things - that
carries its `time` and its `place`.

```php
$page = $source->occurrences(new OccurrenceQuery(
    place: new Point(-24.99, 31.59),            // or a BBox; none: anywhere
    from: new \DateTimeImmutable('2025-01-01'), // both ends included; a date alone is its whole day
    to: new \DateTimeImmutable('2025-12-31'),
    filters: [OccurrenceQuery::TAXON => '4CGXP'], // what only one domain filters on, by key
    radius: 100,                                // km around the Point; null: the source's default
    limit: 300,                                 // per page, capped by the source
));
$page->total;              // how many the query matches, when the source says
foreach ($page as $occurrence) {
    $occurrence->time;     // when
    $occurrence->place;    // where: a Point
}
$next = $source->occurrences($query->with(['cursor' => $page->next]));
```

## The query: `Source\OccurrenceQuery`

| Field | |
|---|---|
| `place` | a `BBox`, or a `Point` and its `radius` (km; null: the source's default, its README says which); null: anywhere |
| `from`, `to` | the period, both ends included, a date without a time standing for its whole day; an end left out is open |
| `filters` | `array<string, mixed>` by key - what only a domain filters on (below); a source refuses a key it does not read, before any call |
| `radius` | kilometres around a `Point`: a radius around a box, or without a place, is an `InvalidArgumentException` |
| `limit`, `cursor` | a page's size (each source caps it) and the `Page::$next` of the page before |
| `sort` | `OccurrenceQuery::NEWEST`, `OLDEST`, or a key the source sorts by (`magnitude` at the USGS); null: the source's own order. A source that cannot sort as asked refuses |

The filter keys several sources may read are constants of the query, so that a second source
reuses a key before it makes one:

| Key | Value | Read by |
|---|---|---|
| `OccurrenceQuery::TAXON` (`taxon`) | an `Identifier` of the source's scheme, a key, or a scientific name | gbif, inaturalist |
| `OccurrenceQuery::COUNTRY` (`country`) | ISO 3166-1 alpha-2 (`ZA`), or a list | gbif |
| `OccurrenceQuery::KIND` (`kind`) | the source's word for what it is, or a list: `earthquake`, `quarry blast` (usgs); `human_observation`, `preserved_specimen` (gbif's basis of record) | usgs, gbif |
| `OccurrenceQuery::MIN_MAGNITUDE`, `MAX_MAGNITUDE` | a magnitude, included | usgs |
| `OccurrenceQuery::LICENSE` (`license`) | the licence a record is shared under, as Creative Commons writes it lower case (`cc0`, `cc-by`, `cc-by-nc`...), or a list | inaturalist |

A source may read keys of its own besides (the USGS reads `alert`, PAGER's level; iNaturalist
`quality_grade`); its README lists them.

## The answer: a `Page` of `Entity`

The same `Source\Page` as `search()` and `products()`: `items`, `next` (null on the last page),
`total` when the service counts the matches. Every entity of it has a `time` and a `place` (a
`Point`): a source leaves out the records that lack either, or asks the service not to send them.
An entity of a page may hold less than `entity()` gives for it - the USGS's list has no event
products, so no uncertainties nor PAGER review -; `entity($occurrence->id)` reads it whole, and
each source's README says what a page leaves out.

What the occurrence is of goes into `related`, never into `identifiers`: a GBIF occurrence of a
lion carries the lion's taxon as `related('taxon')`. The `Collector` does not follow it and the
`Merger` joins nothing by it - two observations of one species are two occurrences, not one.

## The capability

`Capability::OCCURRENCES`: the source answers `occurrences()`. `Capability::AREA` says it takes a
`BBox` as well as a `Point`. Without the capability, `occurrences()` throws `NotSupportedException`
(`HttpSource` does it for every source that does not override it).

| | gbif | usgs | inaturalist |
|---|---|---|---|
| Occurrences of | organisms: observations, specimens, samples | earthquakes and the other seismic events of ComCat | organisms: iNaturalist's observations, research grade by default |
| Place | a `BBox` (across the antimeridian too) or a `Point` and its radius (10 km by default) | a `BBox` (across the antimeridian too) or a `Point` and its radius (100 km by default) | a `BBox` (across the antimeridian too) or a `Point` and its radius (10 km by default) |
| Period | whole days | to the second | to the second (a bound at midnight: its whole day) |
| Filters | `taxon`, `country`, `kind` (basis of record) | `min_magnitude`, `max_magnitude`, `kind` (event type), `alert` | `taxon`, `license`, `quality_grade` |
| Sort | GBIF's own order only | `newest` (the default), `oldest`, `magnitude`, `magnitude-asc` | none (the latest added first), `newest`, `oldest` (by the day observed) |
| Per page, at most | 300 | 20,000 | 200 |
| Deepest | 100,000 occurrences into a query (GBIF's search; its downloads, which need an account, go further) | none | none unsorted (paged by id); 10,000 sorted |
| Total | yes | no (a count would cost a call more) | on the first page (unsorted), on every page (sorted) |

## In plain PHP

The whole of it, against the real services, no key:

```php
require 'vendor/autoload.php';

use Omnilab\Gbif\GbifSourceFactory;
use Omnilab\Model\BBox;
use Omnilab\Model\Point;
use Omnilab\Source\OccurrenceQuery;
use Omnilab\Usgs\UsgsSourceFactory;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$gbif = (new GbifSourceFactory($http))->create(['mailto' => 'you@example.org']);
$usgs = (new UsgsSourceFactory($http))->create(['mailto' => 'you@example.org']);

// The lions observed within 100 km of Skukuza (Kruger National Park) in 2025.
$query = new OccurrenceQuery(new Point(-24.99, 31.59), new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-12-31'), [OccurrenceQuery::TAXON => '4CGXP'], radius: 100, limit: 300);
$lions = [];
do {
    $page = $gbif->occurrences($query);
    array_push($lions, ...$page->items);
    $query = $query->with(['cursor' => $page->next]);
} while (null !== $page->next);
printf("%d lions (GBIF counts %d)\n", count($lions), $page->total);

// The earthquakes of magnitude 4 or more over Europe in the first week of October 2026, the largest first.
$europe = new BBox(-25, 35, 45, 72);
$page = $usgs->occurrences(new OccurrenceQuery($europe, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-07'), [OccurrenceQuery::MIN_MAGNITUDE => 4], sort: 'magnitude', limit: 5));
foreach ($page as $quake) {
    printf("%s  %s  %s\n", $quake->time->format('Y-m-d H:i'), $quake->place, $quake->label);
}
```

```
412 lions (GBIF counts 412)
2026-10-04 11:43  37.7292,36.1272  M 5.0 - 21 km ESE of Feke, Turkey
2026-10-04 11:19  37.8028,36.1749  M 4.7 - 21 km SSE of Saimbeyli, Turkey
2026-10-04 11:36  37.7757,36.1685  M 4.7 - 22 km E of Feke, Turkey
2026-10-05 17:28  64.5091,-17.3115  M 4.7 - 105 km WNW of Höfn, Iceland
2026-10-02 15:26  38.1524,21.8509  M 4.5 - 7 km NE of Chalandrítsa, Greece
```

(as answered on 2026-10-15: two pages of GBIF - 35 s the first time, its distance search slow,
3 s asked again -, one call to the USGS; the earthquakes' times are UTC)

Also answered that day: GBIF counts 513 lions in the box of the Kruger National Park (30.8, -25.6,
32.1, -22.3) by the name *Panthera leo*, 757 in South Africa in 2025, and 20,352 occurrences of the
lion in all, 16,675 of them present, dated and placed - what `occurrences()` asks for; the USGS,
eleven earthquakes of magnitude 4 or more over Europe that week, in three pages of four.

## Not in the Collector

The `Collector` follows one thing into the sources that know it, by its identifiers. A page of
occurrences has no identity to follow, and two sources' pages hold different things (a lion is no
earthquake): there is nothing to join, and merging by nearness in place and time would guess -
two lions seen at one waterhole on one day are two occurrences. An occurrence of a page is an
entity like any: `$collector->follow($occurrence)` follows it (an earthquake of the USGS into
Wikidata by P3196; a GBIF occurrence published by iNaturalist into its observation, whose number it
carries: `gbif.occurrence:5007681734` is `inaturalist.observation:257284120`, one call), and `$collector->collect('gbif', $occurrence->related('taxon')->get(Scheme::GBIF))`
reads what it is of.

Why this shape, its name and these choices: [decisions](decisions.md).
