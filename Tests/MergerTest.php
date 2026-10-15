<?php

namespace Omnilab\Tests;

use Omnilab\Merger;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use PHPUnit\Framework\TestCase;

final class MergerTest extends TestCase
{
    private static function caffeine(string $source, Identifier $id, array $others, array $properties = [], array $fields = []): Entity
    {
        return new Entity(...$fields + [
            'id' => $id,
            'label' => 'Caffeine',
            'domain' => Domain::CHEMISTRY,
            'properties' => $properties,
            'identifiers' => new Identifiers($others),
            'source' => $source,
        ]);
    }

    public function testEntitiesThatShareAnIdentifierAreOneEachPropertyWithItsSource(): void
    {
        $pubchem = self::caffeine('pubchem', Identifier::cid(2519), [Identifier::cas('58-08-2'), Identifier::chebi(27732)], [
            new Property('molecular_weight', 'Molecular weight', new Quantity(194.19, 'g/mol'), 'pubchem'),
            new Property('xlogp', 'XLogP', -0.1, 'pubchem'),
        ], ['image' => 'https://pubchem/2519.png', 'synonyms' => ['Guaranine', 'Theine']]);
        $chebi = self::caffeine('chebi', Identifier::chebi(27732), [Identifier::wikidata('Q60235')], [
            new Property('molecular_weight', 'Molecular weight', new Quantity(194.1906, 'g/mol')),
        ], ['label' => 'caffeine', 'description' => 'A trimethylxanthine.', 'synonyms' => ['theine', 'Methyltheobromine']]);
        $water = new Entity(Identifier::cid(962), 'Water', Domain::CHEMISTRY, identifiers: Identifiers::of(Identifier::cas('7732-18-5')), source: 'pubchem');

        $merged = (new Merger(['description' => ['chebi'], 'molecular_weight' => ['chebi']]))->merge([$pubchem, $water], [$chebi]);

        self::assertCount(2, $merged);
        [$caffeine, $h2o] = $merged;
        self::assertSame('Water', $h2o->label);
        self::assertSame(['pubchem'], $h2o->sources);
        self::assertSame('pubchem.cid:2519', $caffeine->id->key());
        self::assertSame('Caffeine', $caffeine->label);
        self::assertSame('A trimethylxanthine.', $caffeine->description);
        self::assertSame('https://pubchem/2519.png', $caffeine->image);
        self::assertSame(['Guaranine', 'Theine', 'Methyltheobromine'], $caffeine->synonyms, 'united, without case, the label left out');
        self::assertSame(['pubchem.cid:2519', 'cas:58-08-2', 'chebi:27732', 'wikidata:Q60235'], $caffeine->identifiers->keys());
        self::assertSame(['pubchem', 'chebi'], $caffeine->sources);
        self::assertSame(['chebi', 'pubchem'], array_map(static fn (Property $p) => $p->source, $caffeine->properties('molecular_weight')), 'ChEBI preferred for it, the property without a source given its entity\'s');
        self::assertSame(-0.1, $caffeine->value('xlogp'));
        self::assertSame(['molecular_weight', 'xlogp'], $caffeine->keys());
    }

    public function testTwoCompoundsWithTwoCidsStayTwoWhateverTheyShare(): void
    {
        $base = new Entity(Identifier::cid(2519), 'Caffeine', identifiers: Identifiers::of(Identifier::cas('58-08-2')), source: 'pubchem');
        $salt = new Entity(Identifier::cid(6097185), 'Caffeine citrate', identifiers: Identifiers::of(Identifier::cas('58-08-2')), source: 'pubchem');
        $chebi = new Entity(Identifier::chebi(27732), 'caffeine', identifiers: Identifiers::of(Identifier::cid(2519), Identifier::cas('58-08-2')), source: 'chebi');

        $merger = new Merger();
        self::assertFalse($merger->same($base, $salt));
        self::assertTrue($merger->same($base, $chebi));
        self::assertCount(2, $merger->merge([$base, $salt, $chebi]), 'ChEBI joins the base, the salt stays apart');
        self::assertSame(['pubchem', 'chebi'], $merger->merge([$base, $salt, $chebi])[0]->sources);
    }

    public function testAnotherIdentifierOfItsOwnSchemeIsStillItself(): void
    {
        // GBIF gives its new key and its legacy one; Wikidata may carry only the legacy one.
        $gbif = new Entity(Identifier::of('gbif', '4CGXP'), 'Panthera leo', identifiers: Identifiers::of(Identifier::of('gbif', '5219404'), Identifier::of('ncbi.taxon', '9689')), source: 'gbif');
        $wikidata = new Entity(Identifier::wikidata('Q140'), 'lion', identifiers: Identifiers::of(Identifier::of('gbif', '5219404')), source: 'wikidata');
        $tiger = new Entity(Identifier::of('gbif', '5219416'), 'Panthera tigris', identifiers: Identifiers::of(Identifier::of('ncbi.taxon', '9689')), source: 'gbif');

        $merger = new Merger();
        self::assertTrue($merger->same($gbif, $wikidata));
        self::assertFalse($merger->same($gbif, $tiger), 'a GBIF key the lion does not have is another taxon, whatever else they share');
    }

    public function testRelatedThingsAreKeptAndJoinNothing(): void
    {
        $taxon = Identifier::of('ncbi.taxon', '9606');
        $hbb = new Entity(Identifier::of('uniprot', 'P68871'), 'Hemoglobin subunit beta', related: ['organism' => Identifiers::of($taxon), 'ligand' => Identifiers::of(Identifier::chebi(60344))], source: 'uniprot');
        $ins = new Entity(Identifier::of('uniprot', 'P01308'), 'Insulin', related: ['organism' => Identifiers::of($taxon)], source: 'uniprot');
        $human = new Entity(Identifier::wikidata('Q15978631'), 'Homo sapiens', identifiers: Identifiers::of($taxon), source: 'wikidata');
        $gene = new Entity(Identifier::of('ncbi.gene', '3043'), 'HBB', identifiers: Identifiers::of(Identifier::of('uniprot', 'P68871')), related: ['organism' => Identifiers::of($taxon), 'ligand' => Identifiers::of(Identifier::chebi(58248))], source: 'ncbi');

        $merger = new Merger();
        self::assertCount(3, $merger->merge([$hbb, $ins, $human]), 'sharing an organism makes nothing one');
        self::assertSame('9606', $hbb->related('organism')->value(Scheme::NCBI_TAXON));
        self::assertCount(0, $hbb->related('structure'));

        $one = $merger->combine($hbb, $gene);
        self::assertSame(['ncbi.taxon:9606'], $one->related('organism')->keys());
        self::assertSame(['chebi:60344', 'chebi:58248'], $one->related('ligand')->keys(), 'united by relation');
        self::assertFalse($one->identifiers()->has($taxon));
    }

    public function testCombiningOneGivesItsSource(): void
    {
        $one = (new Merger())->combine(new Entity(Identifier::cid(962), 'Water', source: 'pubchem'));

        self::assertSame(['pubchem'], $one->sources);
        $this->expectException(\InvalidArgumentException::class);
        (new Merger())->combine();
    }
}
