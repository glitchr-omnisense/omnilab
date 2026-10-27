---
title: Configuration reference
order: 40
---

# Configuration reference

## The registry's configuration

`new Registry(iterable $factories, array $config)`, and the bundle's `omnilab.sources`
([Symfony](symfony.md#the-configuration-tree)), take the same shape: a source's name, then the
name of its factory and its options.

```php
new Registry([new PubChemSourceFactory($http), new OpenMeteoSourceFactory($http)], [
    'pubchem' => ['factory' => 'pubchem', 'options' => ['mailto' => 'you@example.org']],
    'meteo' => ['factory' => 'open-meteo'],
    'air' => ['factory' => 'open-meteo', 'options' => ['api' => 'air_quality']],
]);
```

| Key | Type | Required | |
|---|---|---|---|
| the source's name | `string` | yes | what `get()`, the `Collector`'s `$sources`, the `Merger`'s preferences and `Entity::$source`... call it; the configured order is the order `names()` and `all()` give, and the `Collector` asks |
| `factory` | `string` | yes | a factory's `getName()`: `pubchem`, `open-meteo`, `materials-project`... ([below](#each-sources-own-options)), or an application's own |
| `options` | `array<string, mixed>` | no (`[]`) | handed to the factory's `create()` |

| `Registry` method | |
|---|---|
| `get(string $name): SourceInterface` | the source, built from its factory on the first call and kept; `InvalidConfigException` for a name not configured (`No "x" source; configured: pubchem, meteo.`), a factory not installed (`No "x" factory for the "y" source; installed: ...`), or an option it requires left empty |
| `has(string $name): bool` | configured or not (nothing is built) |
| `names(): list<string>` | the configured sources, in the configured order |
| `all(): array<string, SourceInterface>` | every configured source, built |
| `factories(): list<string>` | the factories given: what `factory` may name |
| `options(string $name): array` | the options a source is configured with, its factory's defaults not included |

## How a factory reads its options

Every `omnilab/*` factory extends `Source\SourceFactory`. `create($options)` makes a `Config` of
the options given, lets the factory set its defaults under them (`populate()`), then the four
options every source takes, checks the options the factory requires, and builds the source
(`build()`):

- an option given as `null` counts as not given: its default applies (`Config::defaults()`), so
  `'api_key' => getenv('X') ?: null` is safe;
- an option the factory requires (`omnilab.required_options`) must be neither missing, `null`,
  `''` nor `[]`: `InvalidConfigException` (`The "x" source needs: key.`); no factory of the 56
  requires one today: the sources that need a key refuse the calls that need it instead -
  iucn, and ebird beyond its taxonomy, with an `InvalidConfigException` before any call,
  materials-project with a `ProviderException` before any call, while openaq sends the call and
  the service's 401 is a `ProviderException` ([Errors](errors.md));
- an option the factory does not know is kept in the `Config` and ignored;
- `$factory->createConfig($options)` gives the `Config` without building the source: the defaults
  the factory would apply, for a test or a diagnostic (`->getArrayCopy()`).

The factory takes the HTTP client to call with (`new PubChemSourceFactory($http)`); with none
it makes its own (`HttpClient::create()`).

## The options every source takes

| Option | Type | Default | |
|---|---|---|---|
| `base_uri` | `string` | the service's address (each source's, below) | another instance (a self-hosted Open-Meteo, a mirror, a development server: `lldev.thespacedevs.com` for Launch Library 2) |
| `mailto` | `?string` | null | a contact address, sent in the User-Agent so that a service can write to the caller before it blocks them; NCBI's and ClinVar's E-utilities also send it as `email` |
| `user_agent` | `?string` | null | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` (`ncbi`, `clinvar`: `'auto'`) | each source's, below (`SourceFactory` sets 0.0 when the factory sets none) | seconds left between two calls of one source instance (`HttpSource` sleeps the rest); 0: none |

The User-Agent sent when `user_agent` is null is
`omnilab/1.x (+https://github.com/glitchr-omnisense/omnilab; mailto:you@example.org)`, without
the `mailto` part when there is none. Wikimedia refuses (403) a request whose User-Agent does not
say who calls: the default one names the project; `mailto` adds whom to reach.

The throttle counts per source instance and per process: two instances of one source (two
names, two workers) each keep their own interval. A service's limit is across all of them: the
application keeps to it ([Throttling and politeness](sources.md#throttling-and-politeness) gives
each service's).

## Each source's own options

The defaults each factory applies, read from `createConfig([])` of every factory on 2026-10-27
(the `mailto` and `user_agent` defaults are null for all). What each option does, its type and
the per-call options a source reads are on the source's own page.

| Factory | Its own options (default) | `throttle` | `base_uri` |
|---|---|---|---|
| [`brightsky`](https://github.com/glitchr-omnisense/omnilab-brightsky/blob/1.x/docs/index.md#options) | `max_distance` (null) | 0 | `https://api.brightsky.dev/` |
| [`cactus`](https://github.com/glitchr-omnisense/omnilab-cactus/blob/1.x/docs/index.md#options) | `representations` ([stdinchi, smiles, iupac_name, formula, mw, cas, names]), `names` (20), `opsin` (true) | 0 | `https://cactus.nci.nih.gov/chemical/structure/` |
| [`cams`](https://github.com/glitchr-omnisense/omnilab-cams/blob/1.x/docs/index.md#options) | `key` (null) | 0 | `https://ads.atmosphere.copernicus.eu/api/` |
| [`cds`](https://github.com/glitchr-omnisense/omnilab-cds/blob/1.x/docs/index.md#options) | `key` (null) | 0 | `https://cds.climate.copernicus.eu/api/` |
| [`chebi`](https://github.com/glitchr-omnisense/omnilab-chebi/blob/1.x/docs/index.md#options) | `synonyms` (20) | 0 | `https://www.ebi.ac.uk/chebi/backend/api/public/` |
| [`chembl`](https://github.com/glitchr-omnisense/omnilab-chembl/blob/1.x/docs/index.md#options) | `synonyms` (20), `unichem` (true), `mechanisms` (true), `unichem_uri` (https://www.ebi.ac.uk/unichem/api/v1/compounds) | 0 | `https://www.ebi.ac.uk/chembl/api/data/` |
| [`clinvar`](https://github.com/glitchr-omnisense/omnilab-clinvar/blob/1.x/docs/index.md#options) | `api_key` (null), `tool` (omnilab), `retries` (1) | auto | `https://eutils.ncbi.nlm.nih.gov/entrez/eutils/` |
| [`cod`](https://github.com/glitchr-omnisense/omnilab-cod/blob/1.x/docs/index.md#options) | - | 0.5 | `https://www.crystallography.net/cod/` |
| [`codata`](https://github.com/glitchr-omnisense/omnilab-codata/blob/1.x/docs/index.md#options) | - | 0 | `https://physics.nist.gov/` |
| [`col`](https://github.com/glitchr-omnisense/omnilab-col/blob/1.x/docs/index.md#options) | `dataset` (3LXR), `language` (eng) | 0 | `https://api.checklistbank.org/` |
| [`copernicus`](https://github.com/glitchr-omnisense/omnilab-copernicus/blob/1.x/docs/index.md#options) | `collections` ([sentinel-2-l2a]) | 0 | `https://stac.dataspace.copernicus.eu/v1/` |
| [`donki`](https://github.com/glitchr-omnisense/omnilab-donki/blob/1.x/docs/index.md#options) | - | 0 | `https://ccmc.gsfc.nasa.gov/DONKI-API/get/` |
| [`earthdata`](https://github.com/glitchr-omnisense/omnilab-earthdata/blob/1.x/docs/index.md#options) | `collections` ([]), `token` (null), `stac` (null) | 0 | `https://cmr.earthdata.nasa.gov/search/` |
| [`ebird`](https://github.com/glitchr-omnisense/omnilab-ebird/blob/1.x/docs/index.md#options) | `key` (null), `locale` (en), `radius_km` (25) | 0 | `https://api.ebird.org/v2/` |
| [`elevation`](https://github.com/glitchr-omnisense/omnilab-elevation/blob/1.x/docs/index.md#options) | `dataset` (srtm30m), `interpolation` (bilinear) | 1 | `https://api.opentopodata.org/v1/` |
| [`emsc`](https://github.com/glitchr-omnisense/omnilab-emsc/blob/1.x/docs/index.md#options) | `radius_km` (100), `min_magnitude` (null) | 0 | `https://www.seismicportal.eu/fdsnws/event/1/` |
| [`ensembl`](https://github.com/glitchr-omnisense/omnilab-ensembl/blob/1.x/docs/index.md#options) | `species` (homo_sapiens), `xrefs` (true) | 0.07 | `https://rest.ensembl.org/` |
| [`exoplanets`](https://github.com/glitchr-omnisense/omnilab-exoplanets/blob/1.x/docs/index.md#options) | - | 0 | `https://exoplanetarchive.ipac.caltech.edu/` |
| [`gaia`](https://github.com/glitchr-omnisense/omnilab-gaia/blob/1.x/docs/index.md#options) | - | 0 | `https://gea.esac.esa.int/tap-server/tap/` |
| [`gbif`](https://github.com/glitchr-omnisense/omnilab-gbif/blob/1.x/docs/index.md#options) | `ncbi` (true), `language` (eng), `radius_km` (10), `checklist` (7ddf754f-d193-4cc9-b351-99906754a03b) | 0 | `https://api.gbif.org/` |
| [`gwas`](https://github.com/glitchr-omnisense/omnilab-gwas/blob/1.x/docs/index.md#options) | `associations` (10) | 0 | `https://www.ebi.ac.uk/gwas/rest/api/v2/` |
| [`hgnc`](https://github.com/glitchr-omnisense/omnilab-hgnc/blob/1.x/docs/index.md#options) | - | 0.1 | `https://rest.genenames.org/` |
| [`horizons`](https://github.com/glitchr-omnisense/omnilab-horizons/blob/1.x/docs/index.md#options) | - | 0.2 | `https://ssd.jpl.nasa.gov/api/` |
| [`inaturalist`](https://github.com/glitchr-omnisense/omnilab-inaturalist/blob/1.x/docs/index.md#options) | `locale` (en), `quality_grade` (research), `radius_km` (10) | 1 | `https://api.inaturalist.org/` |
| [`interpro`](https://github.com/glitchr-omnisense/omnilab-interpro/blob/1.x/docs/index.md#options) | - | 0 | `https://www.ebi.ac.uk/interpro/api/` |
| [`itis`](https://github.com/glitchr-omnisense/omnilab-itis/blob/1.x/docs/index.md#options) | `hierarchy` (true), `language` (English) | 0 | `https://www.itis.gov/ITISWebService/jsonservice/` |
| [`iucn`](https://github.com/glitchr-omnisense/omnilab-iucn/blob/1.x/docs/index.md#options) | `key` (null), `assessment` (true), `language` (eng) | 0.5 | `https://api.iucnredlist.org/api/v4/` |
| [`jpl`](https://github.com/glitchr-omnisense/omnilab-jpl/blob/1.x/docs/index.md#options) | `synonyms` (20), `now` (null) | 0.2 | `https://ssd-api.jpl.nasa.gov/` |
| [`kegg`](https://github.com/glitchr-omnisense/omnilab-kegg/blob/1.x/docs/index.md#options) | `databases` ([compound]) | 0.34 | `https://rest.kegg.jp/` |
| [`launches`](https://github.com/glitchr-omnisense/omnilab-launches/blob/1.x/docs/index.md#options) | `api_key` (null) | 0 | `https://ll.thespacedevs.com/2.3.0/` |
| [`materials-project`](https://github.com/glitchr-omnisense/omnilab-materials-project/blob/1.x/docs/index.md#options) | `api_key` (null) | 0 | `https://api.materialsproject.org/` |
| [`mpc`](https://github.com/glitchr-omnisense/omnilab-mpc/blob/1.x/docs/index.md#options) | - | 0 | `https://data.minorplanetcenter.net/api/` |
| [`ncbi`](https://github.com/glitchr-omnisense/omnilab-ncbi/blob/1.x/docs/index.md#options) | `api_key` (null), `tool` (omnilab), `links` (true), `synonyms` (20), `eutils_uri` (https://eutils.ncbi.nlm.nih.gov/entrez/eutils/) | auto | `https://api.ncbi.nlm.nih.gov/datasets/v2/` |
| [`ned`](https://github.com/glitchr-omnisense/omnilab-ned/blob/1.x/docs/index.md#options) | - | 0 | `https://ned.ipac.caltech.edu/` |
| [`noaa`](https://github.com/glitchr-omnisense/omnilab-noaa/blob/1.x/docs/index.md#options) | `api` (ncei), `radius_km` (50), `timezone` (null), `now` (null) | 0 | null: by `api`, NCEI's or the NWS's |
| [`obis`](https://github.com/glitchr-omnisense/omnilab-obis/blob/1.x/docs/index.md#options) | `statistics` (true), `radius_km` (10) | 0 | `https://api.obis.org/v3/` |
| [`open-meteo`](https://github.com/glitchr-omnisense/omnilab-open-meteo/blob/1.x/docs/index.md#options) | `api` (weather), `api_key` (null), `timezone` (auto), `models` (null), `archive_delay` (5), `today` (null), `archive_uri` (https://archive-api.open-meteo.com/v1/) | 0 | `https://api.open-meteo.com/v1/` |
| [`openaq`](https://github.com/glitchr-omnisense/omnilab-openaq/blob/1.x/docs/index.md#options) | `api_key` (null), `radius_km` (10) | 1 | `https://api.openaq.org/v3/` |
| [`opentargets`](https://github.com/glitchr-omnisense/omnilab-opentargets/blob/1.x/docs/index.md#options) | `associations` (10) | 0 | `https://api.platform.opentargets.org/api/v4/` |
| [`oqmd`](https://github.com/glitchr-omnisense/omnilab-oqmd/blob/1.x/docs/index.md#options) | - | 0 | `https://oqmd.org/oqmdapi/` |
| [`pbdb`](https://github.com/glitchr-omnisense/omnilab-pbdb/blob/1.x/docs/index.md#options) | `radius_km` (10) | 0 | `https://paleobiodb.org/data1.2/` |
| [`pdb`](https://github.com/glitchr-omnisense/omnilab-pdb/blob/1.x/docs/index.md#options) | `search_uri` (https://search.rcsb.org/rcsbsearch/v2/query) | 0.25 | `https://data.rcsb.org/` |
| [`pubchem`](https://github.com/glitchr-omnisense/omnilab-pubchem/blob/1.x/docs/index.md#options) | `synonyms` (20), `description` (true) | 0.2 | `https://pubchem.ncbi.nlm.nih.gov/rest/pug/` |
| [`quickgo`](https://github.com/glitchr-omnisense/omnilab-quickgo/blob/1.x/docs/index.md#options) | `annotation_pages` (5) | 0 | `https://www.ebi.ac.uk/QuickGO/services/` |
| [`reactome`](https://github.com/glitchr-omnisense/omnilab-reactome/blob/1.x/docs/index.md#options) | `species` (Homo sapiens), `participants` (true) | 0 | `https://reactome.org/ContentService/` |
| [`rhea`](https://github.com/glitchr-omnisense/omnilab-rhea/blob/1.x/docs/index.md#options) | `enzymes` (25), `sparql_uri` (https://sparql.rhea-db.org/sparql), `uniprot_uri` (https://rest.uniprot.org/) | 0 | `https://www.rhea-db.org/` |
| [`simbad`](https://github.com/glitchr-omnisense/omnilab-simbad/blob/1.x/docs/index.md#options) | `synonyms` (50) | 0.2 | `https://simbad.cds.unistra.fr/simbad/sim-tap/` |
| [`string`](https://github.com/glitchr-omnisense/omnilab-string/blob/1.x/docs/index.md#options) | `caller_identity` (omnilab), `species` (9606), `partners` (10), `required_score` (400) | 1 | `https://string-db.org/api/json/` |
| [`swpc`](https://github.com/glitchr-omnisense/omnilab-swpc/blob/1.x/docs/index.md#options) | - | 0 | `https://services.swpc.noaa.gov/` |
| [`tides`](https://github.com/glitchr-omnisense/omnilab-tides/blob/1.x/docs/index.md#options) | `datum` (MLLW), `radius_km` (50), `application` (omnilab), `metadata_uri` (https://api.tidesandcurrents.noaa.gov/mdapi/prod/webapi/) | 0.5 | `https://api.tidesandcurrents.noaa.gov/api/prod/datagetter` |
| [`uniprot`](https://github.com/glitchr-omnisense/omnilab-uniprot/blob/1.x/docs/index.md#options) | `sequence` (2000), `structures` (20) | 0 | `https://rest.uniprot.org/uniprotkb/` |
| [`usgs`](https://github.com/glitchr-omnisense/omnilab-usgs/blob/1.x/docs/index.md#options) | `radius_km` (100), `min_magnitude` (null) | 0 | `https://earthquake.usgs.gov/fdsnws/event/1/` |
| [`usgs-water`](https://github.com/glitchr-omnisense/omnilab-usgs-water/blob/1.x/docs/index.md#options) | `api_key` (null), `radius_km` (25) | 0 | `https://api.waterdata.usgs.gov/ogcapi/v1/` |
| [`vizier`](https://github.com/glitchr-omnisense/omnilab-vizier/blob/1.x/docs/index.md#options) | - | 0 | `https://tapvizier.cds.unistra.fr/TAPVizieR/tap/` |
| [`wikidata`](https://github.com/glitchr-omnisense/omnilab-wikidata/blob/1.x/docs/index.md#options) | `language` (en), `synonyms` (20) | 0.3 | `https://www.wikidata.org/w/api.php` |
| [`worms`](https://github.com/glitchr-omnisense/omnilab-worms/blob/1.x/docs/index.md#options) | `classification` (true), `synonyms` (true), `vernaculars` (true), `external_ids` ([tsn, ncbi, iucn]), `language` (eng), `marine_only` (false) | 0 | `https://www.marinespecies.org/rest/` |

## The bundle

[Symfony](symfony.md#the-configuration-tree): `omnilab.sources` is the registry's configuration
above; `omnilab.merger.preferences` the `Merger`'s.

## The Merger's preferences

`new Merger(array $preferences = [])`: for a field (`label`, `kind`, `description`, `url`,
`image`, `time`, `place`) or a property key (`molecular_weight`), the source names in the order
they are trusted; `default` orders every field and key not listed. A source not listed comes after
those listed, in the order the entities were given. Every property is kept whatever the
preferences: they decide which comes first (`property()`), not which survive (`properties()`).

```php
new Merger(['default' => ['pubchem', 'chebi'], 'description' => ['chebi'], 'molecular_weight' => ['pubchem']]);
```

See [the Merger](merging.md#the-merger).

## The harness's environment

The harness configures every source from `docker/.env` (`docker/harness/config/sources.php`):
`OMNILAB_MAILTO` for every `mailto`; `WIKIDATA_LANGUAGE` (wikidata's `language`),
`INATURALIST_LOCALE` (inaturalist's `locale`); `NCBI_API_KEY` (ncbi's and clinvar's
`api_key`), `OPEN_METEO_API_KEY` and `OPEN_METEO_BASE_URI`, `CDS_KEY` (cds's `key`), `ADS_KEY`
(cams's `key`), `EARTHDATA_TOKEN`, `USGS_WATER_API_KEY`, `EBIRD_API_KEY` (ebird's `key`),
`LL2_API_KEY` (launches' `api_key`); and three sources only when their key is set:
`materials-project` (`MP_API_KEY`), `openaq` (`OPENAQ_API_KEY`), `iucn` (`IUCN_API_KEY`). It
configures `open-meteo` twice (`air-quality`: `api: air_quality`) and `noaa` twice (`nws`: `api:
nws`). `docker/.env.dist` lists every variable but `MP_API_KEY` and `LL2_API_KEY`.
