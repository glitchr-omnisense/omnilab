<?php

namespace Omnilab\Model;

/**
 * One identifier, normalised so that two spellings of it compare equal: a
 * ChEBI number without its "CHEBI:", a CAS number with its check digit
 * checked, an Ensembl id without its version, a Wikidata item upper-cased.
 *
 *   Identifier::parse('CHEBI:27732');                                   // chebi:27732
 *   Identifier::parse('58-08-2');                                       // cas:58-08-2
 *   Identifier::parse('RYYVLZVUVIJVGH-UHFFFAOYSA-N');                   // inchikey:RYYVLZVUVIJVGH-UHFFFAOYSA-N
 *   Identifier::parse('https://pubchem.ncbi.nlm.nih.gov/compound/2519'); // pubchem.cid:2519
 *   Identifier::parse('https://identifiers.org/uniprot:P69905');        // uniprot:P69905
 *   Identifier::of(Scheme::PUBCHEM_CID, '2519')->url();                 // https://pubchem.ncbi.nlm.nih.gov/compound/2519
 *   Identifier::tryOf('cas', '58-08-3');                                // null: wrong check digit
 *   Identifier::of('wmo', '07190');                                     // wigos:0-20000-0-07190
 */
final readonly class Identifier implements \Stringable
{
    private const INCHIKEY = '~^[A-Z]{14}-[A-Z]{10}-[A-Z]$~';
    private const CAS = '~^(\d{2,7})-(\d{2})-(\d)$~';
    private const UNIPROT = '~^(?:[OPQ][0-9][A-Z0-9]{3}[0-9]|[A-NR-Z][0-9](?:[A-Z][A-Z0-9]{2}[0-9]){1,2})(?:-\d+)?$~';
    private const ENSEMBL = '~^(ENS[A-Z]*[EFGPRT]\d{11})(?:\.\d+)?$~';
    /** A WIGOS station id: its series, issuer, issue number and local id (the WMO's own pattern, as Wikidata's P4136 writes it). */
    private const WIGOS = '~^(?:\d|1[0-4])(?:-(?:\d|[1-9]\d{1,3}|[1-5]\d{4}|6[0-4]\d{3}|65[0-4]\d{2}|655[0-2]\d|6553[0-4])){2}-[0-9A-Za-z]{1,16}$~';

    /**
     * The genomes Ensembl serves under their community's ids, told by the
     * shape of their ids: the pattern (case aside), the prefix as written,
     * the case of the rest, the genome (Ensembl's production name, which a
     * lookup needs). Each checked on 2026-10-15 against rest.ensembl.org.
     *
     * @var list<array{string, string, string, string}>
     */
    public const ENSEMBL_GENOMES = [
        ['~^FB(?:gn|tr|pp)\d{7}$~i', 'FB', 'lower', 'drosophila_melanogaster'],
        ['~^WBGene\d{8}$~i', 'WBGene', 'keep', 'caenorhabditis_elegans'],
        ['~^Y[A-P][LR]\d{3}[WC](?:-[A-Z])?$~i', '', 'upper', 'saccharomyces_cerevisiae'],
        ['~^AT[1-5CM]G\d{5}(?:\.\d+)?$~i', '', 'upper', 'arabidopsis_thaliana'],
        ['~^Os\d{2}g\d{7}$~i', 'Os', 'lower', 'oryza_sativa'],
        ['~^Zm00001eb\d{6}$~i', 'Zm00001eb', 'keep', 'zea_mays'],
        ['~^TraesCS[1-7U][ABD]?\d{2}G\d{6}$~i', 'TraesCS', 'upper', 'triticum_aestivum'],
        ['~^SP[ABC][A-Z0-9]+\.\d+[a-z]?$~', '', 'keep', 'schizosaccharomyces_pombe'],
        ['~^PF3D7_\d{7}$~i', '', 'upper', 'plasmodium_falciparum'],
        ['~^AGAP\d{6}$~i', '', 'upper', 'anopheles_gambiae'],
    ];

    /** The other names a "prefix:value" may give a scheme: identifiers.org's prefixes, the bases' own. */
    private const PREFIXES = [
        'cid' => Scheme::PUBCHEM_CID, 'pubchem' => Scheme::PUBCHEM_CID, 'pubchem.compound' => Scheme::PUBCHEM_CID,
        'chembl.compound' => Scheme::CHEMBL,
        'casrn' => Scheme::CAS, 'cas_rn' => Scheme::CAS, 'cas.rn' => Scheme::CAS,
        'kegg.compound' => Scheme::KEGG, 'kegg.drug' => Scheme::KEGG, 'cpd' => Scheme::KEGG, 'dr' => Scheme::KEGG,
        'dtxsid' => Scheme::DSSTOX, 'comptox' => Scheme::DSSTOX,
        'uniprotkb' => Scheme::UNIPROT,
        'ncbigene' => Scheme::NCBI_GENE, 'gene' => Scheme::NCBI_GENE,
        'taxonomy' => Scheme::NCBI_TAXON, 'ncbitaxon' => Scheme::NCBI_TAXON, 'txid' => Scheme::NCBI_TAXON,
        'inaturalist.taxon' => Scheme::INATURALIST,
        'sbdb' => Scheme::JPL_SBDB, 'jpl' => Scheme::JPL_SBDB,
        'usgs' => Scheme::USGS_EVENT,
        'ghcnd' => Scheme::GHCN,
        'wmo' => Scheme::WIGOS,
        'ensembl.plant' => Scheme::ENSEMBL_GENOMES, 'ensembl.fungi' => Scheme::ENSEMBL_GENOMES, 'ensembl.metazoa' => Scheme::ENSEMBL_GENOMES, 'ensembl.protist' => Scheme::ENSEMBL_GENOMES, 'ensembl.bacteria' => Scheme::ENSEMBL_GENOMES,
        'gaia.dr3' => Scheme::GAIA,
        'nasa.exoplanet' => Scheme::EXOPLANET, 'exoplanetarchive' => Scheme::EXOPLANET,
        'string-db' => Scheme::STRING,
        'ipr' => Scheme::INTERPRO,
        'mim' => Scheme::OMIM,
        'orpha' => Scheme::ORPHANET, 'ordo' => Scheme::ORPHANET, 'orphanet.ordo' => Scheme::ORPHANET,
        'hpo' => Scheme::HP,
        'clinvar.variation' => Scheme::CLINVAR,
        'snp' => Scheme::DBSNP,
        'gwas.study' => Scheme::GWAS, 'gcst' => Scheme::GWAS,
        'rn' => Scheme::KEGG_REACTION,
        'path' => Scheme::KEGG_PATHWAY,
        'ec-code' => Scheme::EC, 'ec.code' => Scheme::EC, 'eccode' => Scheme::EC,
        'cod.id' => Scheme::COD,
        'mp.material' => Scheme::MATERIALS_PROJECT, 'materials-project' => Scheme::MATERIALS_PROJECT,
        'oqmd.entry' => Scheme::OQMD,
        'nist.codata' => Scheme::CODATA,
        'aphia' => Scheme::WORMS, 'aphiaid' => Scheme::WORMS, 'worms.taxon' => Scheme::WORMS,
        'obis' => Scheme::OBIS_OCCURRENCE,
        'tsn' => Scheme::ITIS, 'itis.taxon' => Scheme::ITIS,
        'catalogueoflife' => Scheme::COL, 'col.taxon' => Scheme::COL,
        'txn' => Scheme::PBDB, 'pbdb.taxon' => Scheme::PBDB, 'occ' => Scheme::PBDB_OCCURRENCE,
        'ebird.taxon' => Scheme::EBIRD,
        'iucn.taxon' => Scheme::IUCN, 'iucnredlist' => Scheme::IUCN,
        'wd' => Scheme::WIKIDATA,
    ];

    private function __construct(
        public Scheme $scheme,
        public string $value,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when the value is not a valid identifier of that scheme
     */
    public static function of(Scheme|string $scheme, string $value): self
    {
        $scheme = $scheme instanceof Scheme ? $scheme : (self::scheme($scheme) ?? throw new \InvalidArgumentException(\sprintf('"%s" is not a scheme Omnilab knows.', $scheme)));

        return self::tryOf($scheme, $value) ?? throw new \InvalidArgumentException(\sprintf('"%s" is not a valid %s.', $value, $scheme->label()));
    }

    /** The identifier, or null when the value is not one of that scheme. */
    public static function tryOf(Scheme|string|null $scheme, int|string|null $value): ?self
    {
        if (null === $scheme || null === $value) {
            return null;
        }
        $scheme = $scheme instanceof Scheme ? $scheme : self::scheme($scheme);
        $value = trim((string) $value);
        if (null === $scheme || '' === $value) {
            return null;
        }
        $upper = strtoupper($value);
        $normalized = match ($scheme) {
            Scheme::PUBCHEM_CID => preg_match('~^(?:CID[:\s]*)?0*([1-9]\d{0,11})$~', $upper, $m) ? $m[1] : null,
            Scheme::CHEBI => preg_match('~^(?:CHEBI:)?0*([1-9]\d*)$~', $upper, $m) ? $m[1] : null,
            Scheme::CHEMBL => preg_match('~^CHEMBL\d+$~', $upper) ? $upper : null,
            Scheme::INCHIKEY => preg_match(self::INCHIKEY, $v = (string) preg_replace('~^INCHIKEY=~', '', $upper)) ? $v : null,
            Scheme::INCHI => preg_match('~^InChI=1S?/\S+$~', $value) ? $value : null,
            Scheme::CAS => self::normalizeCas($value),
            Scheme::UNII => preg_match('~^(?:UNII[-:\s]?)?([A-Z0-9]{10})$~', $upper, $m) ? $m[1] : null,
            Scheme::DSSTOX => preg_match('~^DTXSID\d+$~', $upper) ? $upper : null,
            Scheme::KEGG => preg_match('~^(?:CPD:|DR:)?([CD]\d{5})$~', $upper, $m) ? $m[1] : null,
            Scheme::KEGG_REACTION => preg_match('~^(?:RN:)?(R\d{5})$~', $upper, $m) ? $m[1] : null,
            // A map's prefix (map, ko, ec, rn, or an organism's three or four letters: hsa, eco) then five digits, lower case.
            Scheme::KEGG_PATHWAY => preg_match('~^(?:path:)?([a-z]{2,4}\d{5})$~', strtolower($value), $m) ? $m[1] : null,
            // A master reaction or one of its directions (five or six digits), "RHEA:" before it read.
            Scheme::RHEA => preg_match('~^(?:RHEA:)?([1-9]\d{4,5})$~', $upper, $m) ? $m[1] : null,
            // Four levels, the last ones "-" when the number is not complete, the fourth "n" for a preliminary one (identifiers.org's ec-code).
            Scheme::EC => preg_match('~^(?:EC[:\s]*)?([1-7]\.(?:-\.-\.-|\d{1,2}\.(?:-\.-|\d{1,2}\.(?:-|N?\d{1,3}))))$~', $upper, $m) ? strtolower($m[1]) : null,
            Scheme::COD => preg_match('~^(?:COD:?\s*)?([1-9]\d{6})$~', $upper, $m) ? $m[1] : null,
            // A Materials Project id: mp-149, or another of its prefixes (mvc-..., an unsolved structure).
            Scheme::MATERIALS_PROJECT => preg_match('~^((?:mp|mvc)-[1-9]\d{0,9})$~', strtolower($value), $m) ? $m[1] : null,
            Scheme::OQMD => preg_match('~^0*([1-9]\d{0,9})$~', $value, $m) ? $m[1] : null,
            // NIST's short name of a constant, case kept (mp, Kj, RK, alph): letters and digits.
            Scheme::CODATA => preg_match('~^[A-Za-z][A-Za-z0-9]{0,24}$~', $value) ? $value : null,
            Scheme::UNIPROT => preg_match(self::UNIPROT, $upper) ? $upper : null,
            Scheme::NCBI_GENE, Scheme::INATURALIST => preg_match('~^0*([1-9]\d*)$~', $value, $m) ? $m[1] : null,
            Scheme::GBIF => self::normalizeGbif($upper),
            Scheme::GBIF_OCCURRENCE, Scheme::INATURALIST_OBSERVATION => preg_match('~^0*([1-9]\d{0,18})$~', $value, $m) ? $m[1] : null,
            // A number, or the LSID WoRMS and ITIS give their names (urn:lsid:marinespecies.org:taxname:137094).
            Scheme::WORMS => preg_match('~^(?:URN:LSID:MARINESPECIES\.ORG:TAXNAME:)?0*([1-9]\d{0,9})$~', $upper, $m) ? $m[1] : null,
            Scheme::ITIS => preg_match('~^(?:URN:LSID:ITIS\.GOV:ITIS_TSN:)?0*([1-9]\d{0,9})$~', $upper, $m) ? $m[1] : null,
            Scheme::OBIS_OCCURRENCE => preg_match('~^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$~', $v = strtolower($value)) ? $v : null,
            // ChecklistBank's ids of the Catalogue of Life: letters and digits, upper case (4CGXP, N, CH2).
            Scheme::COL => preg_match('~^(?:COL:)?([0-9A-Z]{1,12})$~', $upper, $m) ? $m[1] : null,
            Scheme::PBDB => preg_match('~^(?:TXN:)?0*([1-9]\d{0,9})$~', $upper, $m) ? $m[1] : null,
            Scheme::PBDB_OCCURRENCE => preg_match('~^(?:OCC:)?0*([1-9]\d{0,10})$~', $upper, $m) ? $m[1] : null,
            // eBird's species codes: lower-case letters, digits for the groups and forms (amerob, x00004, y00478, turdid1).
            Scheme::EBIRD => preg_match('~^[a-z][a-z0-9]{3,11}$~', $v = strtolower($value)) ? $v : null,
            Scheme::EBIRD_CHECKLIST => preg_match('~^S[1-9]\d{3,11}$~', $upper) ? $upper : null,
            Scheme::IUCN => preg_match('~^0*([1-9]\d{0,11})$~', $value, $m) ? $m[1] : null,
            Scheme::NCBI_TAXON => preg_match('~^(?:TXID)?0*([1-9]\d*)$~', $upper, $m) ? $m[1] : null,
            Scheme::ENSEMBL => preg_match(self::ENSEMBL, $upper, $m) ? $m[1] : null,
            // A known genome's id as its community writes it; any other as given (not an ENS id: that is the ensembl scheme).
            Scheme::ENSEMBL_GENOMES => self::ensemblGenomes($value) ?? (preg_match('~^[A-Za-z0-9][A-Za-z0-9_.:-]{2,40}$~', $value) && !preg_match(self::ENSEMBL, $upper) ? $value : null),
            Scheme::PDB => preg_match('~^\d[A-Z0-9]{3}$~', $upper) ? $upper : (preg_match('~^PDB_\d{4}[A-Z0-9]{4}$~', $upper) ? strtolower($upper) : null),
            Scheme::SIMBAD, Scheme::JPL_SBDB => self::name($value),
            // A network's code then the event's own (ComCat keeps the catalogues' long ones: official20110311054624120_30, iscgemsup16957911).
            Scheme::USGS_EVENT => preg_match('~^[a-z]{2,10}[a-z0-9_]{6,24}$~', $v = strtolower($value)) ? $v : null,
            // A species' three letters (HSA, MMU; ALL for what no species holds: a small molecule), the number, a version left out.
            Scheme::REACTOME => preg_match('~^(?:REACTOME:)?(R-[A-Z]{3}-[1-9]\d*)(?:\.\d+)?$~', $upper, $m) ? $m[1] : null,
            // DR3's source_id, a 64-bit number; "Gaia DR3 " before it read.
            Scheme::GAIA => preg_match('~^(?:GAIA\s*DR3\s+)?0*([1-9]\d{0,18})$~', $upper, $m) && (\strlen($m[1]) < 19 || strcmp($m[1], (string) \PHP_INT_MAX) <= 0) ? $m[1] : null,
            Scheme::EXOPLANET => self::name($value),
            // The NCBI taxon, a dot, the protein's id in its genome as STRING writes it (9606.ENSP00000269305, 511145.b0001, 4932.YAL001C).
            Scheme::STRING => preg_match('~^[1-9]\d{0,9}\.(?=[^.]*[A-Za-z])[A-Za-z0-9][A-Za-z0-9_.:-]{1,60}$~', $value) ? $value : null,
            Scheme::INTERPRO => preg_match('~^(?:INTERPRO:)?(IPR\d{6})$~', $upper, $m) ? $m[1] : null,
            Scheme::PFAM => preg_match('~^(?:PFAM:)?(PF\d{5})(?:\.\d+)?$~', $upper, $m) ? $m[1] : null,
            // The OBO ontologies' terms: seven digits, "GO:" (or the OBO "GO_") before them read.
            Scheme::GO => preg_match('~^(?:GO[:_])?(\d{7})$~', $upper, $m) ? $m[1] : null,
            Scheme::EFO => preg_match('~^(?:EFO[:_])?(\d{7})$~', $upper, $m) ? $m[1] : null,
            Scheme::MONDO => preg_match('~^(?:MONDO[:_])?(\d{7})$~', $upper, $m) ? $m[1] : null,
            Scheme::HP => preg_match('~^(?:HP[:_])?(\d{7})$~', $upper, $m) ? $m[1] : null,
            Scheme::HGNC => preg_match('~^(?:HGNC:)?0*([1-9]\d{0,6})$~', $upper, $m) ? $m[1] : null,
            // A MIM number: six digits, the first 1 to 6 (its prefix, *, # or %, read).
            Scheme::OMIM => preg_match('~^(?:(?:OMIM|MIM):?\s*)?[*#%+^]?([1-6]\d{5})$~', $upper, $m) ? $m[1] : null,
            Scheme::ORPHANET => preg_match('~^(?:ORPHANET[:_]|ORPHA:?)?0*([1-9]\d{0,6})$~', $upper, $m) ? $m[1] : null,
            // The VariationID, or the VCV accession that pads it to nine digits (its version dropped).
            Scheme::CLINVAR => preg_match('~^(?:VCV)?0*([1-9]\d{0,9})(?:\.\d+)?$~', $upper, $m) && (str_starts_with($upper, 'VCV') || !str_contains($upper, '.')) ? $m[1] : null,
            Scheme::DBSNP => preg_match('~^RS0*([1-9]\d{0,11})$~', $upper, $m) ? 'rs'.$m[1] : null,
            Scheme::GWAS => preg_match('~^(GCST\d{6,9})$~', $upper, $m) ? $m[1] : null,
            // The country (FIPS), the network, the station's own: USW00094728, BR00A6-0010.
            Scheme::GHCN => preg_match('~^(?:GHCND:)?([A-Z]{2}[0-9A-Z][0-9A-Z-]{8})$~', $upper, $m) ? $m[1] : null,
            // A WMO index of five digits is the WIGOS id 0-20000-0-<index>.
            Scheme::WIGOS => preg_match('~^\d{5}$~', $value) ? '0-20000-0-'.$value : (preg_match(self::WIGOS, $value) ? $value : null),
            Scheme::NWS_STATION => preg_match('~^[A-Z0-9]{3,10}$~', $upper) ? $upper : null,
            Scheme::WIKIDATA => preg_match('~^(?:WD:)?(Q[1-9]\d*)$~', $upper, $m) ? $m[1] : null,
        };

        return null === $normalized ? null : new self($scheme, $normalized);
    }

    /**
     * Reads an identifier from a string: "scheme:value" (the schemes' own
     * names, identifiers.org's prefixes, CHEBI:27732), a page of the base
     * that holds it or an identifiers.org link, or a value whose shape tells
     * its scheme (an InChIKey, a CAS number, CHEMBL113, DTXSID..., an
     * Ensembl id, a Wikidata Q-number). Null when nothing is recognised: a
     * bare number (a CID? a GBIF key? a gene?) needs its scheme, and so does
     * a UniProt accession or a PDB code, whose shapes other values share.
     */
    public static function parse(string $input): ?self
    {
        $input = trim($input);
        if ('' === $input) {
            return null;
        }
        if (preg_match('~^https?://~i', $input)) {
            return self::fromUrl($input);
        }
        // scheme:value
        if (preg_match('~^([a-z][a-z._-]*):\s*(.+)$~i', $input, $m) && null !== ($scheme = self::scheme($m[1]))) {
            return self::tryOf($scheme, $m[2]);
        }
        $upper = strtoupper($input);

        return match (true) {
            str_starts_with($input, 'InChI=') => self::tryOf(Scheme::INCHI, $input),
            (bool) preg_match(self::INCHIKEY, $upper) => self::tryOf(Scheme::INCHIKEY, $upper),
            (bool) preg_match(self::CAS, $input) => self::tryOf(Scheme::CAS, $input),
            (bool) preg_match('~^CID\s*\d+$~', $upper) => self::tryOf(Scheme::PUBCHEM_CID, $upper),
            (bool) preg_match('~^CHEMBL\d+$~', $upper) => self::tryOf(Scheme::CHEMBL, $upper),
            (bool) preg_match('~^DTXSID\d+$~', $upper) => self::tryOf(Scheme::DSSTOX, $upper),
            (bool) preg_match('~^UNII-[A-Z0-9]{10}$~', $upper) => self::tryOf(Scheme::UNII, $upper),
            (bool) preg_match(self::ENSEMBL, $upper) => self::tryOf(Scheme::ENSEMBL, $upper),
            (bool) preg_match('~^Q[1-9]\d*$~', $upper) => self::tryOf(Scheme::WIKIDATA, $upper),
            (bool) preg_match(self::WIGOS, $input) => self::tryOf(Scheme::WIGOS, $input),
            null !== self::ensemblGenomes($input) => self::tryOf(Scheme::ENSEMBL_GENOMES, $input),
            (bool) preg_match('~^R-[A-Z]{3}-\d+(?:\.\d+)?$~', $upper) => self::tryOf(Scheme::REACTOME, $upper),
            (bool) preg_match('~^GAIA\s*DR3\s+\d+$~', $upper) => self::tryOf(Scheme::GAIA, $upper),
            (bool) preg_match('~^EC\s+\d~', $upper) => self::tryOf(Scheme::EC, $upper),
            (bool) preg_match('~^(?:mp|mvc)-\d+$~i', $input) => self::tryOf(Scheme::MATERIALS_PROJECT, $input),
            (bool) preg_match('~^IPR\d{6}$~', $upper) => self::tryOf(Scheme::INTERPRO, $upper),
            (bool) preg_match('~^PF\d{5}(?:\.\d+)?$~', $upper) => self::tryOf(Scheme::PFAM, $upper),
            // The OBO short forms, as Open Targets and the GWAS Catalog write the terms: GO_0005829, EFO_0000305, MONDO_0011382, HP_0001250, Orphanet_232.
            (bool) preg_match('~^(GO|EFO|MONDO|HP)_\d{7}$~', $upper, $m) => self::tryOf(strtolower($m[1]), $upper),
            (bool) preg_match('~^ORPHANET_\d+$~', $upper) => self::tryOf(Scheme::ORPHANET, $upper),
            (bool) preg_match('~^VCV\d{9}(?:\.\d+)?$~', $upper) => self::tryOf(Scheme::CLINVAR, $upper),
            (bool) preg_match('~^RS\d+$~', $upper) => self::tryOf(Scheme::DBSNP, $upper),
            (bool) preg_match('~^GCST\d{6,9}$~', $upper) => self::tryOf(Scheme::GWAS, $upper),
            (bool) preg_match('~^URN:LSID:MARINESPECIES\.ORG:TAXNAME:\d+$~', $upper) => self::tryOf(Scheme::WORMS, $upper),
            (bool) preg_match('~^URN:LSID:ITIS\.GOV:ITIS_TSN:\d+$~', $upper) => self::tryOf(Scheme::ITIS, $upper),
            // A STRING protein: an NCBI taxon of three digits or more, a dot, an id with a letter (9606.ENSP00000269305).
            (bool) preg_match('~^[1-9]\d{2,9}\.[A-Za-z][A-Za-z0-9_.-]{2,}$~', $input) => self::tryOf(Scheme::STRING, $input),
            default => null,
        };
    }

    /** The scheme a name or a prefix stands for: "chebi", "pubchem.cid", "ncbigene", "CHEBI"... */
    public static function scheme(string $name): ?Scheme
    {
        $name = strtolower(trim($name));

        return Scheme::tryFrom($name) ?? self::PREFIXES[$name] ?? null;
    }

    public static function cid(int|string $value): self
    {
        return self::of(Scheme::PUBCHEM_CID, (string) $value);
    }

    public static function chebi(int|string $value): self
    {
        return self::of(Scheme::CHEBI, (string) $value);
    }

    public static function inchikey(string $value): self
    {
        return self::of(Scheme::INCHIKEY, $value);
    }

    public static function cas(string $value): self
    {
        return self::of(Scheme::CAS, $value);
    }

    public static function wikidata(string $value): self
    {
        return self::of(Scheme::WIKIDATA, $value);
    }

    /** "chebi:27732": what the Merger and the collections key on. */
    public function key(): string
    {
        return $this->scheme->value.':'.$this->value;
    }

    /** The identifier as its base writes it: CHEBI:27732, CID 2519, 58-08-2. */
    public function display(): string
    {
        return match ($this->scheme) {
            Scheme::CHEBI => 'CHEBI:'.$this->value,
            Scheme::PUBCHEM_CID => 'CID '.$this->value,
            Scheme::GO => 'GO:'.$this->value,
            Scheme::HGNC => 'HGNC:'.$this->value,
            Scheme::OMIM => 'OMIM:'.$this->value,
            Scheme::EFO => 'EFO:'.$this->value,
            Scheme::MONDO => 'MONDO:'.$this->value,
            Scheme::ORPHANET => 'ORPHA:'.$this->value,
            Scheme::HP => 'HP:'.$this->value,
            Scheme::CLINVAR => 'VCV'.str_pad($this->value, 9, '0', \STR_PAD_LEFT),
            Scheme::RHEA => 'RHEA:'.$this->value,
            Scheme::EC => 'EC '.$this->value,
            default => $this->value,
        };
    }

    public function equals(self $other): bool
    {
        return $this->scheme === $other->scheme && $this->value === $other->value;
    }

    /** The page of the base that holds it, when there is one. */
    public function url(): ?string
    {
        $v = rawurlencode($this->value);

        return match ($this->scheme) {
            Scheme::PUBCHEM_CID => 'https://pubchem.ncbi.nlm.nih.gov/compound/'.$v,
            Scheme::CHEBI => 'https://www.ebi.ac.uk/chebi/CHEBI:'.$v,
            Scheme::CHEMBL => 'https://identifiers.org/chembl.compound:'.$v,
            Scheme::INCHIKEY, Scheme::INCHI => null,
            Scheme::CAS => 'https://commonchemistry.cas.org/detail?cas_rn='.$v,
            Scheme::UNII => 'https://precision.fda.gov/uniisearch/srs/unii/'.$v,
            Scheme::DSSTOX => 'https://comptox.epa.gov/dashboard/chemical/details/'.$v,
            Scheme::KEGG, Scheme::KEGG_REACTION, Scheme::KEGG_PATHWAY => 'https://www.kegg.jp/entry/'.$v,
            // Rhea's page answers a browser check to a program (403, 2026-10-15); a browser opens it.
            Scheme::RHEA => 'https://www.rhea-db.org/rhea/'.$v,
            Scheme::EC => 'https://enzyme.expasy.org/EC/'.$this->value,
            Scheme::COD => 'https://www.crystallography.net/cod/'.$v.'.html',
            Scheme::MATERIALS_PROJECT => 'https://materialsproject.org/materials/'.$v.'/',
            Scheme::OQMD => 'https://oqmd.org/materials/entry/'.$v,
            Scheme::CODATA => 'https://physics.nist.gov/cgi-bin/cuu/Value?'.$v,
            Scheme::UNIPROT => 'https://www.uniprot.org/uniprotkb/'.$v,
            Scheme::NCBI_GENE => 'https://www.ncbi.nlm.nih.gov/gene/'.$v,
            Scheme::NCBI_TAXON => 'https://www.ncbi.nlm.nih.gov/Taxonomy/Browser/wwwtax.cgi?id='.$v,
            // Every division's site sends its ids to www.ensembl.org's (checked on 2026-10-15).
            Scheme::ENSEMBL, Scheme::ENSEMBL_GENOMES => 'https://www.ensembl.org/id/'.$v,
            Scheme::PDB => 'https://www.rcsb.org/structure/'.$v,
            Scheme::GBIF => ctype_digit($this->value) ? 'https://www.gbif.org/species/'.$v : 'https://www.gbif.org/taxon/'.$v,
            Scheme::GBIF_OCCURRENCE => 'https://www.gbif.org/occurrence/'.$v,
            Scheme::INATURALIST => 'https://www.inaturalist.org/taxa/'.$v,
            Scheme::INATURALIST_OBSERVATION => 'https://www.inaturalist.org/observations/'.$v,
            Scheme::WORMS => 'https://www.marinespecies.org/aphia.php?p=taxdetails&id='.$v,
            Scheme::OBIS_OCCURRENCE => 'https://api.obis.org/v3/occurrence/'.$v,
            Scheme::ITIS => 'https://www.itis.gov/servlet/SingleRpt/SingleRpt?search_topic=TSN&search_value='.$v,
            Scheme::COL => 'https://www.catalogueoflife.org/data/taxon/'.$v,
            Scheme::PBDB => 'https://paleobiodb.org/classic/basicTaxonInfo?taxon_no='.$v,
            Scheme::PBDB_OCCURRENCE => 'https://paleobiodb.org/data1.2/occs/single.json?id=occ:'.$v.'&show=full',
            Scheme::EBIRD => 'https://ebird.org/species/'.$v,
            Scheme::EBIRD_CHECKLIST => 'https://ebird.org/checklist/'.$v,
            Scheme::IUCN => 'https://www.iucnredlist.org/species/'.$v,
            Scheme::SIMBAD => 'https://simbad.cds.unistra.fr/simbad/sim-id?Ident='.$v,
            Scheme::JPL_SBDB => 'https://ssd.jpl.nasa.gov/tools/sbdb_lookup.html#/?sstr='.$v,
            Scheme::USGS_EVENT => 'https://earthquake.usgs.gov/earthquakes/eventpage/'.$v,
            Scheme::REACTOME => 'https://reactome.org/content/detail/'.$v,
            Scheme::STRING => 'https://string-db.org/network/'.$v,
            Scheme::INTERPRO => 'https://www.ebi.ac.uk/interpro/entry/InterPro/'.$v.'/',
            Scheme::PFAM => 'https://www.ebi.ac.uk/interpro/entry/pfam/'.$v.'/',
            Scheme::GO => 'https://www.ebi.ac.uk/QuickGO/term/GO:'.$v,
            Scheme::HGNC => 'https://www.genenames.org/data/gene-symbol-report/#!/hgnc_id/HGNC:'.$v,
            Scheme::OMIM => 'https://omim.org/entry/'.$v,
            Scheme::EFO => 'https://www.ebi.ac.uk/ols4/ontologies/efo/classes?obo_id=EFO:'.$v,
            Scheme::MONDO => 'https://monarchinitiative.org/MONDO:'.$v,
            Scheme::ORPHANET => 'https://www.orpha.net/en/disease/detail/'.$v,
            Scheme::HP => 'https://www.ebi.ac.uk/ols4/ontologies/hp/classes?obo_id=HP:'.$v,
            Scheme::CLINVAR => 'https://www.ncbi.nlm.nih.gov/clinvar/variation/'.$v.'/',
            Scheme::DBSNP => 'https://www.ncbi.nlm.nih.gov/snp/'.$v,
            Scheme::GWAS => 'https://www.ebi.ac.uk/gwas/studies/'.$v,
            Scheme::GAIA => 'https://vizier.cds.unistra.fr/viz-bin/VizieR-S?Gaia%20DR3%20'.$v,
            Scheme::EXOPLANET => 'https://exoplanetarchive.ipac.caltech.edu/overview/'.$v,
            Scheme::GHCN => 'https://www.ncei.noaa.gov/cdo-web/datasets/GHCND/stations/GHCND:'.$v.'/detail',
            Scheme::WIGOS => 'https://oscar.wmo.int/surface/#/search/station/stationReportDetails/'.$v,
            Scheme::NWS_STATION => 'https://www.weather.gov/wrh/timeseries?site='.$v,
            Scheme::WIKIDATA => 'https://www.wikidata.org/wiki/'.$v,
        };
    }

    public function __toString(): string
    {
        return $this->key();
    }

    private static function fromUrl(string $url): ?self
    {
        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));
        $path = rawurldecode(trim((string) parse_url($url, \PHP_URL_PATH), '/'));
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $fragment = rawurldecode((string) parse_url($url, \PHP_URL_FRAGMENT));
        $last = basename($path);
        $found = static fn (string $pattern, Scheme $scheme): ?self => preg_match($pattern, rawurldecode($url), $m) ? self::tryOf($scheme, $m[1]) : null;

        return match (true) {
            str_ends_with($host, 'identifiers.org') => str_contains($path, ':') ? self::parse($path) : null,
            'pubchem.ncbi.nlm.nih.gov' === $host => preg_match('~^compound/(\d+)$~', $path, $m) ? self::tryOf(Scheme::PUBCHEM_CID, $m[1]) : null,
            str_ends_with($host, 'ebi.ac.uk') && str_starts_with($path, 'interpro/') => $found('~/(IPR\d{6})~i', Scheme::INTERPRO) ?? $found('~/(PF\d{5})~i', Scheme::PFAM),
            str_ends_with($host, 'ebi.ac.uk') && str_starts_with(strtolower($path), 'quickgo/') => $found('~GO:(\d{7})~i', Scheme::GO),
            str_ends_with($host, 'ebi.ac.uk') && str_starts_with($path, 'gwas/') => $found('~/(GCST\d{6,9})~i', Scheme::GWAS),
            str_ends_with($host, 'ebi.ac.uk') && str_starts_with($path, 'ols') => preg_match('~^(GO|EFO|MONDO|HP):\d{7}$~i', (string) ($query['obo_id'] ?? ''), $m) ? self::parse((string) $query['obo_id']) : null,
            str_ends_with($host, 'string-db.org') => $found('~/network/([1-9]\d*\.[^/?#&]+)~', Scheme::STRING),
            str_ends_with($host, 'genenames.org') => $found('~HGNC:(\d+)~i', Scheme::HGNC),
            'omim.org' === $host || 'www.omim.org' === $host => $found('~/entry/(\d{6})~', Scheme::OMIM),
            str_ends_with($host, 'monarchinitiative.org') => $found('~MONDO:(\d{7})~i', Scheme::MONDO),
            str_ends_with($host, 'orpha.net') => $found('~(?:/detail/|Expert=)(\d+)~', Scheme::ORPHANET),
            str_ends_with($host, 'ncbi.nlm.nih.gov') && str_starts_with($path, 'clinvar/variation/') => self::tryOf(Scheme::CLINVAR, explode('/', $path)[2] ?? ''),
            str_ends_with($host, 'ncbi.nlm.nih.gov') && str_starts_with($path, 'snp/') => self::tryOf(Scheme::DBSNP, $last),
            str_ends_with($host, 'ebi.ac.uk') && str_contains($path, 'chembl') => $found('~(CHEMBL\d+)~i', Scheme::CHEMBL),
            str_ends_with($host, 'ebi.ac.uk') && str_contains($path, 'chebi') => $found('~CHEBI:(\d+)~i', Scheme::CHEBI),
            str_ends_with($host, 'wikidata.org') => $found('~/(?:wiki|entity)/(Q\d+)~i', Scheme::WIKIDATA),
            str_ends_with($host, 'uniprot.org') => self::firstOf(Scheme::UNIPROT, $path),
            str_ends_with($host, 'rcsb.org') => self::tryOf(Scheme::PDB, $last),
            str_ends_with($host, 'gbif.org') && str_contains($path, 'occurrence/') => $found('~/occurrence/(\d+)~', Scheme::GBIF_OCCURRENCE),
            str_ends_with($host, 'gbif.org') => $found('~/(?:species|taxon)/([0-9A-Za-z]+)~', Scheme::GBIF),
            str_ends_with($host, 'inaturalist.org') && str_contains($path, 'observations/') => $found('~/observations/(\d+)~', Scheme::INATURALIST_OBSERVATION),
            str_ends_with($host, 'inaturalist.org') => $found('~/taxa/(\d+)~', Scheme::INATURALIST),
            str_ends_with($host, 'marinespecies.org') => self::tryOf(Scheme::WORMS, (string) ($query['id'] ?? '')) ?? $found('~/AphiaRecordByAphiaID/(\d+)~', Scheme::WORMS),
            'api.obis.org' === $host => $found('~/occurrence/([0-9a-f-]{36})~i', Scheme::OBIS_OCCURRENCE),
            str_ends_with($host, 'itis.gov') => self::tryOf(Scheme::ITIS, (string) ($query['search_value'] ?? $query['tsn'] ?? '')),
            str_ends_with($host, 'catalogueoflife.org') || str_ends_with($host, 'checklistbank.org') => $found('~/taxon/([0-9A-Za-z]{1,12})(?:[/?#]|$)~', Scheme::COL),
            str_ends_with($host, 'paleobiodb.org') => isset($query['taxon_no']) ? self::tryOf(Scheme::PBDB, (string) $query['taxon_no']) : (isset($query['occurrence_no']) ? self::tryOf(Scheme::PBDB_OCCURRENCE, (string) $query['occurrence_no']) : (isset($query['id']) ? self::parse((string) $query['id']) : null)),
            'ebird.org' === $host || str_ends_with($host, '.ebird.org') => $found('~/checklist/(S\d+)~i', Scheme::EBIRD_CHECKLIST) ?? $found('~/species/([a-z0-9]+)~i', Scheme::EBIRD),
            str_ends_with($host, 'iucnredlist.org') => $found('~/species/(\d+)~', Scheme::IUCN),
            'earthquake.usgs.gov' === $host => $found('~/eventpage/([a-z0-9_]+)~i', Scheme::USGS_EVENT),
            str_ends_with($host, 'ncbi.nlm.nih.gov') && str_starts_with($path, 'gene/') => self::tryOf(Scheme::NCBI_GENE, $last),
            str_ends_with($host, 'ncbi.nlm.nih.gov') && str_contains(strtolower($path), 'taxonomy') => self::tryOf(Scheme::NCBI_TAXON, (string) ($query['id'] ?? $last)),
            str_ends_with($host, 'ensembl.org') => $found('~(ENS[A-Z]*[EFGPRT]\d{11})~i', Scheme::ENSEMBL) ?? (preg_match('~/id/([^/?#]+)~', $path === '' ? '' : '/'.$path, $m) ? self::tryOf(Scheme::ENSEMBL_GENOMES, $m[1]) : null),
            'commonchemistry.cas.org' === $host => self::tryOf(Scheme::CAS, (string) ($query['cas_rn'] ?? '')),
            'comptox.epa.gov' === $host => $found('~(DTXSID\d+)~i', Scheme::DSSTOX),
            'precision.fda.gov' === $host => self::tryOf(Scheme::UNII, $last),
            str_ends_with($host, 'kegg.jp') || str_ends_with($host, 'genome.jp') => $found('~\b([CD]\d{5})\b~', Scheme::KEGG)
                ?? $found('~\b(R\d{5})\b~', Scheme::KEGG_REACTION) ?? $found('~\bec:(\d[\d.n-]*)~i', Scheme::EC) ?? $found('~(?:/|\bpath:|=)([a-z]{2,4}\d{5})\b~', Scheme::KEGG_PATHWAY),
            str_ends_with($host, 'rhea-db.org') => $found('~(?:/rhea/|RHEA:)(\d{5,6})\b~i', Scheme::RHEA),
            'enzyme.expasy.org' === $host => $found('~/EC/([\d.n-]+)~i', Scheme::EC),
            str_ends_with($host, 'enzyme-database.org') => self::tryOf(Scheme::EC, (string) ($query['ec'] ?? '')),
            str_ends_with($host, 'crystallography.net') => $found('~/cod/(\d{7})\b~', Scheme::COD),
            str_ends_with($host, 'materialsproject.org') => $found('~/materials/((?:mp|mvc)-\d+)~i', Scheme::MATERIALS_PROJECT),
            str_ends_with($host, 'oqmd.org') => $found('~/entry/(\d+)~', Scheme::OQMD),
            'physics.nist.gov' === $host => $found('~/cuu/Value\?([A-Za-z][A-Za-z0-9]*)~', Scheme::CODATA),
            str_contains($host, 'simbad') => self::tryOf(Scheme::SIMBAD, (string) ($query['Ident'] ?? $query['ident'] ?? '')),
            str_ends_with($host, 'reactome.org') => $found('~(R-[A-Z]{3}-\d+)~i', Scheme::REACTOME),
            'exoplanetarchive.ipac.caltech.edu' === $host => preg_match('~^overview/(.+)$~', $path, $m) ? self::tryOf(Scheme::EXOPLANET, $m[1]) : null,
            'www.ncei.noaa.gov' === $host => $found('~GHCND:([A-Z0-9-]{11})~i', Scheme::GHCN),
            'oscar.wmo.int' === $host => preg_match('~stationReportDetails/([0-9A-Za-z-]+)~', $fragment, $m) ? self::tryOf(Scheme::WIGOS, $m[1]) : self::tryOf(Scheme::WIGOS, (string) ($query['wigosId'] ?? '')),
            'api.weather.gov' === $host => preg_match('~^stations/([A-Z0-9]+)$~i', $path, $m) ? self::tryOf(Scheme::NWS_STATION, $m[1]) : null,
            'www.weather.gov' === $host => self::tryOf(Scheme::NWS_STATION, (string) ($query['site'] ?? '')),
            'ssd.jpl.nasa.gov' === $host => preg_match('~sstr=([^&]+)~', $fragment.'&'.http_build_query($query), $m) ? self::tryOf(Scheme::JPL_SBDB, $m[1]) : null,
            default => null,
        };
    }

    /** The first segment of a path that is an identifier of the scheme: uniprotkb/P69905/entry. */
    private static function firstOf(Scheme $scheme, string $path): ?self
    {
        foreach (explode('/', $path) as $segment) {
            if (null !== ($identifier = self::tryOf($scheme, (string) preg_replace('~\.\w+$~', '', $segment)))) {
                return $identifier;
            }
        }

        return null;
    }

    /**
     * The genome (Ensembl's production name) of an id of the ensembl.genomes
     * scheme, when its shape tells it: "drosophila_melanogaster" for
     * FBgn0000008; null otherwise.
     */
    public static function ensemblSpecies(self|string $id): ?string
    {
        $value = $id instanceof self ? $id->value : trim($id);
        foreach (self::ENSEMBL_GENOMES as [$pattern, , , $species]) {
            if (preg_match($pattern, $value)) {
                return $species;
            }
        }

        return null;
    }

    /** A known genome's id as its community writes it; null when no known shape. */
    private static function ensemblGenomes(string $value): ?string
    {
        $value = trim($value);
        foreach (self::ENSEMBL_GENOMES as [$pattern, $prefix, $case]) {
            if (preg_match($pattern, $value)) {
                $rest = substr($value, \strlen($prefix));

                return $prefix.match ($case) {
                    'upper' => strtoupper($rest),
                    'lower' => strtolower($rest),
                    default => $rest,
                };
            }
        }

        return null;
    }

    /**
     * A GBIF taxon key in either of its forms: the number of the legacy GBIF
     * Backbone Taxonomy (5219404, frozen since 2023, Wikidata's P846), or the
     * key of the Catalogue of Life eXtended Release GBIF's taxonomy has been
     * since June 2026 (4CGXP: one to six of the digits 2 to 9 and the
     * consonants, Wikidata's P14607). A key of digits alone is read as a
     * legacy number.
     */
    private static function normalizeGbif(string $value): ?string
    {
        $value = (string) preg_replace('~^GBIF:~', '', $value);

        return match (true) {
            (bool) preg_match('~^0*([1-9]\d{0,9})$~', $value, $m) => $m[1],
            (bool) preg_match('~^[2-9BCDFGHJKLMNPQRSTVWXYZ]{1,6}$~', $value) => $value,
            default => null,
        };
    }

    private static function normalizeCas(string $value): ?string
    {
        if (!preg_match(self::CAS, $value, $m)) {
            return null;
        }
        $digits = ltrim($m[1], '0').$m[2];
        if (\strlen(ltrim($m[1], '0')) < 2) {
            return null;
        }
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $digit) {
            $sum += ($i + 1) * (int) $digit;
        }

        return $sum % 10 === (int) $m[3] ? ltrim($m[1], '0').'-'.$m[2].'-'.$m[3] : null;
    }

    /** A name as an identifier (SIMBAD's, JPL's): its spaces collapsed. */
    private static function name(string $value): ?string
    {
        $value = trim((string) preg_replace('~\s+~u', ' ', $value));

        return '' === $value || \strlen($value) > 240 ? null : $value;
    }
}
