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
        yield 'one of the catalogues ComCat keeps, its code long' => ['usgs:official20110311054624120_30', 'usgs.event:official20110311054624120_30'];
        yield 'not a USGS event: no network' => ['usgs:20110311', null];
        yield 'a fly gene' => ['FBgn0000008', 'ensembl.genomes:FBgn0000008'];
        yield 'a fly gene, its case as FlyBase writes it' => ['fbgn0000008', 'ensembl.genomes:FBgn0000008'];
        yield 'a fly transcript' => ['FBtr0071764', 'ensembl.genomes:FBtr0071764'];
        yield 'a worm gene' => ['WBGene00000001', 'ensembl.genomes:WBGene00000001'];
        yield 'a yeast gene' => ['yal001c', 'ensembl.genomes:YAL001C'];
        yield 'a yeast gene with its letter' => ['YIL177W-A', 'ensembl.genomes:YIL177W-A'];
        yield 'an Arabidopsis gene' => ['At5g10140', 'ensembl.genomes:AT5G10140'];
        yield 'an Arabidopsis transcript' => ['AT5G10140.1', 'ensembl.genomes:AT5G10140.1'];
        yield 'a rice gene' => ['Os01g0100100', 'ensembl.genomes:Os01g0100100'];
        yield 'a maize gene' => ['Zm00001eb000010', 'ensembl.genomes:Zm00001eb000010'];
        yield 'a wheat gene' => ['TraesCS1A02G000100', 'ensembl.genomes:TraesCS1A02G000100'];
        yield 'a fission yeast gene' => ['SPBC1105.02c', 'ensembl.genomes:SPBC1105.02c'];
        yield 'a Plasmodium gene' => ['PF3D7_0100100', 'ensembl.genomes:PF3D7_0100100'];
        yield 'an Anopheles gene' => ['AGAP000002', 'ensembl.genomes:AGAP000002'];
        yield 'another genome\'s id with its scheme' => ['ensembl.genomes:Solyc01g005000.3', 'ensembl.genomes:Solyc01g005000.3'];
        yield 'identifiers.org\'s Ensembl Plants' => ['ensembl.plant:AT1G73965', 'ensembl.genomes:AT1G73965'];
        yield 'an ENS id is not of the scheme' => ['ensembl.genomes:ENSG00000244734', null];
        yield 'an Ensembl page of a fly gene' => ['https://www.ensembl.org/id/FBgn0000008', 'ensembl.genomes:FBgn0000008'];
        yield 'an Ensembl page of a human gene, still' => ['https://www.ensembl.org/id/ENSG00000244734', 'ensembl:ENSG00000244734'];
        yield 'a Reactome pathway' => ['R-HSA-70171', 'reactome:R-HSA-70171'];
        yield 'a Reactome pathway, its version dropped' => ['r-hsa-70171.5', 'reactome:R-HSA-70171'];
        yield 'a Reactome small molecule' => ['reactome:R-ALL-29356', 'reactome:R-ALL-29356'];
        yield 'Reactome\'s page' => ['https://reactome.org/content/detail/R-HSA-70171', 'reactome:R-HSA-70171'];
        yield 'identifiers.org\'s Reactome' => ['https://identifiers.org/reactome:R-HSA-70171', 'reactome:R-HSA-70171'];
        yield 'not Reactome: no species' => ['reactome:R-70171', null];
        yield 'a STRING protein' => ['9606.ENSP00000269305', 'string:9606.ENSP00000269305'];
        yield 'a STRING protein of E. coli' => ['string:511145.b0001', 'string:511145.b0001'];
        yield 'STRING\'s page' => ['https://string-db.org/network/9606.ENSP00000269305', 'string:9606.ENSP00000269305'];
        yield 'not a STRING protein: a decimal number' => ['9606.5', null];
        yield 'an InterPro entry' => ['IPR000971', 'interpro:IPR000971'];
        yield 'InterPro\'s page' => ['https://www.ebi.ac.uk/interpro/entry/InterPro/IPR000971/', 'interpro:IPR000971'];
        yield 'a Pfam family, its version dropped' => ['PF00042.27', 'pfam:PF00042'];
        yield 'Pfam\'s page in InterPro' => ['https://www.ebi.ac.uk/interpro/entry/pfam/PF00042/', 'pfam:PF00042'];
        yield 'a GO term' => ['GO:0005829', 'go:0005829'];
        yield 'a GO term, OBO\'s short form' => ['GO_0005829', 'go:0005829'];
        yield 'QuickGO\'s page' => ['https://www.ebi.ac.uk/QuickGO/term/GO:0005829', 'go:0005829'];
        yield 'not a GO term: six digits' => ['GO:000582', null];
        yield 'an HGNC gene' => ['HGNC:4827', 'hgnc:4827'];
        yield 'HGNC\'s page' => ['https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/HGNC:4827', 'hgnc:4827'];
        yield 'an OMIM entry' => ['OMIM:141900', 'omim:141900'];
        yield 'a MIM number of a phenotype' => ['MIM:#603903', 'omim:603903'];
        yield 'OMIM\'s page' => ['https://omim.org/entry/141900', 'omim:141900'];
        yield 'a MIM number needs its scheme' => ['141900', null];
        yield 'an EFO term as Open Targets writes it' => ['EFO_0000305', 'efo:0000305'];
        yield 'a Mondo disease' => ['MONDO:0011382', 'mondo:0011382'];
        yield 'a Mondo disease as Open Targets writes it' => ['MONDO_0011382', 'mondo:0011382'];
        yield 'a Mondo disease\'s page' => ['https://monarchinitiative.org/MONDO:0011382', 'mondo:0011382'];
        yield 'an HPO term' => ['HP:0001250', 'hp:0001250'];
        yield 'an OLS page' => ['https://www.ebi.ac.uk/ols4/ontologies/efo/classes?obo_id=EFO:0000305', 'efo:0000305'];
        yield 'an Orphanet disease' => ['ORPHA:232', 'orphanet:232'];
        yield 'an Orphanet disease as Open Targets writes it' => ['Orphanet_232', 'orphanet:232'];
        yield 'Orphanet\'s page' => ['https://www.orpha.net/en/disease/detail/232', 'orphanet:232'];
        yield 'a ClinVar variation' => ['VCV000015333.71', 'clinvar:15333'];
        yield 'a ClinVar VariationID' => ['clinvar:15333', 'clinvar:15333'];
        yield 'ClinVar\'s page' => ['https://www.ncbi.nlm.nih.gov/clinvar/variation/15333/', 'clinvar:15333'];
        yield 'a dbSNP variant' => ['rs334', 'dbsnp:rs334'];
        yield 'dbSNP\'s page' => ['https://www.ncbi.nlm.nih.gov/snp/rs334', 'dbsnp:rs334'];
        yield 'a GWAS Catalog study' => ['GCST000854', 'gwas:GCST000854'];
        yield 'the GWAS Catalog\'s page' => ['https://www.ebi.ac.uk/gwas/studies/GCST000854', 'gwas:GCST000854'];
        yield 'a Gaia DR3 designation' => ['Gaia DR3 4472832130942575872', 'gaia:4472832130942575872'];
        yield 'a Gaia source_id with its scheme' => ['gaia:5853498713190525696', 'gaia:5853498713190525696'];
        yield 'a Gaia source_id needs its scheme' => ['4472832130942575872', null];
        yield 'not a Gaia source_id: beyond 64 bits' => ['gaia:99999999999999999999', null];
        yield 'an exoplanet of the archive' => ['exoplanet:51  Peg b', 'exoplanet:51 Peg b'];
        yield 'the archive\'s page' => ['https://exoplanetarchive.ipac.caltech.edu/overview/Proxima%20Cen', 'exoplanet:Proxima Cen'];
        yield 'a GHCN station' => ['ghcn:usw00094728', 'ghcn:USW00094728'];
        yield 'a GHCN station with a hyphen' => ['ghcn:BR00A6-0010', 'ghcn:BR00A6-0010'];
        yield 'a GHCN station as CDO writes it' => ['ghcnd:GHCND:FR000007190', 'ghcn:FR000007190'];
        yield 'a GHCN station needs its scheme' => ['USW00094728', null];
        yield 'nor is a word of eleven letters one' => ['CAFFEINATED', null];
        yield 'a WIGOS id' => ['0-20000-0-07190', 'wigos:0-20000-0-07190'];
        yield 'a WMO index' => ['wmo:07190', 'wigos:0-20000-0-07190'];
        yield 'an NWS station' => ['nws.station:knyc', 'nws.station:KNYC'];
        yield 'an NWS buoy' => ['nws.station:44069', 'nws.station:44069'];
        yield 'an EMSC event, by its shape' => ['20261004_0000156', 'emsc.event:20261004_0000156'];
        yield 'an EMSC event, by its prefix' => ['emsc:20261004_0000156', 'emsc.event:20261004_0000156'];
        yield 'not an EMSC event: a day too short' => ['emsc:2026104_0000156', null];
        yield 'the SeismicPortal\'s page of an event' => ['https://www.seismicportal.eu/eventdetails.html?unid=20261004_0000156', 'emsc.event:20261004_0000156'];
        yield 'a USGS water monitoring location, by its shape' => ['usgs-01646500', 'usgs.site:USGS-01646500'];
        yield 'a USGS monitoring location by its number' => ['usgs.site:01646500', 'usgs.site:USGS-01646500'];
        yield 'a USGS site\'s page' => ['https://waterdata.usgs.gov/monitoring-location/USGS-01646500/', 'usgs.site:USGS-01646500'];
        yield 'a USGS site\'s record in the Water Data API' => ['https://api.waterdata.usgs.gov/ogcapi/v1/collections/monitoring-locations/items/USGS-01646500?f=json', 'usgs.site:USGS-01646500'];
        yield 'a number alone is no USGS site' => ['01646500', null];
        yield 'a CO-OPS station' => ['coops:8518750', 'coops.station:8518750'];
        yield 'a CO-OPS station\'s page' => ['https://tidesandcurrents.noaa.gov/stationhome.html?id=8518750', 'coops.station:8518750'];
        yield 'a CO-OPS station\'s record in the metadata API' => ['https://api.tidesandcurrents.noaa.gov/mdapi/prod/webapi/stations/8518750.json', 'coops.station:8518750'];
        yield 'a DWD station, padded' => ['dwd:1420', 'dwd.station:01420'];
        yield 'a DWD station with a letter' => ['dwd.station:p0291', 'dwd.station:P0291'];
        yield 'GHCN\'s page' => ['https://www.ncei.noaa.gov/cdo-web/datasets/GHCND/stations/GHCND:USW00094728/detail', 'ghcn:USW00094728'];
        yield 'OSCAR\'s page' => ['https://oscar.wmo.int/surface/#/search/station/stationReportDetails/0-20000-0-07190', 'wigos:0-20000-0-07190'];
        yield 'the NWS\'s station' => ['https://api.weather.gov/stations/KNYC', 'nws.station:KNYC'];
        yield 'the NWS\'s page of a station' => ['https://www.weather.gov/wrh/timeseries?site=KNYC', 'nws.station:KNYC'];
        yield 'a bare number needs its scheme' => ['2519', null];
        yield 'a name is no identifier' => ['caffeine', null];
        yield 'nor a SMILES' => ['CN1C=NC2=C1C(=O)N(C(=O)N2C)C', null];
        yield 'PubChem\'s page' => ['https://pubchem.ncbi.nlm.nih.gov/compound/2519', 'pubchem.cid:2519'];
        yield 'ChEBI\'s page' => ['https://www.ebi.ac.uk/chebi/searchId.do?chebiId=CHEBI:27732', 'chebi:27732'];
        yield 'ChEBI 2.0\'s page' => ['https://www.ebi.ac.uk/chebi/CHEBI:27732', 'chebi:27732'];
        yield 'ChEMBL\'s page' => ['https://www.ebi.ac.uk/chembl/explore/compound/CHEMBL113', 'chembl:CHEMBL113'];
        yield 'Wikidata\'s page' => ['https://www.wikidata.org/wiki/Q60235', 'wikidata:Q60235'];
        yield 'Wikidata\'s entity URI' => ['http://www.wikidata.org/entity/Q60235', 'wikidata:Q60235'];
        yield 'UniProt\'s page' => ['https://www.uniprot.org/uniprotkb/P69905/entry', 'uniprot:P69905'];
        yield 'a GBIF species' => ['https://www.gbif.org/species/2435099', 'gbif:2435099'];
        yield 'a GBIF taxon of the Catalogue of Life eXtended Release' => ['https://www.gbif.org/taxon/4CGXP', 'gbif:4CGXP'];
        yield 'its key, lower case' => ['gbif:4cgxp', 'gbif:4CGXP'];
        yield 'a legacy GBIF key' => ['gbif:5219404', 'gbif:5219404'];
        yield 'not a GBIF key: a vowel' => ['gbif:4CGXA', null];
        yield 'nor: seven characters' => ['gbif:4CGXPBB', null];
        yield 'a GBIF occurrence' => ['gbif.occurrence:5938104699', 'gbif.occurrence:5938104699'];
        yield 'its page' => ['https://www.gbif.org/occurrence/5938104699', 'gbif.occurrence:5938104699'];
        yield 'an iNaturalist taxon' => ['https://www.inaturalist.org/taxa/42007-Puma-concolor', 'inaturalist:42007'];
        yield 'an iNaturalist observation' => ['inaturalist.observation:332912788', 'inaturalist.observation:332912788'];
        yield 'an iNaturalist observation\'s page' => ['https://www.inaturalist.org/observations/332912788', 'inaturalist.observation:332912788'];
        yield 'an Ensembl protein' => ['ENSP00000333994', 'ensembl:ENSP00000333994'];
        yield 'a mouse gene in Ensembl' => ['ENSMUSG00000052305.9', 'ensembl:ENSMUSG00000052305'];
        yield 'a PDB entry\'s extended id' => ['pdb:pdb_00004hhb', 'pdb:pdb_00004hhb'];
        yield 'a PDB entry\'s page' => ['https://www.rcsb.org/structure/4HHB', 'pdb:4HHB'];
        yield 'a USGS event page' => ['https://earthquake.usgs.gov/earthquakes/eventpage/us6000u24c/executive', 'usgs.event:us6000u24c'];
        yield 'the page of a long one' => ['https://earthquake.usgs.gov/earthquakes/eventpage/iscgemsup16957911', 'usgs.event:iscgemsup16957911'];
        yield 'CAS Common Chemistry' => ['https://commonchemistry.cas.org/detail?cas_rn=58-08-2', 'cas:58-08-2'];
        yield 'identifiers.org' => ['https://identifiers.org/uniprot:P69905', 'uniprot:P69905'];
        yield 'NCBI Taxonomy' => ['https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id=9606', 'ncbi.taxon:9606'];
        yield 'SIMBAD\'s page' => ['https://simbad.cds.unistra.fr/simbad/sim-id?Ident=M%2031', 'simbad:M 31'];
        yield 'JPL\'s lookup' => ['https://ssd.jpl.nasa.gov/tools/sbdb_lookup.html#/?sstr=433', 'jpl.sbdb:433'];
        yield 'a minor planet at the MPC' => ['mpc:(433)', 'mpc:433'];
        yield 'an object of NED' => ['ned:Messier  031', 'ned:Messier 031'];
        yield 'NED\'s page' => ['https://ned.ipac.caltech.edu/byname?objname=3C%20273', 'ned:3C 273'];
        yield 'a DONKI event, by its shape' => ['2026-10-04T06:13:00-FLR-001', 'donki:2026-10-04T06:13:00-FLR-001'];
        yield 'a DONKI event, its kind upper-cased' => ['donki:2026-10-10t15:00:00-gst-001', 'donki:2026-10-10T15:00:00-GST-001'];
        yield 'a launch of Launch Library 2' => ['launch:DF88CFBF-F504-4E11-8370-E33DD6280B5B', 'launchlibrary:df88cfbf-f504-4e11-8370-e33dd6280b5b'];
        yield 'a launch\'s API record' => ['https://ll.thespacedevs.com/2.3.0/launches/df88cfbf-f504-4e11-8370-e33dd6280b5b/', 'launchlibrary:df88cfbf-f504-4e11-8370-e33dd6280b5b'];
        yield 'a UUID alone is no launch' => ['df88cfbf-f504-4e11-8370-e33dd6280b5b', null];
        yield 'a comet at the MPC' => ['mpc:C/2020  F3', 'mpc:C/2020 F3'];
        yield 'the MPC\'s page' => ['https://minorplanetcenter.net/db_search/show_object?object_id=2024+YR4', 'mpc:2024 YR4'];
        yield 'a KEGG reaction' => ['rn:R00200', 'kegg.reaction:R00200'];
        yield 'a KEGG reaction\'s page' => ['https://www.kegg.jp/entry/R00200', 'kegg.reaction:R00200'];
        yield 'a KEGG pathway' => ['path:hsa00010', 'kegg.pathway:hsa00010'];
        yield 'a KEGG reference pathway\'s map' => ['https://www.kegg.jp/pathway/map00010', 'kegg.pathway:map00010'];
        yield 'a KEGG compound\'s page is still a compound' => ['https://www.kegg.jp/entry/C07481', 'kegg:C07481'];
        yield 'an enzyme in KEGG' => ['https://www.kegg.jp/entry/ec:2.7.1.40', 'ec:2.7.1.40'];
        yield 'an EC number' => ['EC 2.7.1.40', 'ec:2.7.1.40'];
        yield 'an incomplete EC number' => ['EC:2.7.1.-', 'ec:2.7.1.-'];
        yield 'a preliminary EC number' => ['ec:3.5.1.n3', 'ec:3.5.1.n3'];
        yield 'not an EC number: no class 8' => ['EC 8.1.1.1', null];
        yield 'ExPASy\'s ENZYME page' => ['https://enzyme.expasy.org/EC/2.7.1.40', 'ec:2.7.1.40'];
        yield 'a Rhea reaction' => ['RHEA:18157', 'rhea:18157'];
        yield 'a Rhea reaction\'s page' => ['https://www.rhea-db.org/rhea/18160', 'rhea:18160'];
        yield 'a COD structure' => ['cod:1000000', 'cod:1000000'];
        yield 'a COD structure\'s page' => ['https://www.crystallography.net/cod/1000000.html', 'cod:1000000'];
        yield 'a COD structure\'s CIF' => ['https://www.crystallography.net/cod/9008565.cif', 'cod:9008565'];
        yield 'not a COD id: six digits' => ['cod:100000', null];
        yield 'a Materials Project id' => ['mp-149', 'materialsproject:mp-149'];
        yield 'a Materials Project page' => ['https://materialsproject.org/materials/mp-149/', 'materialsproject:mp-149'];
        yield 'an OQMD entry\'s page' => ['https://oqmd.org/materials/entry/6', 'oqmd:6'];
        yield 'a CODATA constant' => ['codata:mp', 'codata:mp'];
        yield 'NIST\'s page of one' => ['https://physics.nist.gov/cgi-bin/cuu/Value?mp', 'codata:mp'];
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
        self::assertSame(Domain::TAXONOMY, Scheme::GBIF_OCCURRENCE->domain());
        self::assertSame('https://www.gbif.org/occurrence/5938104699', Identifier::of(Scheme::GBIF_OCCURRENCE, '5938104699')->url());
        self::assertSame(Domain::TAXONOMY, Scheme::INATURALIST_OBSERVATION->domain());
        self::assertSame('https://www.inaturalist.org/observations/332912788', Identifier::of(Scheme::INATURALIST_OBSERVATION, '332912788')->url());
        self::assertSame(Domain::ASTRONOMY, Scheme::SIMBAD->domain());
        self::assertSame(Domain::EARTH, Scheme::USGS_EVENT->domain());
        self::assertSame([Domain::EARTH, Domain::EARTH, Domain::EARTH, Domain::EARTH], [Scheme::EMSC_EVENT->domain(), Scheme::USGS_SITE->domain(), Scheme::COOPS_STATION->domain(), Scheme::DWD_STATION->domain()]);
        self::assertSame('https://www.seismicportal.eu/eventdetails.html?unid=20261004_0000156', Identifier::of(Scheme::EMSC_EVENT, '20261004_0000156')->url());
        self::assertSame('https://waterdata.usgs.gov/monitoring-location/USGS-01646500/', Identifier::of(Scheme::USGS_SITE, '01646500')->url());
        self::assertSame('https://tidesandcurrents.noaa.gov/stationhome.html?id=8518750', Identifier::of(Scheme::COOPS_STATION, '8518750')->url());
        self::assertNull(Identifier::of(Scheme::DWD_STATION, '01420')->url());
        self::assertSame([Domain::BIOLOGY, Domain::ASTRONOMY, Domain::ASTRONOMY], [Scheme::REACTOME->domain(), Scheme::GAIA->domain(), Scheme::EXOPLANET->domain()]);
        self::assertSame('https://reactome.org/content/detail/R-HSA-70171', Identifier::of(Scheme::REACTOME, 'R-HSA-70171')->url());
        self::assertSame('https://exoplanetarchive.ipac.caltech.edu/overview/51%20Peg%20b', Identifier::of(Scheme::EXOPLANET, '51 Peg b')->url());
        self::assertSame(Domain::BIOLOGY, Scheme::MONDO->domain());
        self::assertSame(['GO:0005829', 'HGNC:4827', 'OMIM:141900', 'EFO:0000305', 'MONDO:0011382', 'ORPHA:232', 'HP:0001250', 'VCV000015333', 'rs334', '9606.ENSP00000269305'], [
            Identifier::of(Scheme::GO, 'GO:0005829')->display(),
            Identifier::of(Scheme::HGNC, '4827')->display(), Identifier::of(Scheme::OMIM, '141900')->display(), Identifier::of(Scheme::EFO, '0000305')->display(),
            Identifier::of(Scheme::MONDO, 'MONDO_0011382')->display(), Identifier::of(Scheme::ORPHANET, '232')->display(), Identifier::of(Scheme::HP, '0001250')->display(),
            Identifier::of(Scheme::CLINVAR, '15333')->display(), Identifier::of(Scheme::DBSNP, 'RS334')->display(), Identifier::of(Scheme::STRING, '9606.ENSP00000269305')->display(),
        ]);
        self::assertSame('https://www.ebi.ac.uk/QuickGO/term/GO:0005829', Identifier::of(Scheme::GO, '0005829')->url());
        self::assertSame('https://www.ncbi.nlm.nih.gov/clinvar/variation/15333/', Identifier::of(Scheme::CLINVAR, 'VCV000015333')->url());
        self::assertSame([Domain::CHEMISTRY, Domain::BIOLOGY, Domain::CHEMISTRY, Domain::BIOLOGY, Domain::CHEMISTRY, Domain::CHEMISTRY, Domain::CHEMISTRY, Domain::OTHER], array_map(static fn (Scheme $s) => $s->domain(), [Scheme::KEGG_REACTION, Scheme::KEGG_PATHWAY, Scheme::RHEA, Scheme::EC, Scheme::COD, Scheme::MATERIALS_PROJECT, Scheme::OQMD, Scheme::CODATA]));
        self::assertSame(['RHEA:18157', 'EC 2.7.1.40', 'mp-149', 'mp'], [Identifier::of(Scheme::RHEA, '18157')->display(), Identifier::of(Scheme::EC, '2.7.1.40')->display(), Identifier::of(Scheme::MATERIALS_PROJECT, 'MP-149')->display(), Identifier::of(Scheme::CODATA, 'mp')->display()]);
        self::assertSame('https://enzyme.expasy.org/EC/2.7.1.-', Identifier::of(Scheme::EC, '2.7.1.-')->url());
        self::assertSame('https://www.kegg.jp/entry/hsa00010', Identifier::of(Scheme::KEGG_PATHWAY, 'HSA00010')->url());
        self::assertSame('https://physics.nist.gov/cgi-bin/cuu/Value?mp', Identifier::of(Scheme::CODATA, 'mp')->url());
        self::assertNotSame(Identifier::of(Scheme::CODATA, 'Kj')->key(), Identifier::of(Scheme::CODATA, 'kj')->key(), 'NIST\'s short names keep their case');
        self::assertNull(Scheme::WIKIDATA->domain());
        foreach (Scheme::cases() as $scheme) {
            self::assertNotSame('', $scheme->label());
        }

        self::assertSame('https://pubchem.ncbi.nlm.nih.gov/compound/2519', Identifier::cid(2519)->url());
        self::assertSame('https://www.wikidata.org/wiki/Q60235', Identifier::wikidata('q60235')->url());
        self::assertSame('https://www.ebi.ac.uk/chebi/CHEBI:27732', Identifier::chebi('CHEBI:27732')->url(), 'ChEBI 2.0\'s page: the old searchId.do answers 301 to it');
        self::assertSame('https://simbad.cds.unistra.fr/simbad/sim-id?Ident=M%2031', Identifier::of('simbad', 'M 31')->url());
        self::assertNull(Identifier::inchikey('RYYVLZVUVIJVGH-UHFFFAOYSA-N')->url());
        self::assertSame('CHEBI:27732', Identifier::chebi('CHEBI:27732')->display());
        self::assertSame('CID 2519', Identifier::cid('2519')->display());
        foreach (Scheme::cases() as $scheme) {
            // Every scheme's url() is a page of its base or null: none is left out of the match.
            $url = Identifier::tryOf($scheme, match ($scheme) {
                Scheme::INCHIKEY => 'RYYVLZVUVIJVGH-UHFFFAOYSA-N', Scheme::INCHI => 'InChI=1S/H2O/h1H2', Scheme::CAS => '7732-18-5',
                Scheme::CHEMBL => 'CHEMBL1098659', Scheme::UNII => '059QF0KO0R', Scheme::DSSTOX => 'DTXSID6026296', Scheme::KEGG => 'C00001',
                Scheme::UNIPROT => 'P69905', Scheme::ENSEMBL => 'ENSG00000141510', Scheme::PDB => '1A3N', Scheme::SIMBAD => 'M 31', Scheme::JPL_SBDB => '433', Scheme::MPC => '433', Scheme::NED => 'Messier 031', Scheme::DONKI => '2026-10-04T06:13:00-FLR-001', Scheme::LAUNCH_LIBRARY => 'df88cfbf-f504-4e11-8370-e33dd6280b5b',
                Scheme::USGS_EVENT => 'us6000u24c', Scheme::WIKIDATA => 'Q283', Scheme::GHCN => 'USW00094728', Scheme::NWS_STATION => 'KNYC', Scheme::REACTOME => 'R-HSA-70171', Scheme::ENSEMBL_GENOMES => 'FBgn0000008', Scheme::GAIA => '4472832130942575872', Scheme::EXOPLANET => '51 Peg b',
                Scheme::STRING => '9606.ENSP00000269305', Scheme::INTERPRO => 'IPR000971', Scheme::PFAM => 'PF00042', Scheme::GO, Scheme::EFO, Scheme::MONDO, Scheme::HP => '0005829', Scheme::OMIM => '141900', Scheme::DBSNP => 'rs334', Scheme::GWAS => 'GCST000854',
                Scheme::KEGG_REACTION => 'R00200', Scheme::KEGG_PATHWAY => 'map00010', Scheme::RHEA => '18157', Scheme::EC => '2.7.1.40', Scheme::COD => '1000000', Scheme::MATERIALS_PROJECT => 'mp-149', Scheme::CODATA => 'mp',
                Scheme::OBIS_OCCURRENCE => '00007235-5211-4633-98bb-0f8f9ad53e0f', Scheme::EBIRD => 'amerob', Scheme::EBIRD_CHECKLIST => 'S123456789', default => '962',
            })?->url();
            self::assertTrue(null === $url || str_starts_with($url, 'https://'), $scheme->value);
        }
    }

    public function testEverySchemeHasADomain(): void
    {
        foreach (Scheme::cases() as $scheme) {
            $scheme->domain();
        }
        self::assertSame(Domain::OTHER, Scheme::LAUNCH_LIBRARY->domain());
        self::assertNull(Scheme::WIKIDATA->domain());
    }

    public function testAnEnsemblGenomesIdSaysItsGenome(): void
    {
        self::assertSame('drosophila_melanogaster', Identifier::ensemblSpecies(Identifier::of(Scheme::ENSEMBL_GENOMES, 'FBgn0000008')));
        self::assertSame('arabidopsis_thaliana', Identifier::ensemblSpecies('AT5G10140.1'));
        self::assertSame('schizosaccharomyces_pombe', Identifier::ensemblSpecies('SPAC1002.01'));
        self::assertNull(Identifier::ensemblSpecies('Solyc01g005000.3'), 'not a known genome');
        self::assertSame('https://www.ensembl.org/id/AT5G10140', Identifier::of(Scheme::ENSEMBL_GENOMES, 'AT5G10140')->url());
        self::assertSame(Domain::BIOLOGY, Scheme::ENSEMBL_GENOMES->domain());
    }

    public function testAGbifKeyInEitherForm(): void
    {
        $new = Identifier::of(Scheme::GBIF, '4cgxp');
        $legacy = Identifier::of(Scheme::GBIF, '05219404');

        self::assertSame(['4CGXP', '5219404'], [$new->value, $legacy->value]);
        self::assertSame('https://www.gbif.org/taxon/4CGXP', $new->url());
        self::assertSame('https://www.gbif.org/species/5219404', $legacy->url(), 'GBIF sends a legacy key\'s page to the new one');
        self::assertSame('N', Identifier::of(Scheme::GBIF, 'N')->value, 'Animalia: one character');
        self::assertSame('gbif:59R5F', Identifier::tryOf('gbif', 'GBIF:59R5F')?->key());
        foreach (['0', '1A2B', '4CGX0', 'ABCDE', '', '12345678901'] as $value) {
            self::assertNull(Identifier::tryOf(Scheme::GBIF, $value), $value);
        }
    }

    public function testTheTaxonomiesOfBiodiversityAndPalaeontology(): void
    {
        // WoRMS and ITIS: a number, or the LSID each gives its names, read by its shape.
        self::assertSame('worms:137094', Identifier::parse('urn:lsid:marinespecies.org:taxname:137094')?->key());
        self::assertSame('worms:137094', Identifier::parse('aphia:137094')?->key());
        self::assertSame('worms:137094', Identifier::parse('https://www.marinespecies.org/aphia.php?p=taxdetails&id=137094')?->key());
        self::assertSame('itis:180438', Identifier::parse('urn:lsid:itis.gov:itis_tsn:180438')?->key());
        self::assertSame('itis:180438', Identifier::parse('tsn:180438')?->key());
        self::assertSame('itis:180438', Identifier::parse('https://www.itis.gov/servlet/SingleRpt/SingleRpt?search_topic=TSN&search_value=180438')?->key());
        self::assertNull(Identifier::parse('137094'), 'a bare number needs its scheme');
        // OBIS: an occurrence's UUID, lower case.
        self::assertSame('obis.occurrence:00007235-5211-4633-98bb-0f8f9ad53e0f', Identifier::tryOf('obis.occurrence', '00007235-5211-4633-98BB-0F8F9AD53E0F')?->key());
        self::assertSame('obis.occurrence:00007235-5211-4633-98bb-0f8f9ad53e0f', Identifier::parse('https://api.obis.org/v3/occurrence/00007235-5211-4633-98bb-0f8f9ad53e0f')?->key());
        self::assertNull(Identifier::tryOf(Scheme::OBIS_OCCURRENCE, '1406_14803'), 'the publisher\'s occurrenceID is not OBIS\'s id');
        // The Catalogue of Life: its ids, the same as GBIF's keys since June 2026, never read by their shape alone.
        self::assertSame('col:4CGXP', Identifier::parse('col:4cgxp')?->key());
        self::assertSame('col:4CGXP', Identifier::parse('https://www.catalogueoflife.org/data/taxon/4CGXP')?->key());
        self::assertSame('col:34JWM', Identifier::parse('https://www.checklistbank.org/dataset/3LXR/taxon/34JWM')?->key());
        self::assertSame('https://www.catalogueoflife.org/data/taxon/4CGXP', Identifier::of(Scheme::COL, '4CGXP')->url());
        self::assertNull(Identifier::parse('4CGXP'));
        // The Paleobiology Database: txn: and occ:, as its API writes them.
        self::assertSame(['pbdb:54833', 'pbdb.occurrence:139292'], [Identifier::parse('txn:54833')?->key(), Identifier::parse('occ:139292')?->key()]);
        self::assertSame('pbdb:54833', Identifier::parse('https://paleobiodb.org/classic/basicTaxonInfo?taxon_no=54833')?->key());
        // eBird and the IUCN Red List.
        self::assertSame(['ebird:amerob', 'ebird.checklist:S123456789', 'iucn:134817215'], [Identifier::of(Scheme::EBIRD, 'AMEROB')->key(), Identifier::of(Scheme::EBIRD_CHECKLIST, 's123456789')->key(), Identifier::parse('iucn:134817215')?->key()]);
        self::assertSame(['ebird:amerob', 'ebird.checklist:S123456789', 'iucn:134817215'], [Identifier::parse('https://ebird.org/species/amerob')?->key(), Identifier::parse('https://ebird.org/checklist/S123456789')?->key(), Identifier::parse('https://www.iucnredlist.org/species/134817215/1')?->key()]);
        foreach ([Scheme::WORMS, Scheme::OBIS_OCCURRENCE, Scheme::ITIS, Scheme::COL, Scheme::PBDB, Scheme::PBDB_OCCURRENCE, Scheme::EBIRD, Scheme::EBIRD_CHECKLIST, Scheme::IUCN] as $scheme) {
            self::assertSame(Domain::TAXONOMY, $scheme->domain(), $scheme->value);
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
