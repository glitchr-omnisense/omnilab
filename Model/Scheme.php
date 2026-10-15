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
            self::UNIPROT => 'UniProt',
            self::NCBI_GENE => 'NCBI Gene',
            self::NCBI_TAXON => 'NCBI Taxonomy',
            self::ENSEMBL => 'Ensembl',
            self::PDB => 'PDB',
            self::GBIF => 'GBIF',
            self::GBIF_OCCURRENCE => 'GBIF occurrence',
            self::INATURALIST => 'iNaturalist',
            self::INATURALIST_OBSERVATION => 'iNaturalist observation',
            self::SIMBAD => 'SIMBAD',
            self::JPL_SBDB => 'JPL SBDB',
            self::USGS_EVENT => 'USGS event',
            self::REACTOME => 'Reactome',
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
            self::PUBCHEM_CID, self::CHEBI, self::CHEMBL, self::INCHIKEY, self::INCHI, self::CAS, self::UNII, self::DSSTOX, self::KEGG => Domain::CHEMISTRY,
            self::UNIPROT, self::NCBI_GENE, self::ENSEMBL, self::PDB, self::REACTOME => Domain::BIOLOGY,
            self::NCBI_TAXON, self::GBIF, self::GBIF_OCCURRENCE, self::INATURALIST, self::INATURALIST_OBSERVATION => Domain::TAXONOMY,
            self::SIMBAD, self::JPL_SBDB, self::GAIA, self::EXOPLANET => Domain::ASTRONOMY,
            self::USGS_EVENT, self::GHCN, self::WIGOS, self::NWS_STATION => Domain::EARTH,
            self::WIKIDATA => null,
        };
    }
}
