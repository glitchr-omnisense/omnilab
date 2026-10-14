<?php

namespace Omnilab\Tests;

use Omnilab\Collector;
use Omnilab\Exception\InvalidConfigException;
use Omnilab\Merger;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Registry;
use Omnilab\Source\Query;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    protected function setUp(): void
    {
        StubSource::$log = [];
    }

    /** PubChem, ChEBI and Wikidata as they know caffeine; a fourth source that is down. */
    private function registry(): Registry
    {
        return new Registry([new StubSourceFactory()], [
            'pubchem' => ['factory' => 'stub', 'options' => ['name' => 'pubchem', 'reads' => ['pubchem.cid', 'inchikey', 'cas'], 'entities' => [
                [['pubchem.cid:2519', 'inchikey:RYYVLZVUVIJVGH-UHFFFAOYSA-N', 'cas:58-08-2', 'chebi:27732'], 'Caffeine', ['molecular_weight' => new Quantity(194.19, 'g/mol'), 'charge' => 0]],
            ]]],
            'chebi' => ['factory' => 'stub', 'options' => ['name' => 'chebi', 'reads' => ['chebi'], 'entities' => [
                [['chebi:27732', 'wikidata:Q60235'], 'caffeine', ['molecular_weight' => new Quantity(194.191, 'g/mol'), 'charge' => 0]],
            ]]],
            'wikidata' => ['factory' => 'stub', 'options' => ['name' => 'wikidata', 'reads' => ['wikidata'], 'entities' => [
                [['wikidata:Q60235', 'cas:58-08-2'], 'caffeine', ['melting_point' => new Quantity(235, '°C')]],
            ]]],
            'down' => ['factory' => 'stub', 'options' => ['name' => 'down', 'reads' => ['inchikey'], 'down' => true]],
            'genes' => ['factory' => 'stub', 'options' => ['name' => 'genes', 'reads' => ['ncbi.gene']]],
        ]);
    }

    public function testSourcesByNameBuiltOnce(): void
    {
        $registry = $this->registry();

        self::assertSame(['pubchem', 'chebi', 'wikidata', 'down', 'genes'], $registry->names());
        self::assertSame(['stub'], $registry->factories());
        self::assertSame($registry->get('chebi'), $registry->get('chebi'));
        self::assertTrue($registry->has('wikidata'));
        self::assertSame(['name' => 'down', 'reads' => ['inchikey'], 'down' => true], $registry->options('down'));
        self::assertCount(5, $registry->all());

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('No "uniprot" source; configured: pubchem, chebi, wikidata, down, genes.');
        $registry->get('uniprot');
    }

    public function testARequiredOption(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "stub" source needs: name.');
        (new Registry([new StubSourceFactory()], ['x' => ['factory' => 'stub']]))->get('x');
    }

    public function testTheCollectorFollowsTheCrossReferencesOneRoundAtATime(): void
    {
        $collector = new Collector($this->registry());
        $caffeine = $collector->collect('pubchem', 'pubchem.cid:2519');

        self::assertSame(['pubchem pubchem.cid:2519', 'chebi chebi:27732', 'down inchikey:RYYVLZVUVIJVGH-UHFFFAOYSA-N'], StubSource::$log, 'each source offered only the schemes it reads; Wikidata not yet: Q60235 is ChEBI\'s');
        self::assertSame(['pubchem', 'chebi'], $caffeine->sources);
        self::assertSame(['down' => '[down] down'], $collector->incomplete);
        self::assertSame('Caffeine', $caffeine->label);
        self::assertSame('Q60235', $caffeine->identifier(Scheme::WIKIDATA), 'ChEBI\'s cross-reference, united');
        self::assertSame(['pubchem', 'chebi'], array_map(static fn (Property $p) => $p->source, $caffeine->properties('molecular_weight')), 'both values kept, each with its source');
        self::assertSame('194.19 g/mol', (string) $caffeine->property('molecular_weight'));

        StubSource::$log = [];
        $deeper = $collector->collect('pubchem', 'pubchem.cid:2519', depth: 2);
        self::assertSame(['pubchem', 'chebi', 'wikidata'], $deeper->sources, 'the second round follows what ChEBI gave');
        self::assertSame('235 °C', (string) $deeper->property('melting_point'));
        self::assertNotContains('down inchikey:RYYVLZVUVIJVGH-UHFFFAOYSA-N', \array_slice(StubSource::$log, 3), 'a source down is not asked again');
    }

    public function testTheCollectorFollowsAnEntityIntoTheSourcesNamed(): void
    {
        $registry = $this->registry();
        $collector = new Collector($registry, new Merger(['default' => ['chebi', 'pubchem']]));
        $caffeine = $collector->follow($registry->get('pubchem')->entity('pubchem.cid:2519'), 1, ['chebi']);

        self::assertSame(['chebi', 'pubchem'], $caffeine->sources, 'ChEBI preferred');
        self::assertSame('caffeine', $caffeine->label);
        self::assertSame('chebi:27732', $caffeine->id->key(), 'the id of the preferred source');
        self::assertSame('2519', $caffeine->identifier(Scheme::PUBCHEM_CID), 'PubChem\'s among the identifiers');
        self::assertSame([], $collector->incomplete);
    }

    public function testUnknownToTheFirstSourceIsNull(): void
    {
        self::assertNull((new Collector($this->registry()))->collect('pubchem', 'pubchem.cid:1'));
    }

    public function testASearchAcrossTheSourcesMerged(): void
    {
        $collector = new Collector($this->registry());
        $found = $collector->search(new Query('caff'));

        self::assertCount(1, $found, 'one thing, three sources');
        self::assertSame(['pubchem', 'chebi', 'wikidata'], $found[0]->sources);
        self::assertArrayHasKey('down', $collector->incomplete);
    }
}
