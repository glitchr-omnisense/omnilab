# Sources

Every source implements `Omnilab\Source\SourceInterface`:

| Method | Answers |
|---|---|
| `getName()` | its factory's name: `pubchem`, `chebi`, `wikidata`, `uniprot`, `ensembl`, `pdb`, `inaturalist`, `simbad`, `usgs`, `open-meteo`, `copernicus`... |
| `capabilities()` | a list of `Capability`: what it can be asked, what its answers hold |
| `entity($id)` | an `Entity`, or null |
| `search(Query)` | a `Page` of `Entity`, the best match first; `$page->next` is the next cursor |
| `series(SeriesQuery)` | a list of `Series`, one per variable asked, in the order asked |
| `products(ProductQuery)` | a `Page` of `Product`, newest first by default |
| `occurrences(OccurrenceQuery)` | a `Page` of `Entity`, each with its `time` and `place`: what was found or happened at a place in a period ([occurrences](occurrences.md)) |
| `datasets(Query)` | a `Page` of `Product`, each a dataset of the catalogue, the best match first; its id is what `ProductQuery::$collections` takes ([datasets](datasets.md)) |
| `open(Asset\|string)` | a file read with the source's credentials: the HTTP response, streamed ([datasets](datasets.md)) |

A source that makes files on request implements `Source\JobSourceInterface` besides: `submit($dataset, $request)`, `job($id)`, `jobs(ProductQuery)` answer a `Model\Job` - a `Product` in the making, its status, progress and file ([datasets](datasets.md#asynchronous-requests-job)).

What a source does not do throws `NotSupportedException`, before any call. `HttpSource`, the base
of the sources over HTTP, throws it for every operation a source does not override.

## Capabilities

| Capability | Says |
|---|---|
| `entity` | `entity()` reads one thing by an identifier (or a name, where the source reads names) |
| `search` | `search()` finds things by text |
| `series` | `series()` serves a variable at a place over a time |
| `products` | `products()` lists catalogued products over a place and a time |
| `occurrences` | `occurrences()` lists the things found or happened at a place in a period |
| `structure` | an entity's `image` is its structure (a 2D depiction of a molecule) |
| `cross_references` | an entity carries identifiers of other bases: the `Collector` can follow them |
| `forecast` | `series()` answers ahead of now |
| `historical` | `series()` answers for the past: observations, an archive, a reanalysis |
| `area` | `series()`, `products()` or `occurrences()` take a `BBox`; without it, a box is refused rather than answered for its centre |
| `datasets` | `datasets()` finds the catalogue's datasets by text |
| `jobs` | the source implements `JobSourceInterface`: requests for a file, submitted and followed |
| `open` | `open()` reads a file with the source's credentials |
| `station` | `series()` takes a station's `Identifier` (`ghcn`, `wigos`, `nws.station`) in place of a `Point`, and answers its own records; without it, a station is refused ([stations](models.md#stations)) |
| `cloud_cover` | `products()` applies `ProductQuery::$maxCloudCover`; without it, the query is refused rather than answered unfiltered |

## What each one does

| | pubchem | chebi | wikidata | open-meteo | copernicus |
|---|---|---|---|---|---|
| Shape | entity | entity | entity | series | product |
| `entity()` by | CID, name, InChIKey, InChI, CAS, SMILES, ChEBI, ChEMBL, Wikidata, UNII, DSSTox | ChEBI id (a secondary one too), InChIKey, CAS, KEGG, name | Q-number, label, and the identifier of any scheme below its statements carry (but InChI) | - | - |
| `search()` | the exact name first, then the names that hold the text as a word | ChEBI's search: an exact match first | by label or alias; with a domain, by text among the items that carry a statement of it | - | - |
| `series()` | - | - | - | any Open-Meteo variable at a `Point`: hourly (`PT1H`), 15 minutes (`PT15M`), daily (`P1D`, with `mean`, `max`, `min`, `sum`); with `api: air_quality`, the air's (`pm2_5`, `pm10`, `nitrogen_dioxide`, `ozone`, `european_aqi`, `us_aqi`, pollens...), hourly or each day's `mean`, `max`, `min`, `sum` of the hours | - |
| `products()` | - | - | - | - | any collection of the catalogue, at a `Point` or over a `BBox`, a period, a cloud cover maximum |
| Units | from PubChem's documentation (g/mol, Da, Å²) | g/mol (average mass), Da | the unit item's symbol (Da, °C, g/cm³...) | as Open-Meteo answers (°C, mm, km/h...; μg/m³, EAQI, USAQI, grains/m³) | as the catalogue names its properties |
| Cross-references | InChIKey, InChI, CAS, ChEBI, ChEMBL, Wikidata, UNII, DSSTox, KEGG | secondary ChEBI ids, InChIKey, InChI, CAS, KEGG (and ChEMBL, PubChem, Wikidata, UniProt where ChEBI keeps them) | PubChem CID, ChEBI, CAS, InChIKey, InChI, ChEMBL, UNII, DSSTox, KEGG, UniProt, NCBI Gene, Ensembl, NCBI Taxonomy, GBIF, iNaturalist, SIMBAD, JPL SBDB, USGS event | - | - |
| Paging (cursor) | offset over the CIDs found | ChEBI's page number | the search's offset | - | the next link's token |
| Per page, at most | 100 | 100 | 50 | - | 100 |

| | uniprot | ncbi | gbif |
|---|---|---|---|
| Shape | entity | entity | entity, occurrences |
| `entity()` by | accession (a merged one gives its entry), entry name (`HBB_HUMAN`) | GeneID; NCBI taxon id (`txid9689`); a taxon's scientific or common name | GBIF key (`4CGXP`), legacy Backbone number, NCBI taxon id, scientific name (a synonym gives the accepted taxon); an occurrence by its key (`gbif.occurrence:5007681734`) |
| `search()` | UniProt's query syntax, page by the `Link` header's cursor | Entrez's syntax (`esearch`): NCBI Gene, or NCBI Taxonomy for `Domain::TAXONOMY` | GBIF's search of the checklist's names |
| `occurrences()` | - | - | GBIF's occurrence search: a `BBox` or a `Point` (10 km by default), whole days; `taxon`, `country`, `kind` (basis of record); 300 a page, 100,000 deep; its taxon related |
| Units | aa, Da | - | - |
| Cross-references | NCBI Gene, Ensembl gene | a gene: UniProt (Swiss-Prot), Ensembl; a taxon: GBIF (from its links) | legacy GBIF number, NCBI Taxonomy (ENA's checklist) |
| Related | `organism` (NCBI taxon), `structure` (PDB, 20 by default), `ligand`, `cofactor` (ChEBI) | a gene's `organism`, a taxon's `parent` | `parent`, or a synonym's `accepted`; an occurrence's `taxon` (and `accepted`) |
| Paging (cursor) | UniProt's cursor | the search's offset | the search's offset |
| Per page, at most | 500 | 100 | 1000 |

| | chembl | simbad | jpl | usgs |
|---|---|---|---|---|
| Shape | entity | entity | entity | entity, series, occurrences |
| `entity()` by | ChEMBL id, InChIKey, name or synonym | any SIMBAD identifier or name (`M31`, `Betelgeuse`, `HD 39801`) | number, designation, name, SPK-ID | event id (ComCat or another network's) |
| `search()` | ChEMBL's full-text search; an InChIKey or its first block | by identifier or name | the names that hold the text, the designation that is it | - (no text search at the USGS) |
| `occurrences()` | - | - | - | the events of a `BBox` or around a `Point` (100 km by default) in a period; `min_magnitude`, `max_magnitude`, `kind` (event type), `alert`; sorted `newest`, `oldest`, `magnitude`; 20,000 a page |
| `series()` | - | - | - | per step: count, magnitude, depth, at a `BBox` or around a `Point` |
| Cross-references | InChIKey, InChI; ChEBI, PubChem, UNII, DSSTox (UniChem) | its own id alone | its SPK-ID (a second `jpl.sbdb`) | the other networks' ids for the event |
| Related | the targets of its mechanisms, their proteins (UniProt) | - | - | - |
| Collector path | PubChem -> ChEMBL by the InChIKey | Wikidata -> SIMBAD (P3083) | Wikidata -> JPL (P716) | Wikidata -> USGS (P3196), and back |

| | ensembl | pdb | inaturalist |
|---|---|---|---|
| Shape | entity | entity | entity, occurrences |
| `entity()` by | Ensembl id (gene, transcript, protein, exon; its version dropped); a community's id of a genome Ensembl serves (`ensembl.genomes`: `FBgn0000008`, `YAL001C`, `AT5G10140`); a gene's symbol in a species (`HBB`, `mus_musculus/Hbb-bs`, `drosophila_melanogaster/w`) | PDB id (`4HHB`, `pdb_00004hhb`); a removed entry gives its only replacement | taxon id, a name matched exactly (scientific, synonym, common); an observation by its number (`inaturalist.observation:332912788`) |
| `search()` | the genes a symbol names in a species, page by offset | RCSB's full-text search (experimental structures), then GraphQL for the page | iNaturalist's search of its names, page by number |
| `occurrences()` | - | - | the observations of a `BBox` (across the antimeridian too) or around a `Point` (10 km by default) in a period, to the second; `taxon`, `license`, `quality_grade` (research by default); unsorted (latest added first, paged by id, no depth limit) or `newest`, `oldest` (10,000 deep); 200 a page |
| Units | bp, aa | Å, kDa | m |
| Cross-references | a gene: NCBI Gene, its Swiss-Prot entries; a protein: its UniProt entries | its own id (and the removed id it was asked by) | none (iNaturalist gives no other base's id) |
| Related | `organism` (NCBI taxon), a gene's canonical `transcript`, a transcript's `gene` and `protein`, a protein's `transcript` and `gene` | `protein` (its chains' UniProt), `organism`, `ligand` (ChEBI, else PubChem, else InChIKey) | `parent`, or an inactive taxon's `accepted`; an observation's `taxon` |
| Collector path | NCBI Gene or UniProt -> Ensembl by the Ensembl gene id; Ensembl -> NCBI Gene, UniProt, Wikidata (P594) | none: an entry is taken alone, reached from UniProt's `structure`s; its `protein`s lead back | Wikidata -> iNaturalist (P3151), and back; GBIF -> Wikidata -> iNaturalist at depth 2; a GBIF occurrence -> its iNaturalist observation |

| | gaia | exoplanets |
|---|---|---|
| Shape | entity | entity |
| `entity()` by | Gaia DR3 source_id (`gaia:4472832130942575872`), designation (`Gaia DR3 ...`, its `simbad` form too) | a confirmed planet's name: the archive's (`exoplanet:51 Peg b`), SIMBAD's (`* 51 Peg b`), or an alias the archive resolves (`Dimidium`); a star's name refused |
| `search()` | - | by planet or star name, paged by name |
| Outside the contract | `cone()`: the sources of a cone on the sky (the core's places are on the Earth) | `planets()`: planets by simple criteria |
| Units | deg, mas, mas/yr, km/s, mag, K, pc, R☉, M☉, Gyr | the archive's (Earth and Jupiter masses and radii, days, au, K) |
| Cross-references | its designation and its Hipparcos best neighbour as SIMBAD names them | its name as SIMBAD reads it (and SIMBAD's main identifier when asked by it) |
| Related | - | `host`: the star by each designation (name, HD, HIP, TIC, Gaia DR3, the last in the `gaia` scheme too) |
| Collector path | SIMBAD -> Gaia (the designation), Wikidata -> Gaia (P13228) | Wikidata -> SIMBAD + the archive (P3083, P5667) |

Their limits and politeness are in each package's `docs/index.md`.

## Errors

Unknown is null; anything else a service refuses is an exception, before or after a call:

| Exception | When |
|---|---|
| `NotSupportedException` | the source does not do it (an operation, a scheme, a filter, a box), said before any call |
| `RateLimitedException` | 429: slow down (`retryAfter` when the service says, in seconds) |
| `UnavailableException` | 5xx, a timeout, a host that does not answer |
| `ProviderException` | any other error answer (4xx), its `status` and what the service said |

`HttpSource` maps a call's answer so for every source, and writes into the message what the
service said - the services write it each its own way, all read:

| The answer | Services | The message |
|---|---|---|
| `{"reason": ...}` | Open-Meteo | the reason |
| `{"Fault": {"Message", "Details": [...]}}` | PubChem | the message (the details) |
| `{"title", "detail", "parameterErrors": [...]}` (RFC 9457 problem details) | the CDS and the ADS, the NWS | title: detail (each parameter: its message) |
| `{"detail": {"message"}}`, `{"detail": "..."}`, `{"code", "description"}` | the Copernicus Data Space's STAC | the message, the detail, the description |
| `{"errorMessage", "errors": [{"field", "message"}]}` | NCEI | the message (each field: its message) |
| `{"errors": ["..."]}`, `{"errors": [{"message"}]}` | NASA's CMR, iNaturalist's v2 | the errors |
| `{"messages": ["..."]}` | UniProt | the messages |
| `{"message"}`, `{"error_message"}` | NCBI Datasets, JPL, RCSB's search; ChEMBL | the message |
| `{"error": "..."}`, `{"error": {"info"}}` | Ensembl, iNaturalist's v1; Wikidata | the error, its info |
| an HTML page, a VOTable | GBIF's front (Varnish), the EBI's; SIMBAD's TAP | the page's title; the `QUERY_STATUS` error |
| any other text | the USGS, GBIF | the text, white space collapsed, cut at 300 characters |

A web page answered where JSON was asked, whatever its status, is an `UnavailableException` that
gives the page's title: a service's maintenance page (the ESA Gaia Archive answered 200 with its
"Gaia (OPER) Downtime Page" on the evening of 2026-10-15).

```php
use Omnilab\Cds\CdsSourceFactory;
use Omnilab\Exception\ProviderException;
use Symfony\Component\HttpClient\HttpClient;

$cds = (new CdsSourceFactory(HttpClient::create()))->create(['key' => 'not-a-key']);
try {
    $cds->submit('reanalysis-era5-single-levels', ['variable' => ['2m_temperature']]);
} catch (ProviderException $e) {
    echo $e->status, ' ', $e->getMessage(), "\n";
}
```

```
401 [cds] the service answered 401: Authentication failed: operation not allowed
```

(as answered on 2026-10-15; NCEI's `{"errorCode":400,"errorMessage":"Bad Request","errors":[{"field":"dataset","message":"Unsupported dataset."}]}`
reads `Bad Request (dataset: Unsupported dataset.)`, the CMR's `{"errors":["[foo] is not a valid URL encoded bounding_box"]}` its error)

## What the services do not allow

- **PubChem**: five calls a second and 400 a minute at most (the throttle leaves 0.2 s between
  two); a compound costs up to four calls; synonyms are the depositors', cross-references are read
  from them (the first of each scheme, in PubChem's order); experimental properties (melting
  point...) are in PUG-View, not read here; a search by word ranks nothing beyond the exact name.
- **ChEBI**: one call for an entry by its ChEBI id, two by anything else (its search first); no
  PubChem CID nor Wikidata item among caffeine's cross-references (the Wikidata source finds the
  item by the ChEBI id); the ontology's relations give names, not ids; the search answers 404 for
  nothing found, and does not answer a page past the last one (the source never asks it); the
  SOAP service of ChEBI 1 is gone.
- **Wikidata**: a User-Agent that names the caller is required (403 otherwise); 200 calls a
  minute for an unauthenticated client (Wikimedia's limits, new in 2026), in series; an item costs
  two or three calls and its answer is large (caffeine's, 260 kB); an identifier two items carry
  names neither; PDB structures (P638) are not taken for cross-references; GBIF's key is read from
  P14607 (its 2026 taxonomy, `4CGXP`) and P846 (the legacy number), few items carrying P14607 yet
  (2663 on 2026-10-15); the search index follows edits after a delay.
- **Open-Meteo**: a point only, not a box; the forecast API answers from 92 days ago to 16 days
  ahead, the archive from 1940 to about five days ago; free for non-commercial use only, within
  10 000 calls a day; the point of the model's grid answers, not the exact one asked. Its
  air-quality API (`api: air_quality`, a source of its own) answers hourly from 2013-01-01 to about
  five days ahead (the CAMS models; a grid point answers: 48.6, 7.8 for Strasbourg), no box, no
  daily variable (the source makes the days from the hours), and says nothing of whether a past
  hour is a forecast run's or a reanalysis' (`forecast` null).
- **Copernicus Data Space**: searching is open, downloading an asset needs a CDSE access token
  (out of this package); the catalogue does not count the matches; at most 100 Sentinel-2 items a
  page without fields selection; a radar collection has no cloud cover, so a cloud filter matches
  nothing there.

- **UniProt**: no rate published (only the `stream` endpoint, not used, answers 429); an entry
  whole is large (hemoglobin beta: 342 kB), so each call names its fields; asked for `gzip` alone,
  UniProt compresses the body twice - the source asks for `identity`; an accession merged into
  another is answered 303 (a client that follows it gets the entry whole, its fields dropped); no
  Wikidata item among its cross-references, no secondary accessions among the fields.
- **NCBI**: three calls a second for E-utilities, five for Datasets, ten for both with an API key;
  `tool` and `email` asked of every E-utilities client; Datasets has no text search (E-utilities'
  `esearch` does it, Datasets then reads the page in one call); a taxon's links name GBIF, EOL and
  Wikipedia, not Wikidata nor iNaturalist; GBIF's page there is a legacy key.
- **GBIF**: no rate published, 429 when it slows a caller down (its search APIs first); its
  taxonomy is the Catalogue of Life eXtended Release since June 2026, but a call that names no
  checklist is still answered from the frozen Backbone - the source names it every time; three calls
  a taxon (match, record, the ENA usage that gives the NCBI id); no Wikidata nor iNaturalist id;
  its occurrence search answers 300 a page and stops 100,000 deep (its downloads, which need an
  account, are not read); a period is whole days; it sorts nothing; a legacy number as a taxon
  filter is read in the frozen Backbone.
- **ChEMBL**: no rate published; a molecule costs up to four calls (the record, UniChem's
  cross-references, the mechanisms, their targets); its record names no other base but the
  InChI and its key - the others come from UniChem, which answers only POST, a call the
  `throttle` does not space; a full-text search for an InChIKey answers unrelated molecules, so a
  key is matched on the structures instead.
- **SIMBAD**: blacklists a client beyond about five to ten queries a second (the source stays at
  five); one ADQL query an object; no object of the solar system (see JPL); a search sorts the
  matches itself, SIMBAD's `ORDER BY` over the join taking minutes; the measurement tables and the
  bibliography are not read.
- **JPL SBDB**: one request at a time, as JPL's fair use asks (0.2 s apart); one call a body, every
  section asked at once and at full precision (without it every value is rounded to three
  digits); a name two bodies share (`Halley`) names neither; the search's regular expression is
  case-sensitive, so each letter is asked in both cases (4 to 7 s); close approaches and Sentry
  are read per body, not as lists.
- **USGS**: no rate published; one call an event, one for a series whatever its variables, one a
  page of occurrences; at most 20,000 events a query for a series (the service's 400, said as
  such), 20,000 a page of occurrences, which page through more; no count without a call more (a
  page has no `total`); no text search; no Wikidata item among an event's ids (Wikidata keeps the
  USGS id in P3196, which omnilab/wikidata reads). `events()`, its list before the contract had
  occurrences, is deprecated.
- **Ensembl**: 15 calls a second and 55,000 an hour; every call must say `Content-Type:
  application/json`, a GET's too (Ensembl answers HTML otherwise); an unknown id or symbol is a 400,
  read as null; a gene costs four calls (record, species, its cross-references, its Swiss-Prot
  entries: its own list mixes the reviewed and unreviewed with nothing to tell them apart); no
  Wikidata item among its cross-references (Wikidata keeps the Ensembl gene id in P594); the
  genomes of Ensembl Genomes (plants, yeasts, the fly) keep their communities' ids, which are not
  the `ensembl` scheme.
- **RCSB PDB**: every API is rate-limited ("a handful of calls a second" to start with, 429 beyond);
  GraphQL answers its errors with a 200; the Search API answers ids only, and a 204 when nothing
  matches; a removed entry replaced by several is none of them (null); no Wikidata link (P638 lists
  a thing's structures, which are not it).
- **iNaturalist**: 100 calls a minute at most, 60 asked, 10,000 a day, and blocks without notice a
  use that weighs on it; "not data scraping" (its exports and GBIF are for bulk); version 1 answers
  an observation whole (44 MB a page of 200), so the observations are read in version 2, its fields
  chosen; paging by number stops 10,000 deep (the unsorted order pages by id instead, without a
  limit); no identifier of another base for a taxon; each observation and photo keeps its owner's
  licence, all rights reserved when none.

## Throttling and politeness

| Source | Default `throttle` | Contact |
|---|---|---|
| pubchem | 0.2 s (PubChem's five a second) | `mailto` in the User-Agent |
| chebi | none (no rate published) | `mailto` in the User-Agent |
| wikidata | 0.3 s (Wikimedia's 200 a minute) | a User-Agent with `mailto` or the project's URL: required |
| open-meteo | none (weather and air quality) | User-Agent; `api_key` for a commercial plan |
| copernicus | none | User-Agent |
| uniprot | none (no rate published) | `mailto` in the User-Agent |
| ncbi | 0.34 s without a key, 0.1 s with one (`auto`) | `tool` and `email` (from `mailto`) on E-utilities; `api_key` |
| gbif | none (no rate published) | `mailto` in the User-Agent, as GBIF asks |
| chembl | none (no rate published; UniChem's POST is not spaced) | `mailto` in the User-Agent |
| simbad | 0.2 s (SIMBAD blacklists beyond five to ten a second) | `mailto` in the User-Agent |
| gaia | none (no rate published; an anonymous query has 10 s) | `mailto` in the User-Agent |
| exoplanets | none (no rate published) | `mailto` in the User-Agent |
| jpl | 0.2 s (one request at a time, as JPL asks) | `mailto` in the User-Agent, as JPL asks |
| usgs | none (no rate published) | `mailto` in the User-Agent |
| ensembl | 0.07 s (Ensembl's 15 a second) | `mailto` in the User-Agent; `Content-Type: application/json` |
| pdb | 0.25 s (RCSB's "a handful a second") | `mailto` in the User-Agent |
| inaturalist | 1 s (iNaturalist's 60 a minute) | `mailto` in the User-Agent |

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
