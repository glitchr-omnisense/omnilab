---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Every package's tests run on recorded answers, with no network: 781 tests, 6195 assertions, all
green on 2026-10-27 (the workspace `~/Sites/omnilab` mounted read-only in the
`omniscient/omnilab-harness` image, `--network none`). The core's 245 prove the contract itself:
the identifiers of every scheme, the error mapping of `HttpSource`, the models and queries, the
`Registry`, the `Collector` and the `Merger` on stub sources, the STAC reader, plain PHP without a
framework, and the Symfony bundle in a booted kernel ([Testing](testing.md#the-packages-own-tests)).

A source's tests prove that it reads the answers it was given: each fixture was recorded from the
service on a stated day (some cut down), or, where no live answer could be had, built after the
service's documentation or schema - those packages say so, and say which.

## What ran against the services

| | |
|---|---|
| 2026-10-14 to 2026-10-16 | every source but those below, while it was written: the calls of its page, the answers its fixtures keep; each page's "Verified, and not" says what and when |
| 2026-10-15 | the Collector from PubChem into ChEBI and Wikidata, the installation's scripts ([Installation](installation.md)), the error bodies of each service ([Sources](sources.md#errors)) |
| 2026-10-27 | the installation's plain PHP script again (PubChem, Open-Meteo, the Copernicus Data Space), in the harness image ([its output](installation.md#plain-php)) |

The harness keeps twelve answers recorded live (`docker/harness/recorded/`: ChEBI, the Copernicus
Data Space, Open-Meteo, PubChem, the USGS, Wikidata) that `bare` and the core's `BareTest` replay.

## What did not

- **Keys never used**: OpenAQ (every call), the IUCN Red List (every call), the Materials Project
  (every call), eBird (all but its taxonomy), the Copernicus data stores' requests (`submit()`,
  `job()`, `jobs()` of cds and cams), NASA Earthdata's files (`open()` with a token). Their tests
  answer bodies built from the services' documentation, schemas or official clients.
- **Services not answering or not open to this use**: OQMD (down on 2026-10-15: fixtures from its
  documentation), KEGG (academic use by academic users only: a handful of calls to learn its
  format, no use beyond).
- **The Laravel provider** of [Laravel](laravel.md): plain construction, not run in a Laravel
  application.
- **Rates**: no source was run at the limit its service publishes; the throttles are the published
  rates, not measured ones.

## By package

| Package | Tests | Assertions | Live |
|---|---|---|---|
| `omnilab/brightsky` | 10 | 42 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/cactus` | 6 | 65 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/cams` | 4 | 31 | its catalogue live on 2026-10-15, without an account; `submit()`, `job()`, `jobs()` not live (a key) |
| `omnilab/cds` | 11 | 90 | its catalogue live on 2026-10-15, without an account; `submit()`, `job()`, `jobs()` not live (a key) |
| `omnilab/chebi` | 11 | 75 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/chembl` | 15 | 80 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/clinvar` | 9 | 120 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/cod` | 6 | 85 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/codata` | 5 | 756 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/col` | 8 | 32 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/copernicus` | 4 | 32 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/donki` | 7 | 71 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/earthdata` | 11 | 78 | its search live on 2026-10-15, without an account; `open()` with a token not live |
| `omnilab/ebird` | 8 | 31 | its taxonomy live on 2026-10-16; everything that needs a key not tested live (constructed answers) |
| `omnilab/elevation` | 7 | 24 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/emsc` | 13 | 62 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/ensembl` | 14 | 69 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/exoplanets` | 14 | 221 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/gaia` | 9 | 139 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/gbif` | 15 | 96 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/gwas` | 10 | 96 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/hgnc` | 8 | 85 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/horizons` | 11 | 102 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/inaturalist` | 13 | 66 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/interpro` | 9 | 106 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/itis` | 5 | 27 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/iucn` | 6 | 34 | not tested live: a token is required, none used (the 403 without one, met on 2026-10-16); tests on answers of 2024-09-18 |
| `omnilab/jpl` | 11 | 80 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/kegg` | 10 | 124 | not tested live: academic use by academic users only; a handful of calls on 2026-10-15 to learn the format |
| `omnilab/launches` | 7 | 70 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/materials-project` | 3 | 38 | not tested live: a key is required, none created (the 401 without one, met on 2026-10-15); fixtures built on its OpenAPI schema, numbers placeholders |
| `omnilab/mpc` | 10 | 90 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/ncbi` | 10 | 63 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/ned` | 7 | 76 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/noaa` | 13 | 105 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/obis` | 10 | 262 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/open-meteo` | 9 | 67 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/openaq` | 7 | 25 | not tested live: a key is required, none created (the 401 without one, met on 2026-10-15) |
| `omnilab/opentargets` | 9 | 136 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/oqmd` | 3 | 31 | not tested live: the service did not answer on 2026-10-15; fixtures from its documentation |
| `omnilab/pbdb` | 9 | 358 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/pdb` | 10 | 49 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/pubchem` | 10 | 72 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/quickgo` | 11 | 116 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/reactome` | 12 | 120 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/rhea` | 5 | 57 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/simbad` | 10 | 103 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/string` | 10 | 109 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/swpc` | 9 | 42 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/tides` | 10 | 51 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/uniprot` | 12 | 69 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/usgs` | 17 | 111 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/usgs-water` | 12 | 59 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/vizier` | 6 | 58 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/wikidata` | 28 | 132 | live, on the days its page states (2026-10-14 to 2026-10-16) |
| `omnilab/worms` | 7 | 41 | live, on the days its page states (2026-10-14 to 2026-10-16) |

The core: 245 tests, 766 assertions.

The defects found while writing this manual are said where the behaviour is documented
(`Known defect` callouts): the `mb_*` requirement left undeclared
([Installation](installation.md#requirements)), the bundle's normalised source names
([Symfony](symfony.md#the-configuration-tree)), `Scheme::LAUNCH_LIBRARY->domain()`
([Models](models.md#the-methods-of-scheme)), omnilab/emsc's `\UnexpectedValueException`
([Errors](errors.md#what-the-sources-add)).
