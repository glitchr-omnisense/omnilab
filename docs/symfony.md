# Symfony

Omnilab runs without a framework ([installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel` - are not required by `glitchr/omnilab`: the application has them, and
nothing of them is loaded outside Symfony.

Register `Omnilab\Bridge\Symfony\OmnilabBundle` (no Flex recipe):

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

Every `omnilab/*` package installed registers its factory, on the application's `http_client`.
What is autowired:

| Service | |
|---|---|
| `SourceInterface $pubchem` | one source, by the argument's name (the configured name: `$meteo` is Open-Meteo) |
| `Registry` | every configured source by name (`get()`, `names()`, `all()`) |
| `Collector` | `collect('pubchem', 'caffeine')`, `follow()`, `search()` |
| `Merger` | with the configured preferences |

An application's own source - a class implementing `SourceFactoryInterface` - is registered too,
autoconfigured, and can be named as a `factory`.

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
minute, Open-Meteo by the day), except when `$collector->incomplete` is not empty.
