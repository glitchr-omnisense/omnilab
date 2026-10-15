# glitchr/omnilab

One contract for the public science-data APIs, whatever the domain - chemistry, biology, species,
astronomy, the Earth, its weather and its air: what science knows of a thing, what it measures at a
place, what it has catalogued over an area, what was found or happened there. Publications are not here: they are
[glitchr/omnischolar](https://github.com/glitchr-studio/omnischolar)'s.

```php
$caffeine = $registry->get('pubchem')->entity('caffeine');
$caffeine->property('molecular_weight');             // 194.19 g/mol, from pubchem
$caffeine->identifier(Scheme::CHEBI);                // 27732: a cross-reference the Collector can follow

$caffeine = $collector->collect('pubchem', 'caffeine');   // then ChEBI and Wikidata, by its InChIKey and its CID
$caffeine->properties('molecular_weight');           // 194.19 g/mol from pubchem, 194.194 g/mol from chebi
$caffeine->property('melting_point');                // 234 °C, from wikidata

[$temperature] = $registry->get('open-meteo')->series(new SeriesQuery(['temperature_2m'], new Point(48.5734, 7.7521), $tomorrow, $tomorrow));
$temperature->max();                                 // 20.4, in $temperature->unit: °C

$scenes = $registry->get('copernicus')->products(new ProductQuery(['sentinel-2-l2a'], $strasbourg, new \DateTimeImmutable('-10 days'), maxCloudCover: 30));
$scenes->first()->preview;                           // the scene's thumbnail; its assets keep their hrefs

$lions = $registry->get('gbif')->occurrences(new OccurrenceQuery(new Point(-24.99, 31.59), $from2025, $to2025, [OccurrenceQuery::TAXON => '4CGXP'], radius: 100));
$lions->total;                                       // 412 lions within 100 km of Skukuza in 2025, each with its time and place
```

Every answer takes one of four shapes, not one per domain:

- an **entity** (`Model\Entity`): a thing named and identified - a compound, a gene, a protein, a
  species, a star, an asteroid, an earthquake - its properties with their units (`Property`,
  `Quantity`), its synonyms, and its identifiers in other bases (`Identifier`, `Scheme`: PubChem
  CID, ChEBI, ChEMBL, InChIKey, CAS, UniProt, NCBI Gene and Taxonomy, Ensembl, PDB, GBIF,
  iNaturalist taxa and observations, SIMBAD, JPL SBDB, USGS events, Wikidata...); a time and a place for an event;
- a **series** (`Model\Series`): one variable at a `Point` or over a `BBox`, its points in time,
  its unit, its step and aggregation;
- a **product** (`Model\Product`, in the shape of a STAC item): a catalogued scene or file, its
  datetime, footprint, properties and assets;
- **occurrences** (a `Page` of `Model\Entity`): the things found or happened at a place in a
  period - the observations of a species, the earthquakes of a region -, each with its time and
  its place ([occurrences](docs/occurrences.md)).

A series may be asked at a station (`ghcn:USW00094728`) rather than a point; a catalogue's
datasets are found by text (`datasets()`), its files read with the source's credentials (`open()`),
and a file made on request is followed as a `Model\Job` until it is ready
([datasets](docs/datasets.md)); the catalogues that speak STAC are read by one reader
(`Source\Stac`).

This package holds the contract (`Source\SourceInterface`, `Source\SourceFactory`, `Registry`), the
models, the `Collector` that follows an entity's cross-references into the other sources and the
`Merger` that makes one entity of several, keeping the source of every property; and a bridge for
Symfony. It needs no framework: it requires nothing but `symfony/http-client-contracts`, each
source package `symfony/http-client`. Each source is a package of its own:

| Package | Source | Shape | Key |
|---|---|---|---|
| [`omnilab/pubchem`](https://github.com/glitchr-studio/omnilab-pubchem) | PubChem (NCBI): compounds by CID, name, InChIKey, CAS, SMILES; computed properties, synonyms, 2D structure, cross-references | entity | none (5 calls a second at most) |
| [`omnilab/open-meteo`](https://github.com/glitchr-studio/omnilab-open-meteo) | Open-Meteo: hourly, 15-minute and daily weather at a point, forecast and archive (from 1940); the air quality (PM2.5, PM10, NO2, O3, the European and US indices, pollens), hourly or daily, from 2013 | series | none for non-commercial use |
| [`omnilab/copernicus`](https://github.com/glitchr-studio/omnilab-copernicus) | Copernicus Data Space: the STAC catalogue of Sentinel, Landsat, MODIS, the DEM and the Copernicus services | product | none to search (a CDSE token to download) |
| [`omnilab/chebi`](https://github.com/glitchr-studio/omnilab-chebi) | ChEBI (EMBL-EBI): chemical entities by ChEBI id, InChIKey, CAS, KEGG or name; masses, structure, ontology (classes, roles), cross-references | entity | none |
| [`omnilab/wikidata`](https://github.com/glitchr-studio/omnilab-wikidata) | Wikidata: any item by Q-number, by a science base's identifier (PubChem, ChEBI, CAS, UniProt, NCBI...) or by label; labels in a language, cross-references, a few properties with units | entity | none (a User-Agent that names the caller; 200 calls a minute) |
| [`omnilab/chembl`](https://github.com/glitchr-studio/omnilab-chembl) | ChEMBL (EMBL-EBI): bioactive molecules by ChEMBL id, InChIKey or name; development phase, computed properties, mechanisms of action and their targets, cross-references from UniChem | entity | none |
| [`omnilab/uniprot`](https://github.com/glitchr-studio/omnilab-uniprot) | UniProtKB: proteins by accession or entry name; function, gene, organism, sequence, mass, cofactors, ligands; their genes in NCBI and Ensembl | entity | none |
| [`omnilab/gbif`](https://github.com/glitchr-studio/omnilab-gbif) | GBIF's taxonomy (the Catalogue of Life eXtended Release since 2026): taxa by key, legacy Backbone number, NCBI id or scientific name; status, IUCN category, lineage; NCBI cross-reference; the occurrences of a place and a period (by taxon, country, basis of record) | entity, occurrences | none |
| [`omnilab/ncbi`](https://github.com/glitchr-studio/omnilab-ncbi) | NCBI Gene and Taxonomy (Datasets v2, E-utilities): genes by GeneID, taxa by id or name; location, summary, lineage; UniProt, Ensembl and GBIF ids | entity | none (3 calls a second; 10 with an API key) |
| [`omnilab/simbad`](https://github.com/glitchr-studio/omnilab-simbad) | SIMBAD (CDS, Strasbourg), through its TAP service: objects beyond the solar system by any identifier; type, coordinates, motion, parallax, velocity, redshift, spectral type, magnitudes | entity | none |
| [`omnilab/jpl`](https://github.com/glitchr-studio/omnilab-jpl) | NASA/JPL's Small-Body Database: asteroids and comets by number, designation, name or SPK-ID; orbit, physical parameters, NEO and PHA flags, close approaches, Sentry's impact risk | entity | none |
| [`omnilab/usgs`](https://github.com/glitchr-studio/omnilab-usgs) | USGS earthquake catalogue (ComCat, FDSN event service): an earthquake by its id, with its time and place; the earthquakes of a box or around a point as series, or as occurrences | entity, series, occurrences | none |
| [`omnilab/ensembl`](https://github.com/glitchr-studio/omnilab-ensembl) | Ensembl (EMBL-EBI): genes, transcripts, proteins and exons by Ensembl id, genes by symbol in a species; biotype, species, assembly, place on the genome; NCBI Gene and UniProt cross-references | entity | none (15 calls a second) |
| [`omnilab/pdb`](https://github.com/glitchr-studio/omnilab-pdb) | RCSB PDB (GraphQL Data API, Search API): structures by PDB id; method, resolution, citation, chains and ligands; their UniProt proteins, organisms and ligands as related things; full-text search | entity | none (a handful of calls a second) |
| [`omnilab/noaa`](https://github.com/glitchr-studio/omnilab-noaa) | NOAA: NCEI's station records (GHCN-Daily, monthly and yearly summaries) at the nearest station or one named (`ghcn:USW00094728`); the National Weather Service's observations and forecasts in the United States, at a point or a station (`nws.station:KNYC`); the stations themselves | series, entity (stations) | none (a User-Agent for the NWS) |
| [`omnilab/cds`](https://github.com/glitchr-studio/omnilab-cds) | Copernicus Climate Data Store: ERA5, seasonal forecasts, projections... as products over a place and a period, the catalogue's search; a request submitted and followed to its file | product | none to read (an account's key to submit) |
| [`omnilab/cams`](https://github.com/glitchr-studio/omnilab-cams) | Copernicus Atmosphere Data Store (CAMS): air quality forecasts and reanalyses, atmospheric composition, greenhouse gases, through omnilab/cds | product | none to read (an account's key to submit) |
| [`omnilab/earthdata`](https://github.com/glitchr-studio/omnilab-earthdata) | NASA Earthdata's Common Metadata Repository: the granules of HLS, MODIS, VIIRS, GPM... by place, period and cloud cover, their files | product | none to search (an Earthdata Login token to download) |
| [`omnilab/inaturalist`](https://github.com/glitchr-studio/omnilab-inaturalist) | iNaturalist: taxa by id or name; common name, IUCN category, lineage, default photo and its licence; the observations of a place and a period, research grade by default, each with its licence | entity, occurrences | none (60 calls a minute) |

A source answers what it can - `capabilities()` says what - and throws `NotSupportedException`
for the rest, before any call. Unknown is `null`, an empty page or a series without points; a
service down or rate limited is an `UnavailableException` (`RateLimitedException`), which a caller
never caches as "nothing".

## Documentation

- [Installation and first calls](docs/installation.md)
- [Sources: what each can and cannot do](docs/sources.md)
- [Models and identifiers](docs/models.md)
- [Occurrences: the things of a place and a period](docs/occurrences.md)
- [Datasets, requests, files](docs/datasets.md)
- [Decisions: what the contract settled, and why](docs/decisions.md)
- [Following cross-references, merging](docs/merging.md)
- [Symfony](docs/symfony.md)
- [The Docker harness](docs/harness.md)

## Plain PHP

```sh
composer require glitchr/omnilab omnilab/pubchem omnilab/open-meteo omnilab/copernicus omnilab/chebi omnilab/wikidata
```

```php
use Omnilab\Chebi\ChebiSourceFactory;
use Omnilab\Copernicus\CopernicusSourceFactory;
use Omnilab\OpenMeteo\OpenMeteoSourceFactory;
use Omnilab\PubChem\PubChemSourceFactory;
use Omnilab\Registry;
use Omnilab\Wikidata\WikidataSourceFactory;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();   // or the application's client; a MockHttpClient in a test
$registry = new Registry([new PubChemSourceFactory($http), new ChebiSourceFactory($http), new WikidataSourceFactory($http), new OpenMeteoSourceFactory($http), new CopernicusSourceFactory($http)], [
    'pubchem' => ['factory' => 'pubchem', 'options' => ['mailto' => 'you@example.org']],
    'chebi' => ['factory' => 'chebi', 'options' => ['mailto' => 'you@example.org']],
    'wikidata' => ['factory' => 'wikidata', 'options' => ['mailto' => 'you@example.org', 'language' => 'fr']],
    'open-meteo' => ['factory' => 'open-meteo'],
    'copernicus' => ['factory' => 'copernicus'],
]);
```

No bundle, no container: a factory per source package, the registry built by hand.
[docs/installation.md](docs/installation.md) opens on a whole script that runs as it is, against
the real services.

## Symfony

`Omnilab\Bridge\Symfony\OmnilabBundle` does that wiring in a Symfony application
([docs/symfony.md](docs/symfony.md)); its components (`symfony/config`,
`symfony/dependency-injection`, `symfony/http-kernel`) are not required by this package.

```yaml
omnilab:
    sources:
        pubchem: { factory: pubchem, options: { mailto: '%env(OMNILAB_MAILTO)%' } }
        chebi: { factory: chebi, options: { mailto: '%env(OMNILAB_MAILTO)%' } }
        wikidata: { factory: wikidata, options: { mailto: '%env(OMNILAB_MAILTO)%', language: fr } }
        meteo: { factory: open-meteo }
        air: { factory: open-meteo, options: { api: air_quality } }
        copernicus: { factory: copernicus }
```

```php
public function __construct(SourceInterface $pubchem, SourceInterface $meteo, Collector $collector) {}
```

## Docker: every source, for real

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnilab sources
docker compose run --rm omnilab entity pubchem caffeine
docker compose run --rm omnilab collect pubchem caffeine   # then ChEBI and Wikidata, merged
docker compose run --rm omnilab series open-meteo 48.5734,7.7521 temperature_2m --from tomorrow --to tomorrow
docker compose run --rm omnilab products copernicus --point 48.5734,7.7521 --from=-10days --cloud 30
docker compose run --rm omnilab occurrences gbif --point -24.99,31.59 --radius 100 --from 2025-01-01 --to 2025-12-31 -f taxon=4CGXP
docker compose run --rm omnilab bare      # plain PHP: no bundle, no container, and what PHP loaded
docker compose run --rm omnilab test
```

License: MIT.
