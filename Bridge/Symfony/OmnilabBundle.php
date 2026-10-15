<?php

namespace Omnilab\Bridge\Symfony;

use Omnilab\Chebi\ChebiSourceFactory;
use Omnilab\Chembl\ChemblSourceFactory;
use Omnilab\Collector;
use Omnilab\Copernicus\CopernicusSourceFactory;
use Omnilab\Ensembl\EnsemblSourceFactory;
use Omnilab\Gbif\GbifSourceFactory;
use Omnilab\INaturalist\INaturalistSourceFactory;
use Omnilab\Jpl\JplSourceFactory;
use Omnilab\Merger;
use Omnilab\Ncbi\NcbiSourceFactory;
use Omnilab\OpenMeteo\OpenMeteoSourceFactory;
use Omnilab\Pdb\PdbSourceFactory;
use Omnilab\PubChem\PubChemSourceFactory;
use Omnilab\Registry;
use Omnilab\Simbad\SimbadSourceFactory;
use Omnilab\Source\SourceFactoryInterface;
use Omnilab\Source\SourceInterface;
use Omnilab\UniProt\UniProtSourceFactory;
use Omnilab\Usgs\UsgsSourceFactory;
use Omnilab\Wikidata\WikidataSourceFactory;
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
    ];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('sources')
                    ->info('The sources, by name: a factory (pubchem, chebi, wikidata, uniprot, ensembl, pdb, gbif, inaturalist, simbad, usgs, open-meteo, copernicus...) and its options.')
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
