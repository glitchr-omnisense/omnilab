# glitchr/omnilab

One contract for the public science-data APIs, whatever the domain - chemistry, biology, species,
astronomy, the Earth and its weather: what science knows of a thing, what it measures at a place,
what it has catalogued over an area. Publications are not here: they are
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
```

Every answer takes one of three shapes, not one per domain:

- an **entity** (`Model\Entity`): a thing named and identified - a compound, a gene, a protein, a
  species, a star, an asteroid, an earthquake - its properties with their units (`Property`,
  `Quantity`), its synonyms, and its identifiers in other bases (`Identifier`, `Scheme`: PubChem
  CID, ChEBI, ChEMBL, InChIKey, CAS, UniProt, NCBI Gene and Taxonomy, Ensembl, PDB, GBIF,
  iNaturalist, SIMBAD, JPL SBDB, USGS events, Wikidata...); a time and a place for an event;
- a **series** (`Model\Series`): one variable at a `Point` or over a `BBox`, its points in time,
  its unit, its step and aggregation;
- a **product** (`Model\Product`, in the shape of a STAC item): a catalogued scene or file, its
  datetime, footprint, properties and assets.

This package holds the contract (`Source\SourceInterface`, `Source\SourceFactory`, `Registry`), the
models, the `Collector` that follows an entity's cross-references into the other sources and the
`Merger` that makes one entity of several, keeping the source of every property; and a bridge for
Symfony. It needs no framework: it requires nothing but `symfony/http-client-contracts`, each
source package `symfony/http-client`. Each source is a package of its own:

| Package | Source | Shape | Key |
|---|---|---|---|
| [`omnilab/pubchem`](https://github.com/glitchr-studio/omnilab-pubchem) | PubChem (NCBI): compounds by CID, name, InChIKey, CAS, SMILES; computed properties, synonyms, 2D structure, cross-references | entity | none (5 calls a second at most) |
| [`omnilab/open-meteo`](https://github.com/glitchr-studio/omnilab-open-meteo) | Open-Meteo: hourly, 15-minute and daily weather at a point, forecast and archive (from 1940) | series | none for non-commercial use |
| [`omnilab/copernicus`](https://github.com/glitchr-studio/omnilab-copernicus) | Copernicus Data Space: the STAC catalogue of Sentinel, Landsat, MODIS, the DEM and the Copernicus services | product | none to search (a CDSE token to download) |
| [`omnilab/chebi`](https://github.com/glitchr-studio/omnilab-chebi) | ChEBI (EMBL-EBI): chemical entities by ChEBI id, InChIKey, CAS, KEGG or name; masses, structure, ontology (classes, roles), cross-references | entity | none |
| [`omnilab/wikidata`](https://github.com/glitchr-studio/omnilab-wikidata) | Wikidata: any item by Q-number, by a science base's identifier (PubChem, ChEBI, CAS, UniProt, NCBI...) or by label; labels in a language, cross-references, a few properties with units | entity | none (a User-Agent that names the caller; 200 calls a minute) |

A source answers what it can - `capabilities()` says what - and throws `NotSupportedException`
for the rest, before any call. Unknown is `null`, an empty page or a series without points; a
service down or rate limited is an `UnavailableException` (`RateLimitedException`), which a caller
never caches as "nothing".

## Documentation

- [Installation and first calls](docs/installation.md)
- [Sources: what each can and cannot do](docs/sources.md)
- [Models and identifiers](docs/models.md)
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
docker compose run --rm omnilab bare      # plain PHP: no bundle, no container, and what PHP loaded
docker compose run --rm omnilab test
```

License: MIT.
