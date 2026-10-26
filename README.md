# omnisense/omnilab

One contract for the public science-data APIs, whatever the domain - chemistry, biology, species,
astronomy, the Earth, its weather and its air: what science knows of a thing, what it measures at a
place, what it has catalogued over an area, what was found or happened there. Publications are not here: they are
[glitchr/omnischolar](https://github.com/glitchr-omnisense/omnischolar)'s.

Published as `omnisense/omnilab` (formerly `glitchr/omnilab`, which this package replaces: a
`composer.json` that still requires the old name keeps resolving, see [decisions](docs/decisions.md)).

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
| [`omnilab/pubchem`](https://github.com/glitchr-omnisense/omnilab-pubchem) | PubChem (NCBI): compounds by CID, name, InChIKey, CAS, SMILES; computed properties, synonyms, 2D structure, cross-references | entity | none (5 calls a second at most) |
| [`omnilab/open-meteo`](https://github.com/glitchr-omnisense/omnilab-open-meteo) | Open-Meteo: hourly, 15-minute and daily weather at a point, forecast and archive (from 1940); the air quality (PM2.5, PM10, NO2, O3, the European and US indices, pollens), hourly or daily, from 2013 | series | none for non-commercial use |
| [`omnilab/copernicus`](https://github.com/glitchr-omnisense/omnilab-copernicus) | Copernicus Data Space: the STAC catalogue of Sentinel, Landsat, MODIS, the DEM and the Copernicus services | product | none to search (a CDSE token to download) |
| [`omnilab/chebi`](https://github.com/glitchr-omnisense/omnilab-chebi) | ChEBI (EMBL-EBI): chemical entities by ChEBI id, InChIKey, CAS, KEGG or name; masses, structure, ontology (classes, roles), cross-references | entity | none |
| [`omnilab/wikidata`](https://github.com/glitchr-omnisense/omnilab-wikidata) | Wikidata: any item by Q-number, by a science base's identifier (PubChem, ChEBI, CAS, UniProt, NCBI...) or by label; labels in a language, cross-references, a few properties with units | entity | none (a User-Agent that names the caller; 200 calls a minute) |
| [`omnilab/chembl`](https://github.com/glitchr-omnisense/omnilab-chembl) | ChEMBL (EMBL-EBI): bioactive molecules by ChEMBL id, InChIKey or name; development phase, computed properties, mechanisms of action and their targets, cross-references from UniChem | entity | none |
| [`omnilab/uniprot`](https://github.com/glitchr-omnisense/omnilab-uniprot) | UniProtKB: proteins by accession or entry name; function, gene, organism, sequence, mass, cofactors, ligands; their genes in NCBI and Ensembl | entity | none |
| [`omnilab/gbif`](https://github.com/glitchr-omnisense/omnilab-gbif) | GBIF's taxonomy (the Catalogue of Life eXtended Release since 2026): taxa by key, legacy Backbone number, NCBI id or scientific name; status, IUCN category, lineage; NCBI cross-reference; the occurrences of a place and a period (by taxon, country, basis of record) | entity, occurrences | none |
| [`omnilab/ncbi`](https://github.com/glitchr-omnisense/omnilab-ncbi) | NCBI Gene and Taxonomy (Datasets v2, E-utilities): genes by GeneID, taxa by id or name; location, summary, lineage; UniProt, Ensembl and GBIF ids | entity | none (3 calls a second; 10 with an API key) |
| [`omnilab/simbad`](https://github.com/glitchr-omnisense/omnilab-simbad) | SIMBAD (CDS, Strasbourg), through its TAP service: objects beyond the solar system by any identifier; type, coordinates, motion, parallax, velocity, redshift, spectral type, magnitudes | entity | none |
| [`omnilab/jpl`](https://github.com/glitchr-omnisense/omnilab-jpl) | NASA/JPL's Small-Body Database: asteroids and comets by number, designation, name or SPK-ID; orbit, physical parameters, NEO and PHA flags, close approaches, Sentry's impact risk | entity | none |
| [`omnilab/usgs`](https://github.com/glitchr-omnisense/omnilab-usgs) | USGS earthquake catalogue (ComCat, FDSN event service): an earthquake by its id, with its time and place; the earthquakes of a box or around a point as series, or as occurrences | entity, series, occurrences | none |
| [`omnilab/emsc`](https://github.com/glitchr-omnisense/omnilab-emsc) | The EMSC's earthquake catalogue (SeismicPortal, FDSN event service): an earthquake by its SeismicPortal id, with its time, place, Flinn-Engdahl region; the earthquakes of a box or around a point as series, or as occurrences | entity, series, occurrences | none |
| [`omnilab/usgs-water`](https://github.com/glitchr-omnisense/omnilab-usgs-water) | The USGS's Water Data OGC APIs (which replace the WaterServices): a monitoring location's discharge, gage height, water temperature or any parameter as recorded, hourly or daily, at a `usgs.site` or the nearest of a point; the location itself; locations by name | entity, search, series | none (an api.data.gov key raises the limit) |
| [`omnilab/tides`](https://github.com/glitchr-omnisense/omnilab-tides) | NOAA CO-OPS (Tides and Currents): a station's water level every six minutes, verified hourly heights and highs and lows, tide predictions, water and air temperature, at a `coops.station` or the nearest of a point; the station itself | entity, series | none |
| [`omnilab/swpc`](https://github.com/glitchr-omnisense/omnilab-swpc) | NOAA's Space Weather Prediction Center: the aurora probability at a point (OVATION) as a series; outside the contract, the Kp index, the solar wind at L1, the alerts and the NOAA scales | series | none |
| [`omnilab/brightsky`](https://github.com/glitchr-omnisense/omnilab-brightsky) | The Deutscher Wetterdienst's open data through Bright Sky: hourly observations since 2010 joined to the MOSMIX forecasts, at a point or a `dwd.station` / WMO station; the station itself | entity, series | none |
| [`omnilab/elevation`](https://github.com/glitchr-omnisense/omnilab-elevation) | Open Topo Data: the elevation of points and profiles from SRTM, ASTER, EU-DEM, GEBCO... - outside the contract (`at()`, `profile()`) | - | none (100 locations a call, 1,000 calls a day) |
| [`omnilab/openaq`](https://github.com/glitchr-omnisense/omnilab-openaq) | OpenAQ (API v3): the hourly or daily averages of a pollutant at the nearest location of a point measuring it. Not tested live | series | a key (required) |
| [`omnilab/obis`](https://github.com/glitchr-omnisense/omnilab-obis) | OBIS, the Ocean Biodiversity Information System: a marine taxon by its WoRMS AphiaID or name, its record counts, NCBI id; the records of a box or around a point, by taxon, depth, dataset, each with its depth and the sea surface temperature and salinity at its place | entity, occurrences | none |
| [`omnilab/worms`](https://github.com/glitchr-omnisense/omnilab-worms) | WoRMS, the World Register of Marine Species: a taxon by its AphiaID, its name, or its ITIS, NCBI or IUCN id; accepted name, status, classification, synonyms, vernacular names, the citation it asks for; ITIS, NCBI and IUCN ids as cross-references | entity, search | none |
| [`omnilab/itis`](https://github.com/glitchr-omnisense/omnilab-itis) | ITIS: a taxon by its TSN or name; usage, accepted name, hierarchy, common names, geographic divisions, jurisdictional origins; a search of names | entity, search | none |
| [`omnilab/col`](https://github.com/glitchr-omnisense/omnilab-col) | The Catalogue of Life through ChecklistBank: a taxon or synonym by its COL id, its GBIF key (the same id since June 2026) or its name; classification, vernaculars, distribution, scrutinizer, source record; GBIF, iNaturalist, WoRMS and ITIS ids as cross-references | entity, search | none |
| [`omnilab/pbdb`](https://github.com/glitchr-omnisense/omnilab-pbdb) | The Paleobiology Database: a fossil or living taxon by number or name, its first and last appearances in Ma, ecology; a fossil by its number; the fossils of a place and an age outside the contract (`fossils()`: an age is no date) | entity, search | none |
| [`omnilab/ebird`](https://github.com/glitchr-omnisense/omnilab-ebird) | eBird API 2.0: a bird taxon by its species code; with the user's key, by name, a search, a checklist, the recent observations around a point or in a region. Not tested live beyond the taxonomy | entity, search, occurrences | a key (the user's) for all but the taxonomy |
| [`omnilab/iucn`](https://github.com/glitchr-omnisense/omnilab-iucn) | The IUCN Red List API v4: a taxon by SIS id or name with its latest global assessment (category, criteria, trend, citation). Not tested live | entity | a token (the user's, required) |
| [`omnilab/horizons`](https://github.com/glitchr-omnisense/omnilab-horizons) | NASA/JPL Horizons: the ephemeris of a planet, moon, asteroid, comet or spacecraft seen from a place on the Earth over a period - right ascension and declination, azimuth and elevation, magnitude, illumination, distances to the observer and the Sun, elongation - each variable naming its body (`499:elevation`) | series | none (one request at a time) |
| [`omnilab/mpc`](https://github.com/glitchr-omnisense/omnilab-mpc) | The IAU's Minor Planet Center: minor planets and comets by number, designation or name; designations, naming citation, orbit with uncertainties, absolute magnitude, observations and arc; their latest observations (outside the contract) | entity | none |
| [`omnilab/ned`](https://github.com/glitchr-omnisense/omnilab-ned) | NED, the NASA/IPAC Extragalactic Database: galaxies, quasars, clusters by any name; type, position, redshift and its quality, the number of distances, photometry and spectra NED holds; a cone on the sky (outside the contract) | entity | none |
| [`omnilab/vizier`](https://github.com/glitchr-omnisense/omnilab-vizier) | VizieR (CDS): the tables of published astronomical catalogues by text, as datasets; the rows of a table around a point of the sky or an object, with units (outside the contract) | datasets | none |
| [`omnilab/donki`](https://github.com/glitchr-omnisense/omnilab-donki) | NASA's DONKI (CCMC): space weather events - solar flares, coronal mass ejections, geomagnetic storms, shocks, SEP events - by activity id, with their links; every event of a period (outside the contract: no place on the Earth) | entity | none |
| [`omnilab/launches`](https://github.com/glitchr-omnisense/omnilab-launches) | The Space Devs' Launch Library 2: orbital launches by id or text - rocket, provider, mission, orbit, pad, status -, and the launches of a period as occurrences at their pads | entity, occurrences | none (15 calls an hour; a supporter's key) |
| [`omnilab/ensembl`](https://github.com/glitchr-omnisense/omnilab-ensembl) | Ensembl (EMBL-EBI): genes, transcripts, proteins and exons by Ensembl id, genes by symbol in a species; biotype, species, assembly, place on the genome; NCBI Gene and UniProt cross-references | entity | none (15 calls a second) |
| [`omnilab/pdb`](https://github.com/glitchr-omnisense/omnilab-pdb) | RCSB PDB (GraphQL Data API, Search API): structures by PDB id; method, resolution, citation, chains and ligands; their UniProt proteins, organisms and ligands as related things; full-text search | entity | none (a handful of calls a second) |
| [`omnilab/reactome`](https://github.com/glitchr-omnisense/omnilab-reactome) | Reactome's Content Service: pathways and reactions by stable id (`R-HSA-70171`); species, compartments, GO process, inputs and outputs, Rhea; their UniProt proteins, ChEBI molecules, organism, sub-events and orthologous events as related things; a search; the pathways of a protein or a molecule | entity | none |
| [`omnilab/string`](https://github.com/glitchr-omnisense/omnilab-string) | STRING: a protein by its STRING id (`9606.ENSP00000269305`), UniProt accession, Ensembl protein or name; its preferred name, annotation, species, Ensembl protein; its interaction partners with their scores as related things; the network among proteins, each evidence channel's score; their functional enrichment | entity | none (one call a second, `caller_identity`) |
| [`omnilab/interpro`](https://github.com/glitchr-omnisense/omnilab-interpro) | InterPro (EMBL-EBI): InterPro entries (`IPR000971`) and Pfam families (`PF00042`); type, description, member signatures, GO terms, counts, clan, hierarchy; a UniProt protein's domains and families by type, with their positions, as related things; a search | entity | none |
| [`omnilab/quickgo`](https://github.com/glitchr-omnisense/omnilab-quickgo) | The Gene Ontology through QuickGO (EMBL-EBI): terms by id (`GO:0005829`); name, definition, aspect, synonyms, direct parents by relation, children; a UniProt protein's GO terms by aspect with their evidence codes; the annotations themselves; a search | entity | none |
| [`omnilab/hgnc`](https://github.com/glitchr-omnisense/omnilab-hgnc) | HGNC: human genes by HGNC id (`HGNC:4827`), symbol, previous symbol or alias, or an id it records; approved name, locus type, location, groups, MANE Select, RefSeq; NCBI Gene, Ensembl, UniProt and OMIM cross-references; a search | entity | none (10 calls a second) |
| [`omnilab/opentargets`](https://github.com/glitchr-omnisense/omnilab-opentargets) | The Open Targets Platform (GraphQL): targets by Ensembl gene, diseases by EFO, Mondo, HPO or Orphanet id (`MONDO_0011382`), drugs by ChEMBL id; their associations with scores, drugs and clinical candidates, domains, pathways, mechanisms and indications as related things; a search; the associations paged | entity | none |
| [`omnilab/clinvar`](https://github.com/glitchr-omnisense/omnilab-clinvar) | ClinVar (NCBI E-utilities): variations by VariationID or VCV accession (`VCV000015333`); classification, review status, conditions, genes, protein change, location, SPDI, allele frequencies; its dbSNP id; its genes and conditions (Mondo, OMIM, Orphanet, HPO) as related things; a search in ClinVar's query language | entity | none (3 calls a second; 10 with an API key) |
| [`omnilab/gwas`](https://github.com/glitchr-omnisense/omnilab-gwas) | The NHGRI-EBI GWAS Catalog (REST API v2): studies (`GCST000854`), variants (`rs334`) and traits (EFO, Mondo, HPO) with their strongest associations (p-values, effect sizes, risk alleles); the studies, variants and traits they tie as related things; a search of traits; the associations paged | entity | none |
| [`omnilab/noaa`](https://github.com/glitchr-omnisense/omnilab-noaa) | NOAA: NCEI's station records (GHCN-Daily, monthly and yearly summaries) at the nearest station or one named (`ghcn:USW00094728`); the National Weather Service's observations and forecasts in the United States, at a point or a station (`nws.station:KNYC`); the stations themselves | series, entity (stations) | none (a User-Agent for the NWS) |
| [`omnilab/cds`](https://github.com/glitchr-omnisense/omnilab-cds) | Copernicus Climate Data Store: ERA5, seasonal forecasts, projections... as products over a place and a period, the catalogue's search; a request submitted and followed to its file | product | none to read (an account's key to submit) |
| [`omnilab/cams`](https://github.com/glitchr-omnisense/omnilab-cams) | Copernicus Atmosphere Data Store (CAMS): air quality forecasts and reanalyses, atmospheric composition, greenhouse gases, through omnilab/cds | product | none to read (an account's key to submit) |
| [`omnilab/exoplanets`](https://github.com/glitchr-omnisense/omnilab-exoplanets) | The NASA Exoplanet Archive (Caltech/IPAC), through its TAP service: confirmed planets by name, their mass, radius, orbit, temperature and discovery, their host star related by its designations | entity | none |
| [`omnilab/gaia`](https://github.com/glitchr-omnisense/omnilab-gaia) | ESA's Gaia DR3, through the Gaia Archive's TAP service: a source by its source_id or designation, its astrometry, photometry, radial velocity and astrophysical parameters | entity | none |
| [`omnilab/earthdata`](https://github.com/glitchr-omnisense/omnilab-earthdata) | NASA Earthdata's Common Metadata Repository: the granules of HLS, MODIS, VIIRS, GPM... by place, period and cloud cover, their files | product | none to search (an Earthdata Login token to download) |
| [`omnilab/inaturalist`](https://github.com/glitchr-omnisense/omnilab-inaturalist) | iNaturalist: taxa by id or name; common name, IUCN category, lineage, default photo and its licence; the observations of a place and a period, research grade by default, each with its licence | entity, occurrences | none (60 calls a minute) |
| [`omnilab/kegg`](https://github.com/glitchr-omnisense/omnilab-kegg) | KEGG's REST API (Kanehisa Laboratories): compounds, drugs, reactions, pathway maps and enzymes by KEGG id (`C07481`, `R00200`, `map00010`, `2.7.1.40`); formula, masses, equations, classes; CAS, ChEBI, ChEMBL, Rhea ids; reactions, pathways, enzymes, compounds as related things; a search. **For academic use by academic users only; not tested live** | entity | none (academic use only; 3 calls a second) |
| [`omnilab/rhea`](https://github.com/glitchr-omnisense/omnilab-rhea) | Rhea (SIB), through its SPARQL endpoint and REST search: biochemical reactions by any of their four ids (`RHEA:18157`) or their KEGG reaction; equation, status, sides and participants; the quartet and KEGG reaction as identifiers; ChEBI participants, EC numbers, GO term, Reactome events, the reviewed UniProt proteins that catalyse them as related things; a search | entity | none |
| [`omnilab/cod`](https://github.com/glitchr-omnisense/omnilab-cod) | The Crystallography Open Database: crystal structures by COD id (`9008565`); formula, unit cell with uncertainties, space group, Z, temperature, R factors, publication and DOI; the entry it duplicates related; the CIF as a file (`cif()`, `open()`); a search of the entries' metadata | entity | none (CC0) |
| [`omnilab/cactus`](https://github.com/glitchr-omnisense/omnilab-cactus) | The NCI/CADD Chemical Identifier Resolver (CIR): a structure from a name, a CAS number, an InChIKey, an InChI or a SMILES; its InChIKey (its id), InChI, SMILES, IUPAC name, formula, weight, CAS numbers, names, picture; OPSIN (the IUPAC name parser) for a name CIR cannot read | entity | none |
| [`omnilab/codata`](https://github.com/glitchr-omnisense/omnilab-codata) | The fundamental physical constants of CODATA as NIST publishes them (2022 adjustment, 355 constants): each by NIST's short name (`mp`) or its name; its value, standard uncertainty and unit, exact or not, its digits as published; a search | entity | none |
| [`omnilab/materials-project`](https://github.com/glitchr-omnisense/omnilab-materials-project) | The Materials Project's API: computed properties of inorganic materials by id (`mp-149`); formula, symmetry, density, energies per atom, stability, band gap, magnetism, moduli; the materials of a formula or a chemical system. **An account's key required; not tested live** | entity | an account's API key (free) |
| [`omnilab/oqmd`](https://github.com/glitchr-omnisense/omnilab-oqmd) | The Open Quantum Materials Database's RESTful API: entries by OQMD id; formula, space group, prototype, volume, formation energy, hull distance, band gap, ICSD id; the entries of a composition or a phase space. **Not tested live: the service was down on 2026-10-15** | entity | none |

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
composer require omnisense/omnilab omnilab/pubchem omnilab/open-meteo omnilab/copernicus omnilab/chebi omnilab/wikidata
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

## Responsibility

`omnisense/omnilab` and its `omnilab/*` providers are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the services it reaches. Each service keeps its own
terms (licence of its data, attribution or citation, accounts and keys, rate limits, academic or
non-commercial restrictions), summed up in the "Terms of use" section of each provider's README.
The MIT licence covers this code only and grants no right to any service or to its data.

Whoever installs and runs these packages is responsible for complying with those terms, for
obtaining whatever they require, and for any use made of what they return. The authors publish
them free of charge, make no commercial use of them, and accept no liability for how they are
used (see [LICENSE](LICENSE)).

License: MIT.
