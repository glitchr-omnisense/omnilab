---
title: Symfony
order: 15
---

# Symfony

Omnilab runs without a framework ([installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel` - are not required by `omnisense/omnilab`: the application has them, and
nothing of them is loaded outside Symfony.

The bundle is `Omnilab\Bridge\Symfony\OmnilabBundle`, in this package (an `AbstractBundle`, its
extension alias `omnilab`). There is no `omnibridge/symfony` integration for Omnilab: this bundle
is the whole of it.

## Registration

Register the bundle (no Flex recipe):

```php
// config/bundles.php
return [
    // ...
    Omnilab\Bridge\Symfony\OmnilabBundle::class => ['all' => true],
];
```

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:                       # by name: a factory and its options
        pubchem: { factory: pubchem, options: { mailto: '%env(OMNILAB_MAILTO)%' } }
        meteo: { factory: open-meteo, options: { api_key: '%env(default::OPEN_METEO_API_KEY)%' } }
        copernicus: { factory: copernicus, options: { collections: [sentinel-2-l2a] } }
    merger:
        preferences:               # field or property key => sources, "default" for the others
            default: [pubchem]
```

## The configuration tree

As `config:dump-reference` writes it (dumped from the bundle's `configure()` with Symfony 8.1's
`YamlReferenceDumper` on 2026-10-27):

```yaml
omnilab:

    # The sources, by name: a factory (pubchem, chebi, wikidata, uniprot, ensembl, pdb, reactome, string, interpro, quickgo, hgnc, opentargets, clinvar, gwas, gbif, inaturalist, simbad, gaia, exoplanets, ned, vizier, mpc, horizons, donki, launches, usgs, open-meteo, noaa, copernicus, cds, cams, earthdata, kegg, rhea, cod, cactus, codata, materials-project, oqmd, emsc, usgs-water, tides, swpc, brightsky, elevation, openaq, obis, worms, itis, col, pbdb, ebird, iucn...) and its options.
    sources:

        # Prototype
        name:
            factory:              ~ # Required
            options:              []
    merger:

        # For a field (label, description, image...) or a property key (molecular_weight...), the sources in the order they are trusted; "default" for the others.
        preferences:

            # Prototype
            field:                []
```

| Key | Type | Default | |
|---|---|---|---|
| `sources` | map, by source name | `{}` | the sources the `Registry` builds; their order is the order `names()`, `all()` and the `Collector` follow |
| `sources.<name>.factory` | string, required, not empty | - | the factory's name (`pubchem`, `open-meteo`...: [every one](configuration.md#each-sources-own-options)) or an application's own factory |
| `sources.<name>.options` | any (a map) | `[]` | handed to the factory's `create()` as they are: the options every source takes and the source's own ([Configuration reference](configuration.md)) |
| `merger.preferences` | map of lists of strings | `{}` | for a field (`label`, `kind`, `description`, `url`, `image`, `time`, `place`) or a property key, the sources in the order they are trusted; `default` for every other ([the Merger](merging.md#the-merger)) |

One source package may be configured several times under different names, each with its own
options: Open-Meteo's weather and its air quality (`api: air_quality`), a CDS source with an
account's key beside one without.

> [!WARNING]
> Known defect: Symfony's configuration normalises the dashes of a source's name to underscores
> (`sources` is a prototyped map whose keys are normalised), where the name is expected to be kept
> as written. A source configured as `air-quality:` is the `Registry`'s `air_quality`
> (`$registry->get('air-quality')` throws `InvalidConfigException`), its service
> `omnilab.source.air_quality`; and a `merger.preferences` list that names `air-quality` matches
> no source. Name the sources without dashes (`meteo`, `air`). A factory's name in `factory:` is
> a value, not a key: `factory: open-meteo` is read as written.

## Services

What `loadExtension()` registers:

| Service id | Class | Public | |
|---|---|---|---|
| each factory class installed (`Omnilab\PubChem\PubChemSourceFactory`...) | itself | no | built on `http_client` (null when the application has none: the factory then makes its own client); tagged `omnilab.source_factory` |
| `Omnilab\Registry` | `Registry` | yes | every factory tagged `omnilab.source_factory`, and `sources` |
| `omnilab.source.<name>` | `SourceInterface` | no | `Registry::get('<name>')`, for each configured source: built when first used |
| `Omnilab\Merger` | `Merger` | no | `merger.preferences` |
| `Omnilab\Collector` | `Collector` | no | the `Registry` and the `Merger` above |

The bundle knows the 56 factories of the `omnilab/*` packages (`OmnilabBundle::FACTORIES`) and
registers each one whose class exists: a package not installed costs nothing. Every class that
implements `SourceFactoryInterface` is autoconfigured with the `omnilab.source_factory` tag, so
an application's own factory (a class of its `src/`) is in the `Registry` too, and can be named
in `factory:` by its `getName()`.

### Autowiring

| Argument | Gets |
|---|---|
| `SourceInterface $pubchem` | the source configured as `pubchem`: one named alias per source (`SourceInterface $<name>`, the name camel-cased: a source `air_quality` is `SourceInterface $airQuality`) |
| `Registry $registry` | every configured source by name: `get()`, `has()`, `names()`, `all()`, `factories()`, `options()` |
| `Collector $collector` | `collect('pubchem', 'caffeine')`, `follow()`, `search()` |
| `Merger $merger` | with the configured preferences |

```php
use Omnilab\Collector;
use Omnilab\Source\SourceInterface;

final class Lab
{
    public function __construct(
        private readonly SourceInterface $pubchem,    // omnilab.source.pubchem
        private readonly SourceInterface $meteo,      // omnilab.source.meteo
        private readonly Collector $collector,
    ) {
    }
}
```

A `SourceInterface` argument whose name is no configured source is not autowired: Symfony says
the service cannot be resolved. The `Registry` is public, so `$container->get(Registry::class)`
works in a test.

## Commands, events, Twig

None: the bundle registers no console command, dispatches no event and adds no Twig function.
The harness's console (`docker/harness/bin/omnilab`) is a script of the harness, not a
Symfony command ([harness](harness.md)).

## In a controller

```php
final class CompoundController extends AbstractController
{
    #[Route('/compounds/{name}')]
    public function show(string $name, Collector $collector): Response
    {
        $compound = $collector->collect('pubchem', $name) ?? throw $this->createNotFoundException();

        return $this->render('compound/show.html.twig', ['compound' => $compound]);
    }
}
```

Cache what the sources answer (they are remote, and some count their calls: PubChem by the
minute, Open-Meteo by the day), except when `$collector->incomplete` is not empty, and never an
`UnavailableException` as "nothing" ([Errors](errors.md)).

## What is tested

`Tests/Bridge/OmnilabBundleTest.php` boots a kernel with the bundle and checks that the 56
factories installed in the workspace are registered, that an application's own factory is
autoconfigured, that two sources are injected by their argument names, and that the configured
preferences reach the `Collector`'s `Merger`.
