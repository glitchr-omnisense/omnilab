# omnisense/omnilab

Omnilab is one PHP contract for the public science-data APIs, whatever their domain: chemistry,
biology, species, astronomy, the Earth, its weather and its air. An application asks every
service the same questions - what is known of this thing, what was measured at this place, what
has been catalogued over this area, what was found or happened there - and gets the same answers
back. Each service is a package of its own (`omnilab/pubchem`, `omnilab/gbif`,
`omnilab/open-meteo`...) that turns those questions into the service's calls.

```php
$caffeine = $registry->get('pubchem')->entity('caffeine');
$caffeine->property('molecular_weight');                // 194.19 g/mol, from pubchem
$caffeine->identifier(Scheme::CHEBI);                   // 27732: a cross-reference the Collector can follow

$caffeine = $collector->collect('pubchem', 'caffeine'); // then ChEBI and Wikidata, merged
$caffeine->properties('molecular_weight');              // 194.19 g/mol from pubchem, 194.194 g/mol from chebi

[$temperature] = $registry->get('open-meteo')->series(new SeriesQuery(['temperature_2m'], new Point(48.5734, 7.7521), $tomorrow, $tomorrow));
$scenes = $registry->get('copernicus')->products(new ProductQuery(['sentinel-2-l2a'], $strasbourg, new \DateTimeImmutable('-10 days'), maxCloudCover: 30));
$lions = $registry->get('gbif')->occurrences(new OccurrenceQuery(new Point(-24.99, 31.59), $from, $to, [OccurrenceQuery::TAXON => '4CGXP'], radius: 100));
```

Every answer takes one of four shapes, not one per domain:

- an **entity** (`Model\Entity`): a thing named and identified - a compound, a gene, a protein, a
  species, a star, an earthquake, a station - with its properties and their units
  (`Property`, `Quantity`), its synonyms, its identifiers in other bases (`Identifier`,
  `Scheme`) and the other things it is tied to (`related`);
- a **series** (`Model\Series`): one variable at a `Point`, over a `BBox` or at a station, its
  points in time, its unit, its step and aggregation;
- a **product** (`Model\Product`, the shape of a STAC item): a catalogued scene, granule or
  dataset, its footprint, properties and assets; a `Model\Job` is a product made on request;
- **occurrences**: a `Page` of entities found or happened at a place in a period, each with its
  time and its place.

This package holds the contract (`Source\SourceInterface`, `Source\JobSourceInterface`,
`Source\SourceFactory`, `Source\HttpSource`), the models, the `Registry` that builds the sources
by name, the `Collector` that follows an entity's cross-references from one source into the next,
the `Merger` that makes one entity of several while keeping the source of every property, a
STAC reader (`Source\Stac`) and a Symfony bundle. It requires nothing but PHP 8.2 and
`symfony/http-client-contracts`; each source package adds `symfony/http-client`.

## When to use it, and when not

Use it when an application reads one or several public science services and wants their answers
in one shape: a page that describes a compound from PubChem, ChEBI and Wikidata at once; a
dashboard of the weather, the air and the rivers at a place; the observations of a species
around a park; the scenes of a satellite over a field. The `Collector` is the reason to take
several sources rather than one: it follows the identifiers each one gives into the others.

Do not use it for:

- publications and their metadata: they are
  [omnisense/omnischolar](https://github.com/glitchr-omnisense/omnischolar)'s;
- decoding files: a NetCDF, a GRIB, a GeoTIFF or a CIF is opened (`open()`) and handed over as
  an HTTP response, never read;
- bulk downloads: every source reads pages a caller asks for, within the service's limits
  ([Sources](sources.md#what-the-services-do-not-allow)); the services' own exports are for bulk;
- converting units: a `Quantity` keeps the unit the service gave.

## The providers

56 source packages, each published as `omnilab/<name>` and documented in its own repository.
The capabilities are what each source's `capabilities()` answers with its default options
(checked on 2026-10-27 by building every factory in the harness image); the key column is what
the service asks.

### Chemistry and materials

| Package | Service | Capabilities | Key |
|---|---|---|---|
| [`omnilab/pubchem`](https://github.com/glitchr-omnisense/omnilab-pubchem) | PubChem (NCBI) | `entity`, `search`, `structure`, `cross_references` | none (5 calls a second at most) |
| [`omnilab/chebi`](https://github.com/glitchr-omnisense/omnilab-chebi) | ChEBI (EMBL-EBI) | `entity`, `search`, `structure`, `cross_references` | none |
| [`omnilab/chembl`](https://github.com/glitchr-omnisense/omnilab-chembl) | ChEMBL (EMBL-EBI) | `entity`, `search`, `structure`, `cross_references` | none |
| [`omnilab/kegg`](https://github.com/glitchr-omnisense/omnilab-kegg) | KEGG | `entity`, `search`, `structure`, `cross_references` | none (academic use only; 3 calls a second) |
| [`omnilab/rhea`](https://github.com/glitchr-omnisense/omnilab-rhea) | Rhea (SIB) | `entity`, `search`, `cross_references` | none |
| [`omnilab/cactus`](https://github.com/glitchr-omnisense/omnilab-cactus) | NCI/CADD Chemical Identifier Resolver | `entity`, `structure`, `cross_references` | none |
| [`omnilab/cod`](https://github.com/glitchr-omnisense/omnilab-cod) | Crystallography Open Database | `entity`, `search`, `open` | none (CC0) |
| [`omnilab/materials-project`](https://github.com/glitchr-omnisense/omnilab-materials-project) | Materials Project | `entity`, `search` | an account's API key (free) |
| [`omnilab/oqmd`](https://github.com/glitchr-omnisense/omnilab-oqmd) | Open Quantum Materials Database | `entity`, `search` | none |
| [`omnilab/codata`](https://github.com/glitchr-omnisense/omnilab-codata) | CODATA constants (NIST) | `entity`, `search` | none |

### Genes, proteins, pathways, medicine

| Package | Service | Capabilities | Key |
|---|---|---|---|
| [`omnilab/uniprot`](https://github.com/glitchr-omnisense/omnilab-uniprot) | UniProtKB | `entity`, `search`, `cross_references` | none |
| [`omnilab/ncbi`](https://github.com/glitchr-omnisense/omnilab-ncbi) | NCBI Gene and Taxonomy | `entity`, `search`, `cross_references` | none (3 calls a second; 10 with an API key) |
| [`omnilab/ensembl`](https://github.com/glitchr-omnisense/omnilab-ensembl) | Ensembl | `entity`, `search`, `cross_references` | none (15 calls a second) |
| [`omnilab/pdb`](https://github.com/glitchr-omnisense/omnilab-pdb) | RCSB PDB | `entity`, `search`, `structure` | none (a handful of calls a second) |
| [`omnilab/reactome`](https://github.com/glitchr-omnisense/omnilab-reactome) | Reactome | `entity`, `search` | none |
| [`omnilab/string`](https://github.com/glitchr-omnisense/omnilab-string) | STRING | `entity`, `cross_references` | none (one call a second, `caller_identity`) |
| [`omnilab/interpro`](https://github.com/glitchr-omnisense/omnilab-interpro) | InterPro | `entity`, `search` | none |
| [`omnilab/quickgo`](https://github.com/glitchr-omnisense/omnilab-quickgo) | QuickGO (Gene Ontology) | `entity`, `search` | none |
| [`omnilab/hgnc`](https://github.com/glitchr-omnisense/omnilab-hgnc) | HGNC | `entity`, `search`, `cross_references` | none (10 calls a second) |
| [`omnilab/opentargets`](https://github.com/glitchr-omnisense/omnilab-opentargets) | Open Targets Platform | `entity`, `search`, `cross_references` | none |
| [`omnilab/clinvar`](https://github.com/glitchr-omnisense/omnilab-clinvar) | ClinVar (NCBI) | `entity`, `search`, `cross_references` | none (3 calls a second; 10 with an API key) |
| [`omnilab/gwas`](https://github.com/glitchr-omnisense/omnilab-gwas) | GWAS Catalog | `entity`, `search` | none |

### Species and occurrences

| Package | Service | Capabilities | Key |
|---|---|---|---|
| [`omnilab/gbif`](https://github.com/glitchr-omnisense/omnilab-gbif) | GBIF | `entity`, `search`, `occurrences`, `area`, `cross_references` | none |
| [`omnilab/inaturalist`](https://github.com/glitchr-omnisense/omnilab-inaturalist) | iNaturalist | `entity`, `search`, `occurrences`, `area` | none (60 calls a minute) |
| [`omnilab/col`](https://github.com/glitchr-omnisense/omnilab-col) | Catalogue of Life (ChecklistBank) | `entity`, `search`, `cross_references` | none |
| [`omnilab/itis`](https://github.com/glitchr-omnisense/omnilab-itis) | ITIS | `entity`, `search` | none |
| [`omnilab/worms`](https://github.com/glitchr-omnisense/omnilab-worms) | WoRMS | `entity`, `search`, `cross_references` | none |
| [`omnilab/obis`](https://github.com/glitchr-omnisense/omnilab-obis) | OBIS | `entity`, `occurrences`, `area`, `cross_references` | none |
| [`omnilab/pbdb`](https://github.com/glitchr-omnisense/omnilab-pbdb) | Paleobiology Database | `entity`, `search` | none |
| [`omnilab/ebird`](https://github.com/glitchr-omnisense/omnilab-ebird) | eBird API 2.0 | `entity`, `search`, `occurrences` | a key (the user's) for all but the taxonomy |
| [`omnilab/iucn`](https://github.com/glitchr-omnisense/omnilab-iucn) | IUCN Red List API v4 | `entity` | a token (the user's, required) |

### Astronomy and space

| Package | Service | Capabilities | Key |
|---|---|---|---|
| [`omnilab/simbad`](https://github.com/glitchr-omnisense/omnilab-simbad) | SIMBAD (CDS) | `entity`, `search` | none |
| [`omnilab/gaia`](https://github.com/glitchr-omnisense/omnilab-gaia) | ESA Gaia DR3 | `entity` | none |
| [`omnilab/exoplanets`](https://github.com/glitchr-omnisense/omnilab-exoplanets) | NASA Exoplanet Archive | `entity`, `search` | none |
| [`omnilab/ned`](https://github.com/glitchr-omnisense/omnilab-ned) | NASA/IPAC Extragalactic Database | `entity` | none |
| [`omnilab/vizier`](https://github.com/glitchr-omnisense/omnilab-vizier) | VizieR (CDS) | `datasets` | none |
| [`omnilab/jpl`](https://github.com/glitchr-omnisense/omnilab-jpl) | JPL Small-Body Database | `entity`, `search` | none |
| [`omnilab/mpc`](https://github.com/glitchr-omnisense/omnilab-mpc) | IAU Minor Planet Center | `entity`, `search`, `cross_references` | none |
| [`omnilab/horizons`](https://github.com/glitchr-omnisense/omnilab-horizons) | JPL Horizons | `series`, `forecast`, `historical` | none (one request at a time) |
| [`omnilab/donki`](https://github.com/glitchr-omnisense/omnilab-donki) | NASA DONKI | `entity` | none |
| [`omnilab/launches`](https://github.com/glitchr-omnisense/omnilab-launches) | Launch Library 2 | `entity`, `search`, `occurrences` | none (15 calls an hour; a supporter's key) |
| [`omnilab/swpc`](https://github.com/glitchr-omnisense/omnilab-swpc) | NOAA Space Weather Prediction Center | `series`, `forecast` | none |

### The Earth, weather and air

| Package | Service | Capabilities | Key |
|---|---|---|---|
| [`omnilab/usgs`](https://github.com/glitchr-omnisense/omnilab-usgs) | USGS earthquakes (ComCat) | `entity`, `series`, `occurrences`, `historical`, `area` | none |
| [`omnilab/emsc`](https://github.com/glitchr-omnisense/omnilab-emsc) | EMSC earthquakes (SeismicPortal) | `entity`, `series`, `occurrences`, `historical`, `area` | none |
| [`omnilab/open-meteo`](https://github.com/glitchr-omnisense/omnilab-open-meteo) | Open-Meteo (weather, air quality) | `series`, `forecast`, `historical` | none for non-commercial use |
| [`omnilab/noaa`](https://github.com/glitchr-omnisense/omnilab-noaa) | NOAA NCEI and National Weather Service | `entity`, `series`, `historical`, `station` | none (a User-Agent for the NWS) |
| [`omnilab/brightsky`](https://github.com/glitchr-omnisense/omnilab-brightsky) | Bright Sky (DWD open data) | `entity`, `series`, `forecast`, `historical`, `station` | none |
| [`omnilab/openaq`](https://github.com/glitchr-omnisense/omnilab-openaq) | OpenAQ API v3 | `series`, `historical` | a key (required) |
| [`omnilab/usgs-water`](https://github.com/glitchr-omnisense/omnilab-usgs-water) | USGS Water Data | `entity`, `search`, `series`, `historical`, `station` | none (an api.data.gov key raises the limit) |
| [`omnilab/tides`](https://github.com/glitchr-omnisense/omnilab-tides) | NOAA CO-OPS Tides and Currents | `entity`, `series`, `forecast`, `historical`, `station` | none |
| [`omnilab/elevation`](https://github.com/glitchr-omnisense/omnilab-elevation) | Open Topo Data | none of the contract's (methods of its own) | none (100 locations a call, 1,000 calls a day) |

### Catalogues of products and datasets

| Package | Service | Capabilities | Key |
|---|---|---|---|
| [`omnilab/copernicus`](https://github.com/glitchr-omnisense/omnilab-copernicus) | Copernicus Data Space (STAC) | `products`, `area`, `cloud_cover` | none to search (a CDSE token to download) |
| [`omnilab/cds`](https://github.com/glitchr-omnisense/omnilab-cds) | Copernicus Climate Data Store | `products`, `area`, `datasets`, `jobs`, `open` | none to read (an account's key to submit) |
| [`omnilab/cams`](https://github.com/glitchr-omnisense/omnilab-cams) | Copernicus Atmosphere Data Store | `products`, `area`, `datasets`, `jobs`, `open` | none to read (an account's key to submit) |
| [`omnilab/earthdata`](https://github.com/glitchr-omnisense/omnilab-earthdata) | NASA Earthdata (CMR) | `products`, `area`, `cloud_cover`, `datasets`, `open` | none to search (an Earthdata Login token to download) |

### Knowledge graph

| Package | Service | Capabilities | Key |
|---|---|---|---|
| [`omnilab/wikidata`](https://github.com/glitchr-omnisense/omnilab-wikidata) | Wikidata | `entity`, `search`, `cross_references` | none (a User-Agent that names the caller; 200 calls a minute) |

Capabilities, in short ([every case](sources.md#capabilities)): `entity`, `search`, `series`,
`products`, `occurrences`, `datasets`, `jobs` and `open` are the operations a source answers;
`structure`, `cross_references`, `forecast`, `historical`, `area`, `station` and `cloud_cover` say
what its answers hold or which queries it takes. An operation a source does not answer throws
`NotSupportedException` before any call. `omnilab/elevation` answers none of the contract's
operations: its `at()` and `profile()` are methods of its own.

## How this manual is organised

- [Installation](installation.md): the packages, plain PHP first with a complete script and its
  real output, then the frameworks.
- [Symfony](symfony.md): the bundle, its configuration tree, the services it autowires.
- [Laravel](laravel.md): no bridge; the registry as a singleton in a service provider.
- [Models and identifiers](models.md): every contract, model, field, enum case and scheme.
- [Sources](sources.md): what each source can and cannot do, what the services do not allow,
  throttling and politeness.
- [Occurrences](occurrences.md), [Datasets, requests, files](datasets.md) and
  [Following cross-references, merging](merging.md): the usage guides.
- [Configuration reference](configuration.md): the registry's shape, the options every source
  takes, each source's own options with their defaults.
- [Errors](errors.md): every exception, when it is thrown, what to catch.
- [Testing](testing.md): testing an application that uses Omnilab, and the packages' own tests.
- [The Docker harness](harness.md): every source installed, a console that asks the real services.
- [Writing a provider](providers.md): a new `omnilab/<name>` package, step by step.
- [Verified, and not](verified.md): what the tests prove, what ran live and when, what did not.
- [Decisions](decisions.md): what the contract settled, and why.
