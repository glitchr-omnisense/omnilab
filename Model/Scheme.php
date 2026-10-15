<?php

namespace Omnilab\Model;

/**
 * The kinds of identifiers the science bases give a thing, named as the
 * bases themselves and identifiers.org name them. Each says the domain it
 * belongs to (domain()), null for the general ones (Wikidata).
 */
enum Scheme: string
{
    /** A PubChem compound: 2519 (caffeine). */
    case PUBCHEM_CID = 'pubchem.cid';
    /** ChEBI, the EBI's chemical entities of biological interest: 27732, written CHEBI:27732. */
    case CHEBI = 'chebi';
    /** ChEMBL, bioactive molecules: CHEMBL113. */
    case CHEMBL = 'chembl';
    /** The hashed InChI: RYYVLZVUVIJVGH-UHFFFAOYSA-N. */
    case INCHIKEY = 'inchikey';
    /** The IUPAC International Chemical Identifier itself: InChI=1S/C8H10N4O2/... */
    case INCHI = 'inchi';
    /** A CAS Registry Number: 58-08-2, its check digit checked. */
    case CAS = 'cas';
    /** The FDA's Unique Ingredient Identifier: 3G6A5W338E. */
    case UNII = 'unii';
    /** The EPA's DSSTox substance (CompTox dashboard): DTXSID0020232. */
    case DSSTOX = 'dsstox';
    /** A KEGG compound (C07481) or drug (D00528). */
    case KEGG = 'kegg';
    /** A KEGG reaction: R00200 (pyruvate kinase's). */
    case KEGG_REACTION = 'kegg.reaction';
    /** A KEGG pathway map, reference (map00010) or of an organism (hsa00010), or its KO, enzyme or reaction view (ko00010, ec00010, rn00010). */
    case KEGG_PATHWAY = 'kegg.pathway';
    /** A Rhea biochemical reaction, its number: 18157, written RHEA:18157 (any of a reaction's four ids: the master, its two directions, both). */
    case RHEA = 'rhea';
    /** An enzyme's EC number of the IUBMB's Enzyme Nomenclature, complete or not: 2.7.1.40 (pyruvate kinase), 2.7.1.-, written EC 2.7.1.40. */
    case EC = 'ec';
    /** A crystal structure of the Crystallography Open Database, its seven digits: 1000000 (Wikidata's P9824). */
    case COD = 'cod';
    /** A material of the Materials Project, its computed structure: mp-149 (silicon; Wikidata's P9902). */
    case MATERIALS_PROJECT = 'materialsproject';
    /** An entry of the Open Quantum Materials Database, its number: 6 (OQMD's own id of a computed structure). */
    case OQMD = 'oqmd';
    /** A fundamental physical constant of CODATA, by NIST's short name: mp (the proton mass), c, h (Wikidata's P1645). */
    case CODATA = 'codata';
    /** A UniProt protein: P69905. */
    case UNIPROT = 'uniprot';
    /** An NCBI Gene record: 7157 (TP53, human). */
    case NCBI_GENE = 'ncbi.gene';
    /** An NCBI Taxonomy node: 9606 (Homo sapiens). */
    case NCBI_TAXON = 'ncbi.taxon';
    /** An Ensembl gene, transcript or protein, without its version: ENSG00000141510. */
    case ENSEMBL = 'ensembl';
    /**
     * An Ensembl gene, transcript or protein of a genome Ensembl annotates
     * under its community's ids (FlyBase, WormBase, SGD, Araport,
     * PomBase...): FBgn0000008, AT5G10140, YAL001C.
     */
    case ENSEMBL_GENOMES = 'ensembl.genomes';
    /** A Protein Data Bank structure: 1A3N. */
    case PDB = 'pdb';
    /**
     * A GBIF taxon: the key of the Catalogue of Life eXtended Release, GBIF's
     * taxonomy since June 2026 (4CGXP, Panthera leo), or the number of the
     * legacy GBIF Backbone (5219404, the same lion).
     */
    case GBIF = 'gbif';
    /** A GBIF occurrence: one record of an organism at a place and a time, 5938104699. */
    case GBIF_OCCURRENCE = 'gbif.occurrence';
    /** An iNaturalist taxon: 42007 (Puma concolor). */
    case INATURALIST = 'inaturalist';
    /** An iNaturalist observation: one record of an organism by a person at a place and a time, 332912788. */
    case INATURALIST_OBSERVATION = 'inaturalist.observation';
    /** An object of SIMBAD, the CDS astronomical database, by its main identifier: M 31. */
    case SIMBAD = 'simbad';
    /** A small body of JPL's database (asteroid, comet): 433, 1P/Halley, 2004 MN4. */
    case JPL_SBDB = 'jpl.sbdb';
    /** A USGS earthquake event (ComCat): us6000u24c, official20110311054624120_30. */
    case USGS_EVENT = 'usgs.event';
    /** A Reactome pathway, reaction or physical entity, by its stable identifier without its version: R-HSA-70171 (glycolysis, human). */
    case REACTOME = 'reactome';
    /** A protein of STRING, the network of protein interactions, by its STRING id: the NCBI taxon, a dot, the protein's id in its genome (9606.ENSP00000269305, TP53). */
    case STRING = 'string';
    /** An InterPro entry (family, domain, site, repeat, homologous superfamily): IPR000971. */
    case INTERPRO = 'interpro';
    /** A Pfam family or domain, one of InterPro's member databases: PF00042. */
    case PFAM = 'pfam';
    /** A Gene Ontology term (function, process, component), its number: 0005829, written GO:0005829. */
    case GO = 'go';
    /** A human gene of the HUGO Gene Nomenclature Committee, its number: 4827, written HGNC:4827 (HBB). */
    case HGNC = 'hgnc';
    /** An entry of OMIM, Online Mendelian Inheritance in Man, a gene or a phenotype, by its MIM number: 141900. */
    case OMIM = 'omim';
    /** A term of the Experimental Factor Ontology (diseases, traits, measurements), its number: 0000305, written EFO:0000305. */
    case EFO = 'efo';
    /** A disease of the Mondo Disease Ontology, its number: 0011382, written MONDO:0011382 (sickle cell disease). */
    case MONDO = 'mondo';
    /** A rare disease of Orphanet, its ORPHAcode: 232, written ORPHA:232. */
    case ORPHANET = 'orphanet';
    /** A phenotypic abnormality of the Human Phenotype Ontology, its number: 0001250, written HP:0001250. */
    case HP = 'hp';
    /** A variant of ClinVar by its VariationID: 15333, written VCV000015333. */
    case CLINVAR = 'clinvar';
    /** A short variant of dbSNP by its reference SNP id: rs334. */
    case DBSNP = 'dbsnp';
    /** A study of the NHGRI-EBI GWAS Catalog: GCST000854. */
    case GWAS = 'gwas';
    /** A source of ESA's Gaia DR3 by its source_id: 4472832130942575872, written Gaia DR3 4472832130942575872 (Barnard's star). */
    case GAIA = 'gaia';
    /** A planet or a planetary system of the NASA Exoplanet Archive, by the archive's name: 51 Peg b, Proxima Cen (Wikidata's P5667). */
    case EXOPLANET = 'exoplanet';
    /** A station of the Global Historical Climatology Network (NCEI): USW00094728, New York's Central Park. */
    case GHCN = 'ghcn';
    /** A station of the WMO's register by its WIGOS id: 0-20000-0-07190, Strasbourg-Entzheim (a WMO index, 07190, read as such). */
    case WIGOS = 'wigos';
    /** An observation station of the U.S. National Weather Service: KNYC. */
    case NWS_STATION = 'nws.station';
    /** A Wikidata item, whatever its domain: Q60235. */
    case WIKIDATA = 'wikidata';

    public function label(): string
    {
        return match ($this) {
            self::PUBCHEM_CID => 'PubChem CID',
            self::CHEBI => 'ChEBI',
            self::CHEMBL => 'ChEMBL',
            self::INCHIKEY => 'InChIKey',
            self::INCHI => 'InChI',
            self::CAS => 'CAS RN',
            self::UNII => 'UNII',
            self::DSSTOX => 'DSSTox',
            self::KEGG => 'KEGG',
            self::KEGG_REACTION => 'KEGG reaction',
            self::KEGG_PATHWAY => 'KEGG pathway',
            self::RHEA => 'Rhea',
            self::EC => 'EC number',
            self::COD => 'COD',
            self::MATERIALS_PROJECT => 'Materials Project',
            self::OQMD => 'OQMD',
            self::CODATA => 'CODATA constant',
            self::UNIPROT => 'UniProt',
            self::NCBI_GENE => 'NCBI Gene',
            self::NCBI_TAXON => 'NCBI Taxonomy',
            self::ENSEMBL => 'Ensembl',
            self::ENSEMBL_GENOMES => 'Ensembl (community id)',
            self::PDB => 'PDB',
            self::GBIF => 'GBIF',
            self::GBIF_OCCURRENCE => 'GBIF occurrence',
            self::INATURALIST => 'iNaturalist',
            self::INATURALIST_OBSERVATION => 'iNaturalist observation',
            self::SIMBAD => 'SIMBAD',
            self::JPL_SBDB => 'JPL SBDB',
            self::USGS_EVENT => 'USGS event',
            self::REACTOME => 'Reactome',
            self::STRING => 'STRING',
            self::INTERPRO => 'InterPro',
            self::PFAM => 'Pfam',
            self::GO => 'Gene Ontology',
            self::HGNC => 'HGNC',
            self::OMIM => 'OMIM',
            self::EFO => 'EFO',
            self::MONDO => 'Mondo',
            self::ORPHANET => 'Orphanet',
            self::HP => 'HPO',
            self::CLINVAR => 'ClinVar',
            self::DBSNP => 'dbSNP',
            self::GWAS => 'GWAS Catalog',
            self::GAIA => 'Gaia DR3',
            self::EXOPLANET => 'NASA Exoplanet Archive',
            self::GHCN => 'GHCN station',
            self::WIGOS => 'WIGOS station',
            self::NWS_STATION => 'NWS station',
            self::WIKIDATA => 'Wikidata',
        };
    }

    /** The domain of the things this scheme names; null when it names anything (Wikidata). */
    public function domain(): ?Domain
    {
        return match ($this) {
            self::PUBCHEM_CID, self::CHEBI, self::CHEMBL, self::INCHIKEY, self::INCHI, self::CAS, self::UNII, self::DSSTOX, self::KEGG,
                self::KEGG_REACTION, self::RHEA, self::COD, self::MATERIALS_PROJECT, self::OQMD => Domain::CHEMISTRY,
            self::UNIPROT, self::NCBI_GENE, self::ENSEMBL, self::ENSEMBL_GENOMES, self::PDB, self::REACTOME, self::KEGG_PATHWAY, self::EC,
                self::STRING, self::INTERPRO, self::PFAM, self::GO, self::HGNC, self::OMIM, self::EFO, self::MONDO, self::ORPHANET, self::HP, self::CLINVAR, self::DBSNP, self::GWAS => Domain::BIOLOGY,
            self::NCBI_TAXON, self::GBIF, self::GBIF_OCCURRENCE, self::INATURALIST, self::INATURALIST_OBSERVATION => Domain::TAXONOMY,
            self::SIMBAD, self::JPL_SBDB, self::GAIA, self::EXOPLANET => Domain::ASTRONOMY,
            self::USGS_EVENT, self::GHCN, self::WIGOS, self::NWS_STATION => Domain::EARTH,
            self::CODATA => Domain::OTHER,
            self::WIKIDATA => null,
        };
    }
}
