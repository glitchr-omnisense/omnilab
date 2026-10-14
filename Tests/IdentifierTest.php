<?php

namespace Omnilab\Tests;

use Omnilab\Model\Domain;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Scheme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    /** @return iterable<string, array{string, string|null}> */
    public static function parsed(): iterable
    {
        yield 'a ChEBI id as ChEBI writes it' => ['CHEBI:27732', 'chebi:27732'];
        yield 'a scheme and a value' => ['pubchem.cid:2519', 'pubchem.cid:2519'];
        yield 'identifiers.org\'s prefix' => ['pubchem.compound:2519', 'pubchem.cid:2519'];
        yield 'CID and a number' => ['CID 2519', 'pubchem.cid:2519'];
        yield 'an InChIKey' => ['RYYVLZVUVIJVGH-UHFFFAOYSA-N', 'inchikey:RYYVLZVUVIJVGH-UHFFFAOYSA-N'];
        yield 'an InChI' => ['InChI=1S/C8H10N4O2/c1-10-4-9-6-5(10)7(13)12(3)8(14)11(6)2/h4H,1-3H3', 'inchi:InChI=1S/C8H10N4O2/c1-10-4-9-6-5(10)7(13)12(3)8(14)11(6)2/h4H,1-3H3'];
        yield 'a CAS number' => ['58-08-2', 'cas:58-08-2'];
        yield 'a CAS number with a wrong check digit' => ['58-08-3', null];
        yield 'an EC number is not a CAS number' => ['200-362-1', null];
        yield 'ChEMBL' => ['chembl113', 'chembl:CHEMBL113'];
        yield 'UNII as PubChem writes it' => ['UNII-3G6A5W338E', 'unii:3G6A5W338E'];
        yield 'DSSTox' => ['DTXSID0020232', 'dsstox:DTXSID0020232'];
        yield 'KEGG with its prefix' => ['cpd:C07481', 'kegg:C07481'];
        yield 'a Wikidata item' => ['Q60235', 'wikidata:Q60235'];
        yield 'an Ensembl gene, its version dropped' => ['ENSG00000141510.18', 'ensembl:ENSG00000141510'];
        yield 'UniProt needs its scheme' => ['P69905', null];
        yield 'with it' => ['uniprot:p69905', 'uniprot:P69905'];
        yield 'a gene by identifiers.org\'s prefix' => ['ncbigene:7157', 'ncbi.gene:7157'];
        yield 'a taxon' => ['taxonomy:9606', 'ncbi.taxon:9606'];
        yield 'a PDB structure' => ['pdb:1a3n', 'pdb:1A3N'];
        yield 'a SIMBAD object, spaces collapsed' => ['simbad:M  31', 'simbad:M 31'];
        yield 'a USGS event' => ['usgs:US6000U24C', 'usgs.event:us6000u24c'];
        yield 'a bare number needs its scheme' => ['2519', null];
        yield 'a name is no identifier' => ['caffeine', null];
        yield 'nor a SMILES' => ['CN1C=NC2=C1C(=O)N(C(=O)N2C)C', null];
        yield 'PubChem\'s page' => ['https://pubchem.ncbi.nlm.nih.gov/compound/2519', 'pubchem.cid:2519'];
        yield 'ChEBI\'s page' => ['https://www.ebi.ac.uk/chebi/searchId.do?chebiId=CHEBI:27732', 'chebi:27732'];
        yield 'ChEMBL\'s page' => ['https://www.ebi.ac.uk/chembl/explore/compound/CHEMBL113', 'chembl:CHEMBL113'];
        yield 'Wikidata\'s page' => ['https://www.wikidata.org/wiki/Q60235', 'wikidata:Q60235'];
        yield 'Wikidata\'s entity URI' => ['http://www.wikidata.org/entity/Q60235', 'wikidata:Q60235'];
        yield 'UniProt\'s page' => ['https://www.uniprot.org/uniprotkb/P69905/entry', 'uniprot:P69905'];
        yield 'a GBIF species' => ['https://www.gbif.org/species/2435099', 'gbif:2435099'];
        yield 'an iNaturalist taxon' => ['https://www.inaturalist.org/taxa/42007-Puma-concolor', 'inaturalist:42007'];
        yield 'a USGS event page' => ['https://earthquake.usgs.gov/earthquakes/eventpage/us6000u24c/executive', 'usgs.event:us6000u24c'];
        yield 'CAS Common Chemistry' => ['https://commonchemistry.cas.org/detail?cas_rn=58-08-2', 'cas:58-08-2'];
        yield 'identifiers.org' => ['https://identifiers.org/uniprot:P69905', 'uniprot:P69905'];
        yield 'NCBI Taxonomy' => ['https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=9606', 'ncbi.taxon:9606'];
        yield 'SIMBAD\'s page' => ['https://simbad.cds.unistra.fr/simbad/sim-id?Ident=M%2031', 'simbad:M 31'];
        yield 'JPL\'s lookup' => ['https://ssd.jpl.nasa.gov/tools/sbdb_lookup.html#/?sstr=433', 'jpl.sbdb:433'];
        yield 'an unknown site' => ['https://example.org/compound/2519', null];
    }

    #[DataProvider('parsed')]
    public function testParse(string $input, ?string $key): void
    {
        self::assertSame($key, Identifier::parse($input)?->key());
    }

    public function testEachSchemeSaysItsDomainAndResolves(): void
    {
        self::assertSame(Domain::CHEMISTRY, Scheme::INCHIKEY->domain());
        self::assertSame(Domain::BIOLOGY, Scheme::UNIPROT->domain());
        self::assertSame(Domain::TAXONOMY, Scheme::GBIF->domain());
        self::assertSame(Domain::ASTRONOMY, Scheme::SIMBAD->domain());
        self::assertSame(Domain::EARTH, Scheme::USGS_EVENT->domain());
        self::assertNull(Scheme::WIKIDATA->domain());
        foreach (Scheme::cases() as $scheme) {
            self::assertNotSame('', $scheme->label());
        }

        self::assertSame('https://pubchem.ncbi.nlm.nih.gov/compound/2519', Identifier::cid(2519)->url());
        self::assertSame('https://www.wikidata.org/wiki/Q60235', Identifier::wikidata('q60235')->url());
        self::assertSame('https://simbad.cds.unistra.fr/simbad/sim-id?Ident=M%2031', Identifier::of('simbad', 'M 31')->url());
        self::assertNull(Identifier::inchikey('RYYVLZVUVIJVGH-UHFFFAOYSA-N')->url());
        self::assertSame('CHEBI:27732', Identifier::chebi('CHEBI:27732')->display());
        self::assertSame('CID 2519', Identifier::cid('2519')->display());
        foreach (Scheme::cases() as $scheme) {
            // Every scheme's url() is a page of its base or null: none is left out of the match.
            $url = Identifier::tryOf($scheme, match ($scheme) {
                Scheme::INCHIKEY => 'RYYVLZVUVIJVGH-UHFFFAOYSA-N', Scheme::INCHI => 'InChI=1S/H2O/h1H2', Scheme::CAS => '7732-18-5',
                Scheme::CHEMBL => 'CHEMBL1098659', Scheme::UNII => '059QF0KO0R', Scheme::DSSTOX => 'DTXSID6026296', Scheme::KEGG => 'C00001',
                Scheme::UNIPROT => 'P69905', Scheme::ENSEMBL => 'ENSG00000141510', Scheme::PDB => '1A3N', Scheme::SIMBAD => 'M 31', Scheme::JPL_SBDB => '433',
                Scheme::USGS_EVENT => 'us6000u24c', Scheme::WIKIDATA => 'Q283', default => '962',
            })?->url();
            self::assertTrue(null === $url || str_starts_with($url, 'https://'), $scheme->value);
        }
    }

    public function testOfThrowsTryOfDoesNot(): void
    {
        self::assertNull(Identifier::tryOf('cas', '58-08-3'));
        self::assertNull(Identifier::tryOf('nope', '1'));
        self::assertSame('cas:7732-18-5', Identifier::cas('7732-18-5')->key(), 'water');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"58-08-3" is not a valid CAS RN.');
        Identifier::cas('58-08-3');
    }

    public function testIdentifiersKeepEachOnceAndRoundTrip(): void
    {
        $ids = Identifiers::of(Identifier::cid(2519), Identifier::parse('CHEBI:27732'), null, Identifier::parse('chebi:27732'), Identifier::parse('Q60235'));

        self::assertCount(3, $ids);
        self::assertSame('27732', $ids->value(Scheme::CHEBI));
        self::assertTrue($ids->has(Identifier::wikidata('Q60235')));
        self::assertSame(['pubchem.cid' => ['2519'], 'chebi' => ['27732'], 'wikidata' => ['Q60235']], $ids->toArray());
        self::assertEquals($ids, Identifiers::fromArray($ids->toArray()));
    }
}
