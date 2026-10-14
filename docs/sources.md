# Sources

Every source implements `Omnilab\Source\SourceInterface`:

| Method | Answers |
|---|---|
| `getName()` | its factory's name: `pubchem`, `open-meteo`, `copernicus`... |
| `capabilities()` | a list of `Capability`: what it can be asked, what its answers hold |
| `entity($id)` | an `Entity`, or null |
| `search(Query)` | a `Page` of `Entity`, the best match first; `$page->next` is the next cursor |
| `series(SeriesQuery)` | a list of `Series`, one per variable asked, in the order asked |
| `products(ProductQuery)` | a `Page` of `Product`, newest first by default |

What a source does not do throws `NotSupportedException`, before any call. `HttpSource`, the base
of the sources over HTTP, throws it for every operation a source does not override.

## Capabilities

| Capability | Says |
|---|---|
| `entity` | `entity()` reads one thing by an identifier (or a name, where the source reads names) |
| `search` | `search()` finds things by text |
| `series` | `series()` serves a variable at a place over a time |
| `products` | `products()` lists catalogued products over a place and a time |
| `structure` | an entity's `image` is its structure (a 2D depiction of a molecule) |
| `cross_references` | an entity carries identifiers of other bases: the `Collector` can follow them |
| `forecast` | `series()` answers ahead of now |
| `historical` | `series()` answers for the past: observations, an archive, a reanalysis |
| `area` | `series()` or `products()` take a `BBox`; without it, a box is refused rather than answered for its centre |
| `cloud_cover` | `products()` applies `ProductQuery::$maxCloudCover`; without it, the query is refused rather than answered unfiltered |

## What each one does

| | pubchem | open-meteo | copernicus |
|---|---|---|---|
| Shape | entity | series | product |
| `entity()` by | CID, name, InChIKey, InChI, CAS, SMILES, ChEBI, ChEMBL, Wikidata, UNII, DSSTox | - | - |
| `search()` | the exact name first, then the names that hold the text as a word | - | - |
| `series()` | - | any Open-Meteo variable at a `Point`: hourly (`PT1H`), 15 minutes (`PT15M`), daily (`P1D`, with `mean`, `max`, `min`, `sum`) | - |
| `products()` | - | - | any collection of the catalogue, at a `Point` or over a `BBox`, a period, a cloud cover maximum |
| Units | from PubChem's documentation (g/mol, Da, Å²) | as Open-Meteo answers (°C, mm, km/h...) | as the catalogue names its properties |
| Cross-references | InChIKey, InChI, CAS, ChEBI, ChEMBL, Wikidata, UNII, DSSTox, KEGG | - | - |
| Paging (cursor) | offset over the CIDs found | - | the next link's token |
| Per page, at most | 100 | - | 100 |

## What the services do not allow

- **PubChem**: five calls a second and 400 a minute at most (the throttle leaves 0.2 s between
  two); a compound costs up to four calls; synonyms are the depositors', cross-references are read
  from them (the first of each scheme, in PubChem's order); experimental properties (melting
  point...) are in PUG-View, not read here; a search by word ranks nothing beyond the exact name.
- **Open-Meteo**: a point only, not a box; the forecast API answers from 92 days ago to 16 days
  ahead, the archive from 1940 to about five days ago; free for non-commercial use only, within
  10 000 calls a day; the point of the model's grid answers, not the exact one asked.
- **Copernicus Data Space**: searching is open, downloading an asset needs a CDSE access token
  (out of this package); the catalogue does not count the matches; at most 100 Sentinel-2 items a
  page without fields selection; a radar collection has no cloud cover, so a cloud filter matches
  nothing there.

## Throttling and politeness

| Source | Default `throttle` | Contact |
|---|---|---|
| pubchem | 0.2 s (PubChem's five a second) | `mailto` in the User-Agent |
| open-meteo | none | User-Agent; `api_key` for a commercial plan |
| copernicus | none | User-Agent |

Every factory takes `mailto`, `user_agent`, `throttle` (seconds between two calls of one source
instance) and `base_uri`.

## Writing a source

A source is a package `omnilab/<name>` whose factory extends `Source\SourceFactory` (its name, its
options and their defaults in `populate()`, the source in `build()`) and whose source extends
`Source\HttpSource`, overriding the operations it does and declaring them in `capabilities()`. It
requires `glitchr/omnilab` and `symfony/http-client`, and accepts the client given to its factory.
Its identifiers are those of `Model\Scheme`: a base without a scheme yet adds one to the core
first. What a source reads it reads from an `Identifier` of one of its schemes, and it throws
`NotSupportedException::identifier()` for any other before calling anything: the `Collector`
relies on it.
