<?php

namespace Omnilab\Tests\Bridge;

use Omnilab\Bridge\Symfony\OmnilabBundle;
use Omnilab\Collector;
use Omnilab\Merger;
use Omnilab\Registry;
use Omnilab\Source\SourceInterface;
use Omnilab\Tests\StubSource;
use Omnilab\Tests\StubSourceFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;

final class OmnilabBundleTest extends TestCase
{
    private ?Kernel $kernel = null;

    protected function setUp(): void
    {
        StubSource::$log = [];
    }

    protected function tearDown(): void
    {
        if ($this->kernel) {
            $dir = $this->kernel->getProjectDir();
            $this->kernel->shutdown();
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($dir);
        }
    }

    public function testAKernelBootsWithTheSourcesByName(): void
    {
        $this->kernel = new OmnilabTestKernel('test', false);
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        $registry = $container->get(Registry::class);
        self::assertSame(['pubchem', 'chebi'], $registry->names());
        foreach (['pubchem', 'open-meteo', 'copernicus', 'chebi', 'wikidata', 'chembl', 'uniprot', 'ncbi', 'gbif', 'simbad', 'jpl', 'usgs', 'ensembl', 'pdb', 'inaturalist', 'noaa', 'cds', 'cams', 'earthdata', 'exoplanets', 'gaia', 'reactome', 'string', 'interpro', 'quickgo', 'hgnc', 'opentargets', 'clinvar', 'gwas', 'kegg', 'rhea'] as $factory) {
            self::assertContains($factory, $registry->factories(), 'the packages installed are registered');
        }
        self::assertContains('stub', $registry->factories(), 'the application\'s own, autoconfigured');

        $lab = $container->get(Lab::class);
        self::assertSame('pubchem', $lab->pubchem->getName(), 'one source by its argument name');
        self::assertSame('chebi', $lab->chebi->getName());

        $caffeine = $lab->collector->collect('pubchem', 'pubchem.cid:2519');
        self::assertSame(['chebi', 'pubchem'], $caffeine->sources, 'the configured preferences: chebi first');
        self::assertSame('caffeine', $caffeine->label);
    }
}

final class Lab
{
    public function __construct(
        public readonly SourceInterface $pubchem,
        public readonly SourceInterface $chebi,
        public readonly Collector $collector,
        public readonly Merger $merger,
    ) {
    }
}

final class OmnilabTestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new OmnilabBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->register('http_client', MockHttpClient::class);
            $container->register(StubSourceFactory::class)->setAutoconfigured(true);
            $container->register(Lab::class)->setAutowired(true)->setPublic(true);
            $container->loadFromExtension('omnilab', [
                'sources' => [
                    'pubchem' => ['factory' => 'stub', 'options' => ['name' => 'pubchem', 'reads' => ['pubchem.cid'], 'entities' => [[['pubchem.cid:2519', 'chebi:27732'], 'Caffeine']]]],
                    'chebi' => ['factory' => 'stub', 'options' => ['name' => 'chebi', 'reads' => ['chebi'], 'entities' => [[['chebi:27732'], 'caffeine']]]],
                ],
                'merger' => ['preferences' => ['default' => ['chebi', 'pubchem']]],
            ]);
        });
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/omnilab-bundle-test-'.getmypid();
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log';
    }
}
