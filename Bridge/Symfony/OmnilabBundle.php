<?php

namespace Omnilab\Bridge\Symfony;

use Omnilab\Cactus\CactusSourceFactory;
use Omnilab\Cams\CamsSourceFactory;
use Omnilab\Cds\CdsSourceFactory;
use Omnilab\Chebi\ChebiSourceFactory;
use Omnilab\Chembl\ChemblSourceFactory;
use Omnilab\Cod\CodSourceFactory;
use Omnilab\Codata\CodataSourceFactory;
use Omnilab\Collector;
use Omnilab\Copernicus\CopernicusSourceFactory;
use Omnilab\Earthdata\EarthdataSourceFactory;
use Omnilab\Exoplanets\ExoplanetsSourceFactory;
use Omnilab\Gaia\GaiaSourceFactory;
use Omnilab\Ensembl\EnsemblSourceFactory;
use Omnilab\Gbif\GbifSourceFactory;
use Omnilab\INaturalist\INaturalistSourceFactory;
use Omnilab\Jpl\JplSourceFactory;
use Omnilab\Kegg\KeggSourceFactory;
use Omnilab\MaterialsProject\MaterialsProjectSourceFactory;
use Omnilab\Merger;
use Omnilab\Ncbi\NcbiSourceFactory;
use Omnilab\Noaa\NoaaSourceFactory;
use Omnilab\OpenMeteo\OpenMeteoSourceFactory;
use Omnilab\Oqmd\OqmdSourceFactory;
use Omnilab\Emsc\EmscSourceFactory;
use Omnilab\UsgsWater\UsgsWaterSourceFactory;
use Omnilab\Tides\TidesSourceFactory;
use Omnilab\Swpc\SwpcSourceFactory;
use Omnilab\BrightSky\BrightSkySourceFactory;
use Omnilab\Elevation\ElevationSourceFactory;
use Omnilab\OpenAq\OpenAqSourceFactory;
use Omnilab\Obis\ObisSourceFactory;
use Omnilab\Worms\WormsSourceFactory;
use Omnilab\Itis\ItisSourceFactory;
use Omnilab\Col\ColSourceFactory;
use Omnilab\Pbdb\PbdbSourceFactory;
use Omnilab\Ebird\EbirdSourceFactory;
use Omnilab\Iucn\IucnSourceFactory;
use Omnilab\Pdb\PdbSourceFactory;
use Omnilab\PubChem\PubChemSourceFactory;
use Omnilab\Registry;
use Omnilab\Reactome\ReactomeSourceFactory;
use Omnilab\Rhea\RheaSourceFactory;
use Omnilab\Simbad\SimbadSourceFactory;
use Omnilab\Source\SourceFactoryInterface;
use Omnilab\Source\SourceInterface;
use Omnilab\UniProt\UniProtSourceFactory;
use Omnilab\Usgs\UsgsSourceFactory;
use Omnilab\Wikidata\WikidataSourceFactory;
use Omnilab\StringDb\StringSourceFactory;
use Omnilab\InterPro\InterProSourceFactory;
use Omnilab\QuickGo\QuickGoSourceFactory;
use Omnilab\Hgnc\HgncSourceFactory;
use Omnilab\OpenTargets\OpenTargetsSourceFactory;
use Omnilab\ClinVar\ClinVarSourceFactory;
use Omnilab\Gwas\GwasSourceFactory;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnilab in a Symfony application: the source packages installed
 * (omnilab/pubchem, omnilab/open-meteo...) registered, the sources built
 * from configuration and injectable by their name, the Merger with the
 * site's preferences and the Collector:
 *
 *     omnilab:
 *         sources:
 *             pubchem: { factory: pubchem, options: { mailto: '%env(OMNILAB_MAILTO)%' } }
 *             meteo: { factory: open-meteo }
 *             copernicus: { factory: copernicus }
 *         merger:
 *             preferences:
 *                 default: [pubchem]
 *                 description: [chebi, pubchem]
 *
 *     public function __construct(SourceInterface $pubchem, SourceInterface $meteo, Collector $collector) {}
 *
 * An application's own factories (a SourceFactoryInterface) are registered
 * too, autoconfigured.
 */
final class OmnilabBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnilab';

    /** The source packages this bundle knows, registered when installed. */
    private const FACTORIES = [
        PubChemSourceFactory::class,
        OpenMeteoSourceFactory::class,
        CopernicusSourceFactory::class,
        ChebiSourceFactory::class,
        WikidataSourceFactory::class,
        ChemblSourceFactory::class,
        UniProtSourceFactory::class,
        NcbiSourceFactory::class,
        GbifSourceFactory::class,
        SimbadSourceFactory::class,
        JplSourceFactory::class,
        UsgsSourceFactory::class,
        EnsemblSourceFactory::class,
        PdbSourceFactory::class,
        INaturalistSourceFactory::class,
        NoaaSourceFactory::class,
        CdsSourceFactory::class,
        CamsSourceFactory::class,
        EarthdataSourceFactory::class,
        ExoplanetsSourceFactory::class,
        GaiaSourceFactory::class,
        ReactomeSourceFactory::class,
        KeggSourceFactory::class,
        RheaSourceFactory::class,
        StringSourceFactory::class,
        InterProSourceFactory::class,
        QuickGoSourceFactory::class,
        HgncSourceFactory::class,
        OpenTargetsSourceFactory::class,
        ClinVarSourceFactory::class,
        GwasSourceFactory::class,
        CodSourceFactory::class,
        CactusSourceFactory::class,
        CodataSourceFactory::class,
        MaterialsProjectSourceFactory::class,
        OqmdSourceFactory::class,
        EmscSourceFactory::class,
        UsgsWaterSourceFactory::class,
        TidesSourceFactory::class,
        SwpcSourceFactory::class,
        BrightSkySourceFactory::class,
        ElevationSourceFactory::class,
        OpenAqSourceFactory::class,
        ObisSourceFactory::class,
        WormsSourceFactory::class,
        ItisSourceFactory::class,
        ColSourceFactory::class,
        PbdbSourceFactory::class,
        EbirdSourceFactory::class,
        IucnSourceFactory::class,
    ];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('sources')
                    ->info('The sources, by name: a factory (pubchem, chebi, wikidata, uniprot, ensembl, pdb, reactome, string, interpro, quickgo, hgnc, opentargets, clinvar, gwas, gbif, inaturalist, simbad, gaia, exoplanets, usgs, open-meteo, noaa, copernicus, cds, cams, earthdata, kegg, rhea, cod, cactus, codata, materials-project, oqmd, emsc, usgs-water, tides, swpc, brightsky, elevation, openaq, obis, worms, itis, col, pbdb, ebird, iucn...) and its options.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('factory')->isRequired()->cannotBeEmpty()->end()
                            ->variableNode('options')->defaultValue([])->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('merger')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('preferences')
                            ->info('For a field (label, description, image...) or a property key (molecular_weight...), the sources in the order they are trusted; "default" for the others.')
                            ->useAttributeAsKey('field')
                            ->arrayPrototype()->scalarPrototype()->end()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array{sources: array<string, array{factory: string, options: array<string, mixed>}>, merger: array{preferences: array<string, list<string>>}} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(SourceFactoryInterface::class)->addTag('omnilab.source_factory');

        $services = $container->services();
        foreach (self::FACTORIES as $factory) {
            if (class_exists($factory) && is_subclass_of($factory, SourceFactoryInterface::class)) {
                $services->set($factory)->args([service('http_client')->nullOnInvalid()])->tag('omnilab.source_factory');
            }
        }

        $services->set(Registry::class)
            ->args([tagged_iterator('omnilab.source_factory'), $config['sources']])
            ->public();

        foreach (array_keys($config['sources']) as $name) {
            $id = 'omnilab.source.'.$name;
            $services->set($id, SourceInterface::class)->factory([service(Registry::class), 'get'])->args([$name]);
            $builder->registerAliasForArgument($id, SourceInterface::class, $name);
        }

        $services->set(Merger::class)->args([$config['merger']['preferences']]);
        $services->set(Collector::class)->args([service(Registry::class), service(Merger::class)]);
    }
}
