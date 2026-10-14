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
    /** A UniProt protein: P69905. */
    case UNIPROT = 'uniprot';
    /** An NCBI Gene record: 7157 (TP53, human). */
    case NCBI_GENE = 'ncbi.gene';
    /** An NCBI Taxonomy node: 9606 (Homo sapiens). */
    case NCBI_TAXON = 'ncbi.taxon';
    /** An Ensembl gene, transcript or protein, without its version: ENSG00000141510. */
    case ENSEMBL = 'ensembl';
    /** A Protein Data Bank structure: 1A3N. */
    case PDB = 'pdb';
    /** A GBIF taxon (backbone key): 2435099 (Puma concolor). */
    case GBIF = 'gbif';
    /** An iNaturalist taxon: 42007 (Puma concolor). */
    case INATURALIST = 'inaturalist';
    /** An object of SIMBAD, the CDS astronomical database, by its main identifier: M 31. */
    case SIMBAD = 'simbad';
    /** A small body of JPL's database (asteroid, comet): 433, 1P/Halley, 2004 MN4. */
    case JPL_SBDB = 'jpl.sbdb';
    /** A USGS earthquake event (ComCat): us6000u24c. */
    case USGS_EVENT = 'usgs.event';
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
            self::UNIPROT => 'UniProt',
            self::NCBI_GENE => 'NCBI Gene',
            self::NCBI_TAXON => 'NCBI Taxonomy',
            self::ENSEMBL => 'Ensembl',
            self::PDB => 'PDB',
            self::GBIF => 'GBIF',
            self::INATURALIST => 'iNaturalist',
            self::SIMBAD => 'SIMBAD',
            self::JPL_SBDB => 'JPL SBDB',
            self::USGS_EVENT => 'USGS event',
            self::WIKIDATA => 'Wikidata',
        };
    }

    /** The domain of the things this scheme names; null when it names anything (Wikidata). */
    public function domain(): ?Domain
    {
        return match ($this) {
            self::PUBCHEM_CID, self::CHEBI, self::CHEMBL, self::INCHIKEY, self::INCHI, self::CAS, self::UNII, self::DSSTOX, self::KEGG => Domain::CHEMISTRY,
            self::UNIPROT, self::NCBI_GENE, self::ENSEMBL, self::PDB => Domain::BIOLOGY,
            self::NCBI_TAXON, self::GBIF, self::INATURALIST => Domain::TAXONOMY,
            self::SIMBAD, self::JPL_SBDB => Domain::ASTRONOMY,
            self::USGS_EVENT => Domain::EARTH,
            self::WIKIDATA => null,
        };
    }
}
