---
title: Installation and first calls
order: 10
---

# Installation and first calls

```sh
composer require omnisense/omnilab omnilab/pubchem omnilab/open-meteo omnilab/copernicus
composer require omnilab/chebi omnilab/wikidata   # the sources the Collector follows a compound into
composer require omnilab/uniprot omnilab/ncbi omnilab/gbif   # proteins, genes and taxa
composer require omnilab/chembl                              # bioactive molecules, their targets
composer require omnilab/simbad omnilab/jpl                  # stars and galaxies; asteroids and comets
composer require omnilab/gaia omnilab/exoplanets             # Gaia DR3's sources; the confirmed exoplanets
composer require omnilab/ned omnilab/vizier                # galaxies and quasars (NED); the CDS's catalogues (VizieR)
composer require omnilab/mpc omnilab/horizons               # minor planets and comets (MPC); ephemerides seen from a place (JPL Horizons)
composer require omnilab/donki omnilab/launches             # space weather events (NASA DONKI); orbital launches (Launch Library 2)
composer require omnilab/usgs omnilab/emsc                   # earthquakes: the USGS's catalogue, the EMSC's
composer require omnilab/ensembl omnilab/pdb                 # genomes; protein structures
composer require omnilab/reactome                            # pathways and reactions
composer require omnilab/hgnc omnilab/string omnilab/interpro omnilab/quickgo   # human genes; protein interactions, domains, GO
composer require omnilab/opentargets omnilab/clinvar omnilab/gwas               # targets and diseases; clinical variants; GWAS associations
composer require omnilab/inaturalist                         # taxa, and observations of species
composer require omnilab/col omnilab/itis omnilab/worms omnilab/obis   # the Catalogue of Life (GBIF's taxonomy); ITIS; marine taxa and their records
composer require omnilab/pbdb                                # fossils: taxa, their ages, the fossils of a place
composer require omnilab/ebird omnilab/iucn                  # birds (eBird: a key beyond the taxonomy); the IUCN Red List (a token)
composer require omnilab/noaa                                # weather stations' records (NCEI), the NWS
composer require omnilab/cds omnilab/cams omnilab/earthdata  # climate and atmosphere data stores; NASA's Earth observation
composer require omnilab/rhea omnilab/kegg                  # biochemical reactions; KEGG (academic use only)
composer require omnilab/cod omnilab/cactus omnilab/codata    # crystal structures; a structure from any identifier; physical constants
composer require omnilab/materials-project omnilab/oqmd       # computed materials (Materials Project: a key)
composer require omnilab/usgs-water omnilab/tides            # rivers and wells (USGS); tides and water levels (NOAA CO-OPS)
composer require omnilab/brightsky omnilab/openaq            # the DWD's weather (Bright Sky); air quality measurements (OpenAQ: a key)
composer require omnilab/swpc omnilab/elevation              # space weather (NOAA SWPC); elevations (Open Topo Data)
```

The weather and the air are both `omnilab/open-meteo` (`api: air_quality` for the air); the
occurrences of a species are `omnilab/gbif`'s, `omnilab/inaturalist`'s (its observations), `omnilab/obis`'s (in
the sea) and `omnilab/ebird`'s (birds, with a key) - a fossil's are outside the contract (`omnilab/pbdb`'s
`fossils()`, an age being no date) -, those
of earthquakes `omnilab/usgs`'s and `omnilab/emsc`'s.

PHP 8.2 or later.

Omnilab needs no framework. The core requires nothing but `symfony/http-client-contracts`, each
source package `symfony/http-client`: two libraries that stand alone. It runs the same in plain
PHP, in a worker, in Laravel or Slim, and in Symfony, where a bundle does the wiring
([Symfony](symfony.md)).

## Requirements

| | Requires |
|---|---|
| `omnisense/omnilab` | PHP 8.2 or later, `symfony/http-client-contracts` ^3.0 |
| each `omnilab/<source>` | PHP 8.2 or later, `omnisense/omnilab` ^1.0@dev, `symfony/http-client` ^6.4, ^7.0 or ^8.0; `omnilab/cams` requires `omnilab/cds` besides |
| the Symfony bundle | `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel` ^6.4, ^7.0 or ^8.0 (suggested, not required: a Symfony application has them) |

No PHP extension is declared. The core decodes JSON (`ext-json`, always present since PHP 8.0);
thirteen sources (chebi, chembl, codata, ebird, ensembl, exoplanets, gbif, inaturalist, ncbi,
simbad, uniprot, vizier, wikidata) call `mb_strtolower()` and the other `mb_*` functions.

Each of them requires `ext-mbstring` in its `composer.json`.

No version is tagged yet: every package is installed from its `1.x` branch (`1.x-dev`). An
application that requires them either writes each constraint `1.x-dev`
(`composer require omnisense/omnilab:1.x-dev omnilab/pubchem:1.x-dev`) or sets
`"minimum-stability": "dev"` and `"prefer-stable": true` in its `composer.json`. The source
packages require the core as `^1.0@dev`.

## Plain PHP

```php
<?php // bare.php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Copernicus\CopernicusSourceFactory;
use Omnilab\Model\Point;
use Omnilab\Model\Scheme;
use Omnilab\OpenMeteo\OpenMeteoSourceFactory;
use Omnilab\PubChem\PubChemSourceFactory;
use Omnilab\Registry;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\SeriesQuery;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$registry = new Registry([new PubChemSourceFactory($http), new OpenMeteoSourceFactory($http), new CopernicusSourceFactory($http)], [
    'pubchem' => ['factory' => 'pubchem', 'options' => ['mailto' => getenv('OMNILAB_MAILTO') ?: null]],   // your address, in the User-Agent
    'meteo' => ['factory' => 'open-meteo'],
    'copernicus' => ['factory' => 'copernicus'],
]);

// An entity: caffeine, from PubChem.
$caffeine = $registry->get('pubchem')->entity('caffeine');
echo $caffeine->label, ' (', $caffeine->id, '): ', $caffeine->value('molecular_formula'), ', ', $caffeine->property('molecular_weight'), ', XLogP ', $caffeine->property('xlogp'), ', TPSA ', $caffeine->property('tpsa'), "\n";
echo '  CAS ', $caffeine->identifier(Scheme::CAS), ', ChEBI ', $caffeine->identifier(Scheme::CHEBI), ', ChEMBL ', $caffeine->identifier(Scheme::CHEMBL), ', Wikidata ', $caffeine->identifier(Scheme::WIKIDATA), "\n";
echo '  ', $caffeine->image, "\n";

// A series: tomorrow's hourly temperature in Strasbourg, from Open-Meteo.
$strasbourg = new Point(48.5734, 7.7521);
$tomorrow = new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Paris'));
[$temperature] = $registry->get('meteo')->series(new SeriesQuery(['temperature_2m'], $strasbourg, $tomorrow, $tomorrow));
echo "\nStrasbourg, ", $tomorrow->format('Y-m-d'), ', from ', $temperature->min(), ' to ', $temperature->max(), ' ', $temperature->unit, ":\n ";
foreach ($temperature as [$time, $value]) {
    echo ' ', $time->format('H'), 'h ', $value;
}
echo "\n";

// Products: the Sentinel-2 L2A scenes over Strasbourg in the last ten days, under 30 % cloud.
$scenes = $registry->get('copernicus')->products(new ProductQuery(['sentinel-2-l2a'], $strasbourg, new DateTimeImmutable('-10 days'), maxCloudCover: 30));
echo "\nSentinel-2 L2A over Strasbourg since ", (new DateTimeImmutable('-10 days'))->format('Y-m-d'), ":\n";
foreach ($scenes as $scene) {
    printf("  %s  %5.2f %% cloud  %s\n", $scene->datetime->format('Y-m-d H:i'), $scene->cloudCover(), $scene->id);
}
```

```
$ OMNILAB_MAILTO=you@example.org php bare.php
Caffeine (pubchem.cid:2519): C8H10N4O2, 194.19 g/mol, XLogP -0.1, TPSA 58.4 Å²
  CAS 58-08-2, ChEBI 27732, ChEMBL CHEMBL113, Wikidata Q60235
  https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/2519/PNG

Strasbourg, 2026-10-16, from 10.4 to 20.4 °C:
  00h 12.2 01h 11.3 02h 11 03h 10.7 04h 10.7 05h 10.6 06h 10.5 07h 10.4 08h 10.4 09h 10.8 10h 11.4 11h 12 12h 13.2 13h 15.4 14h 17.3 15h 18.7 16h 19.9 17h 20.4 18h 20.2 19h 19.3 20h 18.5 21h 18 22h 17.3 23h 16.6

Sentinel-2 L2A over Strasbourg since 2026-10-04:
  2026-10-12 10:29  28.94 % cloud  S2A_MSIL2A_20261012T102911_N0513_R108_T32UMU_20261012T170607
  2026-10-12 10:29   1.84 % cloud  S2A_MSIL2A_20261012T102911_N0513_R108_T32ULU_20261012T170607
  2026-10-05 10:28  11.10 % cloud  S2B_MSIL2A_20261005T102859_N0513_R108_T32UMU_20261005T142235
  2026-10-05 10:28   5.62 % cloud  S2B_MSIL2A_20261005T102859_N0513_R108_T32ULU_20261005T142235
```

(as answered on 2026-10-15; the scenes' times are UTC, the temperatures Strasbourg's hours)

Run again on 2026-10-27, in the harness image against the workspace's checkouts: the same compound,
another day's temperatures, and no Sentinel-2 scene under 30 % cloud in those ten days - an empty
page, not an error:

```
Caffeine (pubchem.cid:2519): C8H10N4O2, 194.19 g/mol, XLogP -0.1, TPSA 58.4 Å²
  CAS 58-08-2, ChEBI 27732, ChEMBL CHEMBL113, Wikidata Q60235
  https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/cid/2519/PNG

Strasbourg, 2026-10-28, from 7.8 to 14.9 °C:
  00h 12.7 01h 13.1 02h 12.9 03h 13.9 04h 13.5 05h 13.2 06h 12.5 07h 11.6 08h 11 09h 11.4 10h 12.2 11h 13.2 12h 14.1 13h 14.6 14h 14.9 15h 14.8 16h 14.5 17h 14 18h 13.3 19h 12.3 20h 11.1 21h 10.1 22h 8.9 23h 7.8

Sentinel-2 L2A over Strasbourg since 2026-10-17:
```

No key, no account: the script asks the real services. That is all there is to it:

- a **factory** per source package (`PubChemSourceFactory`, `OpenMeteoSourceFactory`,
  `CopernicusSourceFactory`), which takes the HTTP client to call with - the application's, a
  `MockHttpClient` in a test; with none given it makes its own (`HttpClient::create()`);
- the **registry**, built by hand from the factories and the sources' options, by name;
- the **sources** it gives, and what works on them: the `Collector`, the `Merger`.

No class of a framework is loaded on the way - a test of this package checks it in a process of
its own (`Tests/BareTest.php`), and so does `docker compose run --rm omnilab bare`
([harness](harness.md)).

## One source

```php
use Omnilab\Model\Identifier;
use Omnilab\PubChem\PubChemSourceFactory;
use Omnilab\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$pubchem = (new PubChemSourceFactory(HttpClient::create()))->create(['mailto' => 'support@glitchr.io']);

$pubchem->entity('caffeine');                            // by name
$pubchem->entity('RYYVLZVUVIJVGH-UHFFFAOYSA-N');         // by InChIKey
$pubchem->entity('CHEBI:27732');                         // by an identifier PubChem keeps among its synonyms
$pubchem->entity(Identifier::cid(2519));

$page = $pubchem->search(new Query('caffeine', limit: 10));
foreach ($page as $compound) {
    echo $compound->id, ' ', $compound->label, ' ', $compound->property('molecular_weight'), "\n";
}
$next = $pubchem->search(new Query('caffeine', limit: 10, cursor: $page->next));   // the next page
```

An entity is named by an `Identifier` or a string: one of the source's identifiers (`'2519'`,
`'CHEBI:27732'`, `'58-08-2'`), a page of a base (`https://pubchem.ncbi.nlm.nih.gov/compound/2519`,
`https://identifiers.org/chebi:27732`) or, for the sources that look things up by name, a name.

## A series, products

```php
use Omnilab\Model\BBox;
use Omnilab\Model\Point;
use Omnilab\Model\Series;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\SeriesQuery;

$meteo = $registry->get('meteo');
$copernicus = $registry->get('copernicus');
$strasbourg = new Point(48.5734, 7.7521);
[$max, $rain] = $meteo->series(new SeriesQuery(['temperature_2m', 'precipitation_sum'], $strasbourg,
    new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-30'), SeriesQuery::DAILY, Series::MAX));
$max->variable;        // temperature_2m_max: Open-Meteo's name for it; precipitation_sum says its own and is kept
$max->forecast;        // false: the archive answered

$page = $copernicus->products(new ProductQuery(['sentinel-1-grd'], new BBox(7.68, 48.53, 7.83, 48.64), new \DateTimeImmutable('-7 days')));
foreach ($page as $scene) {
    echo $scene->datetime->format(\DATE_ATOM), ' ', $scene->platform(), ' ', $scene->asset('Product')?->href, "\n";
}
```

## Air quality

```php
$air = (new OpenMeteoSourceFactory($http))->create(['api' => 'air_quality']);
$day = new \DateTimeImmutable('2026-10-14');
foreach ($air->series(new SeriesQuery(['pm2_5', 'pm10', 'nitrogen_dioxide', 'ozone', 'european_aqi', 'us_aqi'], $strasbourg, $day, $day)) as $series) {
    printf("%-17s %-6s from %s to %s\n", $series->variable, $series->unit, $series->min(), $series->max());
}
```

```
pm2_5             μg/m³ from 5.8 to 19
pm10              μg/m³ from 7.7 to 24.9
nitrogen_dioxide  μg/m³ from 7 to 41.4
ozone             μg/m³ from 16 to 73
european_aqi      EAQI   from 22 to 49
us_aqi            USAQI  from 41 to 51
```

(Strasbourg, 2026-10-14, as answered on 2026-10-15)

## Occurrences

What was found or happened at a place in a period - the fourth shape
([occurrences](occurrences.md)):

```php
use Omnilab\Gbif\GbifSourceFactory;
use Omnilab\Source\OccurrenceQuery;
use Omnilab\Usgs\UsgsSourceFactory;

$gbif = (new GbifSourceFactory($http))->create(['mailto' => 'you@example.org']);
$lions = $gbif->occurrences(new OccurrenceQuery(new Point(-24.99, 31.59), new \DateTimeImmutable('2025-01-01'),
    new \DateTimeImmutable('2025-12-31'), [OccurrenceQuery::TAXON => '4CGXP'], radius: 100, limit: 300));
echo $lions->total, " lions within 100 km of Skukuza in 2025\n";

$usgs = (new UsgsSourceFactory($http))->create(['mailto' => 'you@example.org']);
$quakes = $usgs->occurrences(new OccurrenceQuery(new BBox(-25, 35, 45, 72), new \DateTimeImmutable('2026-10-01'),
    new \DateTimeImmutable('2026-10-07'), [OccurrenceQuery::MIN_MAGNITUDE => 4], limit: 3, sort: 'magnitude'));
foreach ($quakes as $quake) {
    echo $quake->time->format('Y-m-d H:i:s'), ' UTC  ', $quake->place, '  ', $quake->label, "\n";
}
```

```
412 lions within 100 km of Skukuza in 2025
2026-10-04 11:43:23 UTC  37.7292,36.1272  M 5.0 - 21 km ESE of Feke, Turkey
2026-10-04 11:36:26 UTC  37.7757,36.1685  M 4.7 - 22 km E of Feke, Turkey
2026-10-04 11:19:26 UTC  37.8028,36.1749  M 4.7 - 21 km SSE of Saimbeyli, Turkey
```

(as answered on 2026-10-15; `printf` pads `μg/m³` by its bytes; two earthquakes of one magnitude
come in either order)

## Several sources

```php
use Omnilab\Chebi\ChebiSourceFactory;
use Omnilab\Collector;
use Omnilab\PubChem\PubChemSourceFactory;
use Omnilab\Registry;
use Omnilab\Wikidata\WikidataSourceFactory;

$registry = new Registry([new PubChemSourceFactory($http), new ChebiSourceFactory($http), new WikidataSourceFactory($http)], [
    'pubchem' => ['factory' => 'pubchem', 'options' => ['mailto' => 'you@example.org']],
    'chebi' => ['factory' => 'chebi', 'options' => ['mailto' => 'you@example.org']],
    'wikidata' => ['factory' => 'wikidata', 'options' => ['mailto' => 'you@example.org']],
]);
$collector = new Collector($registry);
$caffeine = $collector->collect('pubchem', 'caffeine');   // then its InChIKey offered to ChEBI, its CID to Wikidata
$caffeine->sources;                                       // those that answered: pubchem, chebi, wikidata
$collector->incomplete;                                   // the sources skipped (down, rate limited): do not cache this answer
foreach (['molecular_weight', 'monoisotopic_mass', 'is_a', 'melting_point'] as $key) {
    echo $key, ': ', implode('  ', array_map(fn ($p) => $p.' ['.$p->source.']', $caffeine->properties($key))), "\n";
}
```

```
molecular_weight: 194.19 g/mol [pubchem]  194.194 g/mol [chebi]
monoisotopic_mass: 194.08037557 Da [pubchem]  194.08038 Da [chebi]
is_a: purine alkaloid [chebi]  trimethylxanthine [chebi]
melting_point: 234 °C [wikidata]  235 °C [wikidata]
```

(as answered on 2026-10-15)

See [Following cross-references, merging](merging.md).

## In a framework

- **Symfony**: `Omnilab\Bridge\Symfony\OmnilabBundle` registers the factories on the
  application's `http_client`, builds the registry from `config/packages/omnilab.yaml`, and
  autowires each source by its name, the `Collector` and the `Merger`: see [Symfony](symfony.md).
  Its components (`symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`) are
  not required by this package: a Symfony application has them.
- **Laravel**: there is no Laravel bridge for Omnilab; a service provider of the application
  builds the `Registry` as a singleton: see [Laravel](laravel.md).
- **Any other**: build the `Registry` once, where the framework builds its services (a service
  provider, a container definition), as the script above does.

## Errors

| Exception | When |
|---|---|
| `NotSupportedException` | the source does not do that, or cannot read that identifier: thrown before any call |
| `RateLimitedException` | 429: slow down (`$retryAfter` seconds, when the service says) |
| `UnavailableException` | 5xx (PubChem's 503 "server busy"), a timeout, a network error: never "nothing" |
| `ProviderException` | any other error answer (an unknown variable, collection or structure: 400), with the service's reason |
| `InvalidConfigException` | a source not configured, a factory not installed, an option missing |

All implement `Omnilab\Exception\OmnilabException`. Not found is `null`, an empty page, or a
series without points.
