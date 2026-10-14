<?php

namespace Omnilab\Tests;

use Omnilab\Bridge\Symfony\OmnilabBundle;
use Omnilab\Copernicus\CopernicusSourceFactory;
use Omnilab\OpenMeteo\OpenMeteoSourceFactory;
use Omnilab\PubChem\PubChemSourceFactory;
use Omnilab\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Omnilab outside Symfony: the harness's bare script (docker/harness/bin/bare)
 * run in a PHP process of its own - this one has loaded the bundle's tests -
 * builds the registry by hand, asks PubChem for an entity, Open-Meteo for a
 * series and the Copernicus Data Space for products, from recorded answers,
 * and reports every class and file PHP loaded on the way. None may be a
 * framework's.
 */
final class BareTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation)|Symfony\\\\Bundle|Doctrine|Twig)\\\\~';
    private const FRAMEWORK_FILES = '~/vendor/(?:symfony/(?:dependency-injection|config|http-kernel|http-foundation|[a-z-]*bundle)|doctrine|twig)/~';

    public function testTheRegistryIsBuiltByHandAndNoClassOfAFrameworkIsLoaded(): void
    {
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        self::assertContains(Registry::class, $report['symbols'], 'the registry was built there');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
        $installed = array_values(array_filter(array_column(require __DIR__.'/../docker/harness/plugins.php', 1), 'class_exists'));
        foreach ($installed as $factory) {
            self::assertContains($factory, $report['symbols'], 'every source package installed, built and asked what it can do');
        }
        self::assertSame([] === $installed, [] === $report['sources']);
    }

    public function testEachShapeFromARecordedAnswerWithNoClassOfAFrameworkLoaded(): void
    {
        if (!class_exists(PubChemSourceFactory::class) || !class_exists(OpenMeteoSourceFactory::class) || !class_exists(CopernicusSourceFactory::class)) {
            self::markTestSkipped('omnilab/pubchem, omnilab/open-meteo and omnilab/copernicus are not all installed.');
        }
        [$status, $report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--recorded', '--json']);

        self::assertSame(0, $status);
        self::assertTrue($report['recorded']);
        self::assertSame('pubchem.cid:2519', $report['entity']['id']);
        self::assertSame('Caffeine', $report['entity']['label']);
        self::assertSame('194.19 g/mol', $report['entity']['properties']['molecular_weight']);
        self::assertSame('58.4 Å²', $report['entity']['properties']['tpsa']);
        self::assertContains('wikidata:Q60235', $report['entity']['identifiers']);
        self::assertContains('entity', $report['sources']['pubchem']);

        self::assertSame(['temperature_2m', '°C', '2026-10-16'], [$report['series']['variable'], $report['series']['unit'], $report['series']['day']]);
        self::assertCount(24, $report['series']['points']);
        self::assertSame(['17:00', 20.4], $report['series']['points'][17]);

        self::assertSame(['S2A_MSIL2A_20261012T102911_N0513_R108_T32UMU_20261012T170607', 'S2A_MSIL2A_20261012T102911_N0513_R108_T32ULU_20261012T170607', 'S2B_MSIL2A_20261005T102859_N0513_R108_T32UMU_20261005T142235', 'S2B_MSIL2A_20261005T102859_N0513_R108_T32ULU_20261005T142235'], array_column($report['products'], 'id'));
        self::assertSame([28.94, 1.84, 11.1, 5.62], array_column($report['products'], 'cloud'));

        self::assertContains('Symfony\Component\HttpClient\MockHttpClient', $report['symbols'], 'the answers came through the HTTP client given');
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
    }

    /** The check is not blind: the same report, once the bundle is loaded, names the framework. */
    public function testTheBundleDoesLoadTheFramework(): void
    {
        if (!class_exists(AbstractBundle::class)) {
            self::markTestSkipped('symfony/http-kernel is not installed.');
        }
        [$status, $report] = self::php(['-r', 'require getenv("OMNILAB_AUTOLOAD"); class_exists($argv[1]) || exit(2); echo json_encode(["symbols" => [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], "files" => get_included_files()]);', '--', OmnilabBundle::class]);

        self::assertSame(0, $status);
        $framework = self::framework($report);
        self::assertContains(AbstractBundle::class, $framework);
        self::assertNotEmpty(preg_grep('~/symfony/http-kernel/~', $framework));
    }

    /**
     * @param array{symbols: list<string>, files: list<string>} $report
     *
     * @return list<string> the classes, interfaces, traits and files of a framework among those loaded
     */
    private static function framework(array $report): array
    {
        return [...array_values(preg_grep(self::FRAMEWORK, $report['symbols'])), ...array_values(preg_grep(self::FRAMEWORK_FILES, $report['files']))];
    }

    /**
     * Runs PHP apart, on the autoloader of this run.
     *
     * @param list<string> $arguments
     *
     * @return array{int, array<string, mixed>} the exit status, the JSON printed
     */
    private static function php(array $arguments): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNILAB_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return [$status, $report];
    }
}
